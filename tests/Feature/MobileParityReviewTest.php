<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MobileMutation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class MobileParityReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    #[DataProvider('retryCases')]
    public function test_complete_boundary_rolls_back_before_retry(bool $separate, string $failure, int $failures): void
    {
        config(['session.driver' => 'database', 'session.connection' => $separate ? 'review_sessions' : config('database.default')]);
        if ($separate) {
            config(['database.connections.review_sessions' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            DB::connection('review_sessions')->getSchemaBuilder()->create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->integer('user_id');
            });
        }
        $sessions = DB::connection(config('session.connection'));
        $user = User::factory()->create();
        $original = $user->createToken('Original', ['mobile:access'], now()->addDay());
        $original->accessToken->forceFill(['account_version' => $user->auth_version])->save();
        $reset = Password::createToken($user);
        $session = ['id' => 'review-session', 'user_id' => $user->id];
        if (! $separate) {
            $session += ['payload' => 'private', 'last_activity' => now()->timestamp];
        }
        $sessions->table('sessions')->insert($session);
        $request = Request::create('/api/v1/account/profile', 'PATCH');
        $request->headers->set('Authorization', 'Bearer '.$original->plainTextToken);
        $request->setUserResolver(fn () => $user->withAccessToken($original->accessToken));
        $attempts = 0;
        $assertClean = function () use ($user, $original, $reset, $sessions): void {
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertSame($user->auth_version, $user->fresh()->auth_version);
            $this->assertDatabaseCount('personal_access_tokens', 1);
            $this->assertNotNull(PersonalAccessToken::findToken($original->plainTextToken));
            $this->assertTrue(Password::tokenExists($user, $reset));
            $this->assertDatabaseCount('activity_logs', 0);
            $this->assertSame(1, $sessions->table('sessions')->count());
        };
        $caught = null;
        $result = null;
        try {
            $result = MobileMutation::run($request, function (User $actor, ?User $target, PersonalAccessToken $token) use (&$attempts, $assertClean, $sessions, $failure, $failures): string {
                $assertClean();
                $attempts++;
                $actor->update(['email' => 'changed@example.test']);
                $next = MobileMutation::rotate($actor, $token);
                ActivityLog::record('account.profile_updated', $actor);
                if ($attempts <= $failures) {
                    $throw = function () use ($failure): never {
                        throw match ($failure) {
                            'validation' => ValidationException::withMessages(['email' => 'database is locked']),
                            'arbitrary' => new RuntimeException('database is locked'),
                            'serialization' => new PDOException('serialization failure', 40001),
                            default => new PDOException('database is locked'),
                        };
                    };
                    if ($failure === 'session') {
                        $sessions->transaction($throw);
                    } else {
                        $throw();
                    }
                }

                return $next;
            });
        } catch (Throwable $error) {
            $caught = $error;
        }
        $retryable = ! in_array($failure, ['validation', 'arbitrary'], true);
        $expectedAttempts = $retryable ? min($failures + 1, 3) : 1;
        $this->assertSame($expectedAttempts, $attempts, $caught?->getMessage() ?? '');
        $this->assertSame(0, DB::connection()->transactionLevel());
        $this->assertSame(0, $sessions->transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->assertFalse($sessions->getPdo()->inTransaction());
        if (! $retryable || $failures >= 3) {
            $this->assertNotNull($caught);
            $this->assertNull($result);
            $assertClean();
        } else {
            $this->assertNull($caught, $caught?->getMessage() ?? '');
            $this->assertNotNull(PersonalAccessToken::findToken($result));
            $this->assertNull(PersonalAccessToken::findToken($original->plainTextToken));
            $this->assertDatabaseCount('personal_access_tokens', 1);
            $this->assertDatabaseCount('activity_logs', 1);
            $this->assertDatabaseCount('password_reset_tokens', 0);
            $this->assertSame(0, $sessions->table('sessions')->count());
            $this->assertSame('changed@example.test', $user->fresh()->email);
        }
    }

    public static function retryCases(): array
    {
        $cases = [];
        foreach ([false, true] as $separate) {
            foreach (['deadlock', 'serialization', 'session'] as $failure) {
                foreach ([2, 3] as $failures) {
                    $cases[($separate ? 'separate' : 'same').'-'.$failure.'-'.$failures] = [$separate, $failure, $failures];
                }
            }
            foreach (['validation', 'arbitrary'] as $failure) {
                $cases[($separate ? 'separate' : 'same').'-'.$failure] = [$separate, $failure, 3];
            }
        }

        return $cases;
    }
}
