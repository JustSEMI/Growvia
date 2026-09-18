<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountMutation;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AccountMutationTest extends TestCase
{
    use RefreshDatabase;

    private ?PDO $competitor = null;

    #[DataProvider('currentActorVersions')]
    public function test_callback_receives_a_fresh_actor_inside_a_transaction_and_returns_its_result(bool $legacy): void
    {
        $actor = User::factory()->create([
            'name' => 'Original name',
            'auth_version' => $legacy ? null : Str::random(40),
        ]);
        $request = $this->requestFor($actor);
        $level = DB::transactionLevel();
        User::query()->findOrFail($actor->id)->update(['name' => 'Fresh name']);

        $result = AccountMutation::run($request, function (User $freshActor, ?User $target) use ($actor, $level): User {
            $this->assertNotSame($actor, $freshActor);
            $this->assertTrue($actor->is($freshActor));
            $this->assertSame('Fresh name', $freshActor->name);
            $this->assertNull($target);
            $this->assertSame($level + 1, DB::transactionLevel());
            $freshActor->update(['name' => 'Callback name']);

            return $freshActor;
        });

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('Callback name', $actor->fresh()->name);
        $this->assertSame('Original name', $actor->name);
        $this->assertSame($actor, $request->user());
        $this->assertSame($level, DB::transactionLevel());
    }

    public static function currentActorVersions(): array
    {
        return [['legacy' => true], ['legacy' => false]];
    }

    #[DataProvider('actorRevocations')]
    public function test_a_revoked_actor_cannot_reach_the_callback(bool $legacy, string $field): void
    {
        $actor = User::factory()->admin()->create([
            'auth_version' => $legacy ? null : Str::random(40),
        ]);
        $request = $this->requestFor($actor);
        $changes = match ($field) {
            'password' => ['password' => Str::random(48)],
            'email' => ['email' => 'changed@example.test'],
            'role' => ['role' => 'user'],
            'is_active' => ['is_active' => false],
            'auth_version' => ['auth_version' => Str::random(40)],
        };
        $actor->fresh()->forceFill($changes)->save();
        $called = false;

        try {
            AccountMutation::run($request, function (User $freshActor) use (&$called): void {
                $called = true;
                $freshActor->update(['name' => 'Forbidden change']);
            });
            $this->fail('A revoked actor must authenticate again.');
        } catch (AuthenticationException $exception) {
            $this->assertSame(['web'], $exception->guards());
            $this->assertSame('Unauthenticated.', $exception->getMessage());
        }

        $this->assertFalse($called);
        $this->assertSame($actor->name, $actor->fresh()->name);
        $this->assertSame($actor, $request->user());
    }

    public static function actorRevocations(): array
    {
        return [
            'legacy session after password reset' => [true, 'password'],
            'versioned session after password reset' => [false, 'password'],
            'email change' => [false, 'email'],
            'role change' => [false, 'role'],
            'deactivation' => [false, 'is_active'],
            'explicit session revocation' => [false, 'auth_version'],
        ];
    }

    #[DataProvider('unavailableActors')]
    public function test_an_unavailable_actor_cannot_reach_the_callback(string $state): void
    {
        $actor = $state === 'guest' ? null : User::factory()->create();
        if ($state === 'deleted') {
            $actor->fresh()->delete();
        } elseif ($state === 'inactive') {
            // Also reject inactive rows when no observer/version rotation ran.
            DB::table('users')->where('id', $actor->id)->update(['is_active' => false]);
        }

        try {
            AccountMutation::run($this->requestFor($actor), function (): void {
                $this->fail('An unavailable actor must not reach the callback.');
            });
            $this->fail('An unavailable actor must authenticate again.');
        } catch (AuthenticationException $exception) {
            $this->assertSame(['web'], $exception->guards());
            $this->assertSame('Unauthenticated.', $exception->getMessage());
        }
    }

    public static function unavailableActors(): array
    {
        return [['guest'], ['deleted'], ['inactive']];
    }

    #[DataProvider('targetKinds')]
    public function test_callback_receives_the_fresh_locked_target(string $kind): void
    {
        $target = User::factory()->create(['is_active' => $kind !== 'inactive']);
        $actor = $kind === 'self' ? $target->fresh() : User::factory()->admin()->create();
        $request = $this->requestFor($actor);
        $target->fresh()->update(['name' => 'Fresh target']);
        $retrieved = [];
        User::retrieved(function (User $user) use (&$retrieved): void {
            $retrieved[] = $user->id;
        });

        $result = AccountMutation::run($request, function (User $freshActor, ?User $freshTarget) use ($target, $kind): User {
            $this->assertNotNull($freshTarget);
            $this->assertNotSame($target, $freshTarget);
            $this->assertTrue($target->is($freshTarget));
            $this->assertSame('Fresh target', $freshTarget->name);
            if ($kind === 'self') {
                $this->assertSame($freshActor, $freshTarget);
            }

            return $freshTarget;
        }, $target);

        $expectedIds = array_values(array_unique([$actor->id, $target->id]));
        sort($expectedIds, SORT_NUMERIC);
        $this->assertSame($expectedIds, $retrieved);
        $this->assertSame('Fresh target', $result->name);
        $this->assertSame($actor, $request->user());
    }

    public static function targetKinds(): array
    {
        return [['other'], ['inactive'], ['self']];
    }

    #[DataProvider('staleTargets')]
    public function test_a_changed_target_must_be_reloaded_before_mutation(bool $legacy, bool $self): void
    {
        $target = User::factory()->create([
            'auth_version' => $legacy ? null : Str::random(40),
        ]);
        $target->fresh()->update(['email' => 'target-changed@example.test']);
        $actor = $self ? $target->fresh() : User::factory()->admin()->create();
        $called = false;

        try {
            AccountMutation::run($this->requestFor($actor), function () use (&$called): void {
                $called = true;
            }, $target);
            $this->fail('A stale route-bound target must not be overwritten.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'user' => ['Data akun telah berubah. Muat ulang halaman lalu coba lagi.'],
            ], $exception->errors());
        }

        $this->assertFalse($called);
        $this->assertSame('target-changed@example.test', $target->fresh()->email);
    }

    public static function staleTargets(): array
    {
        return [
            'legacy target' => [true, false],
            'versioned target' => [false, false],
            'different snapshots of the same account' => [false, true],
        ];
    }

    #[DataProvider('missingTargets')]
    public function test_a_missing_target_never_reaches_the_callback(bool $deleted): void
    {
        $actor = User::factory()->admin()->create();
        $target = $deleted ? User::factory()->create() : new User;
        if ($deleted) {
            $target->fresh()->delete();
        }

        try {
            AccountMutation::run($this->requestFor($actor), function (): void {
                $this->fail('A missing target must not reach the callback.');
            }, $target);
            $this->fail('A missing target must produce a not-found response.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(User::class, $exception->getModel());
            $this->assertSame([], $exception->getIds());
        }
    }

    public static function missingTargets(): array
    {
        return [['deleted' => true], ['deleted' => false]];
    }

    public function test_callback_failure_rolls_back_both_accounts_and_observer_artifacts(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $token = Password::createToken($target);
        $originalPassword = $target->getRawOriginal('password');
        $level = DB::transactionLevel();
        $calls = 0;

        try {
            AccountMutation::run($this->requestFor($actor), function (User $freshActor, ?User $freshTarget) use (&$calls): void {
                $calls++;
                $freshActor->update(['name' => 'Rolled back actor']);
                $freshTarget->update([
                    'name' => 'Rolled back target',
                    'password' => Str::random(48),
                ]);

                throw new RuntimeException('Cancel mutation.');
            }, $target);
            $this->fail('The callback exception must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cancel mutation.', $exception->getMessage());
        }

        $this->assertSame(1, $calls);
        $this->assertSame($actor->name, $actor->fresh()->name);
        $freshTarget = $target->fresh();
        $this->assertSame($target->name, $freshTarget->name);
        $this->assertSame($target->auth_version, $freshTarget->auth_version);
        $this->assertTrue(hash_equals($originalPassword, $freshTarget->getRawOriginal('password')));
        $this->assertTrue(Password::tokenExists($freshTarget, $token));
        $this->assertSame($level, DB::transactionLevel());
    }

    public function test_sqlite_reserves_the_writer_lock_before_the_callback_writes(): void
    {
        $this->withDisposableSqlite(function (): void {
            $actor = User::query()->firstOrFail();

            AccountMutation::run($this->requestFor($actor), function (User $freshActor): void {
                $blocked = false;
                try {
                    $this->competitor->exec('BEGIN IMMEDIATE TRANSACTION');
                } catch (PDOException $exception) {
                    $this->assertSame(5, $exception->errorInfo[1]);
                    $blocked = true;
                } finally {
                    if ($this->competitor->inTransaction()) {
                        $this->competitor->rollBack();
                    }
                }

                $this->assertTrue($blocked, 'A competing writer must be blocked before any callback write.');
                $freshActor->update(['name' => 'Committed name']);
            });

            $this->assertSame(0, DB::transactionLevel());
            $this->competitor->exec('BEGIN IMMEDIATE TRANSACTION');
            $this->assertSame('Committed name', $this->competitor->query('SELECT name FROM users')->fetchColumn());
            $this->competitor->rollBack();
        });
    }

    public function test_sqlite_connections_apply_the_five_second_busy_timeout(): void
    {
        $this->withDisposableSqlite(function (): void {
            $this->assertSame(5000, DB::connection()->getConfig('busy_timeout'));
            $this->assertSame(5000, (int) DB::scalar('PRAGMA busy_timeout'));
            $this->assertSame('IMMEDIATE', DB::connection()->getConfig('transaction_mode'));
        });
    }

    #[DataProvider('snapshotsChangedAtTransactionStart')]
    public function test_versions_are_captured_before_the_transaction_starts(bool $changeTarget): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $request = $this->requestFor($actor);
        DB::connection()->beforeStartingTransaction(function () use ($actor, $target, $changeTarget): void {
            $changed = $changeTarget ? $target : $actor;
            $changed->forceFill(['auth_version' => Str::random(40)])->save();
        });

        $this->expectException($changeTarget ? ValidationException::class : AuthenticationException::class);
        AccountMutation::run($request, function (): void {
            $this->fail('Changing the supplied instance must not replace the captured version.');
        }, $target);
    }

    public static function snapshotsChangedAtTransactionStart(): array
    {
        return [['changeTarget' => false], ['changeTarget' => true]];
    }

    public function test_a_competing_writer_prevents_reads_then_a_committed_revocation_rejects_the_actor(): void
    {
        $this->withDisposableSqlite(function (): void {
            $actor = User::query()->firstOrFail();
            $request = $this->requestFor($actor);
            $reads = 0;
            DB::listen(function ($query) use (&$reads): void {
                if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'users')) {
                    $reads++;
                }
            });
            // Fail fast only on this disposable connection; production waits 5 seconds.
            DB::statement('PRAGMA busy_timeout = 0');
            $this->competitor->exec('BEGIN IMMEDIATE TRANSACTION');

            try {
                AccountMutation::run($request, function (): void {
                    $this->fail('A blocked mutation must not invoke the callback.');
                });
                $this->fail('An existing writer must block the transaction boundary.');
            } catch (PDOException $exception) {
                $this->assertSame(5, $exception->errorInfo[1]);
            }

            $this->assertSame(0, $reads);
            $this->assertSame(0, DB::transactionLevel());
            $statement = $this->competitor->prepare('UPDATE users SET auth_version = ? WHERE id = ?');
            $statement->execute([Str::random(40), $actor->id]);
            $statement = null;
            $this->competitor->commit();

            try {
                AccountMutation::run($request, function (): void {
                    $this->fail('A committed revocation must be observed under the new lock.');
                });
                $this->fail('A revoked actor must authenticate again.');
            } catch (AuthenticationException $exception) {
                $this->assertSame(['web'], $exception->guards());
            }

            $this->assertSame('Original name', DB::table('users')->value('name'));
        });
    }

    #[DataProvider('retryOutcomes')]
    public function test_retryable_conflicts_get_three_fresh_transaction_attempts(bool $eventualSuccess): void
    {
        $this->withDisposableSqlite(function () use ($eventualSuccess): void {
            $actor = User::query()->firstOrFail();
            $attemptActors = [];
            $calls = 0;

            try {
                $result = AccountMutation::run($this->requestFor($actor), function (User $freshActor) use (&$calls, &$attemptActors, $eventualSuccess): string {
                    $calls++;
                    $this->assertSame('Original name', $freshActor->name);
                    $this->assertSame(1, DB::transactionLevel());
                    $attemptActors[] = $freshActor;
                    $freshActor->update(['name' => 'Committed retry']);
                    if (! $eventualSuccess || $calls < 3) {
                        throw new PDOException('Transaction conflict.', 40001);
                    }

                    return 'committed';
                });
                $this->assertTrue($eventualSuccess);
                $this->assertSame('committed', $result);
            } catch (PDOException $exception) {
                $this->assertFalse($eventualSuccess);
                $this->assertSame(40001, $exception->getCode());
            }

            $this->assertSame(3, $calls);
            $this->assertNotSame($attemptActors[0], $attemptActors[1]);
            $this->assertNotSame($attemptActors[1], $attemptActors[2]);
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame($eventualSuccess ? 'Committed retry' : 'Original name', DB::table('users')->value('name'));
        });
    }

    public static function retryOutcomes(): array
    {
        return [['eventualSuccess' => true], ['eventualSuccess' => false]];
    }

    private function withDisposableSqlite(Closure $callback): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'growvia-account-mutation-');
        $this->assertNotFalse($path);
        $default = DB::getDefaultConnection();
        $name = 'account_mutation_probe';
        config(['database.connections.'.$name => array_replace(config('database.connections.sqlite'), [
            'url' => null,
            'database' => $path,
        ])]);

        try {
            DB::setDefaultConnection($name);
            DB::connection()->getSchemaBuilder()->create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->string('auth_version', 40)->nullable();
                $table->timestamps();
            });
            DB::table('users')->insert(['id' => 1, 'name' => 'Original name', 'is_active' => true]);
            $this->competitor = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->competitor->exec('PRAGMA busy_timeout = 0');

            return $callback();
        } finally {
            if ($this->competitor?->inTransaction()) {
                $this->competitor->rollBack();
            }
            $this->competitor = null;
            DB::purge($name);
            DB::setDefaultConnection($default);
            config(['database.connections.'.$name => null]);
            foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }

    private function requestFor(?User $actor): Request
    {
        $request = Request::create('/account-mutation', 'PATCH');
        $request->setUserResolver(static fn (?string $guard = null): ?User => $actor);

        return $request;
    }
}
