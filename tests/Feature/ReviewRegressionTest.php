<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
    }

    // Use actual encrypted cookie jars, not actingAs(), to exercise session boundaries.
    private function requestAs(string $method, string $uri, array $data, array &$cookies): TestResponse
    {
        Auth::forgetGuards();
        $this->app->forgetInstance('auth.driver');
        Session::clearResolvedInstance('session');
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance(StartSession::class);
        $this->app->make('cookie')->flushQueuedCookies();
        $this->app->make('redirect')->setSession($this->app->make('session.store'));
        $response = $this->call($method, $uri, $data, $cookies);
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getValue() === '' || ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() <= time())) {
                unset($cookies[$cookie->getName()]);
            } else {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    private function login(User $user, bool $remember = false): array
    {
        $cookies = [];
        $this->requestAs('POST', '/login', [
            'email' => $user->email, 'password' => 'password', 'remember' => $remember,
        ], $cookies)->assertRedirect()->assertSessionHasNoErrors();

        return $cookies;
    }

    private function reauthenticationRequests(User $actor, User $target): array
    {
        return [
            ['PATCH', '/pengaturan/profil', [
                'name' => $actor->name, 'email' => 'changed@example.test', 'profile_password' => 'wrong-password',
            ], 'profile_password'],
            ['PATCH', '/pengaturan/kata-sandi', [
                'current_password' => 'wrong-password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
            ], 'current_password'],
            ['PATCH', '/pengguna/'.$target->id, [
                'name' => $target->name, 'email' => $target->email, 'role' => 'user',
                'password' => 'new-password-123', 'admin_password' => 'wrong-password',
            ], 'admin_password'],
            ['DELETE', '/pengaturan/sesi', ['sessions_password' => 'wrong-password'], 'sessions_password'],
        ];
    }

    public function test_reauthentication_shares_account_budget_across_endpoints_and_ips_without_changing_auth(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $cookies = $this->login($admin, true);
        $sessionId = session()->getId();
        $csrf = session()->token();
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $requests = $this->reauthenticationRequests($admin, $target);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)]);
            [$method, $uri, $data, $field] = $requests[$attempt % 4];
            $this->requestAs($method, $uri, $data, $cookies)->assertSessionHasErrors($field);
            $this->assertAuthenticatedAs($admin, 'web');
            $this->assertSame($sessionId, session()->getId());
            $this->assertSame($csrf, session()->token());
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100']);
        foreach ($requests as [$method, $uri, $data]) {
            $this->requestAs($method, $uri, $data, $cookies)->assertStatus(429);
            $this->assertAuthenticatedAs($admin, 'web');
            $this->assertSame($sessionId, session()->getId());
            $this->assertSame($csrf, session()->token());
        }
        $this->requestAs('GET', '/pengaturan/sesi', [], $cookies)->assertOk();
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame(1, ActivityLog::query()->count());
    }

    public function test_reauthentication_ip_budget_is_independent_of_account(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();
        $target = User::factory()->create();
        $firstJar = $this->login($first);
        $secondJar = $this->login($second);
        $requests = $this->reauthenticationRequests($first, $target);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            [$method, $uri, $data, $field] = $requests[$attempt % 4];
            if ($attempt % 2 === 0) {
                $this->requestAs($method, $uri, $data, $firstJar)->assertSessionHasErrors($field);
            } else {
                $this->requestAs($method, $uri, $data, $secondJar)->assertSessionHasErrors($field);
            }
        }
        $this->requestAs('DELETE', '/pengaturan/sesi', ['sessions_password' => 'wrong-password'], $secondJar)->assertStatus(429);
        $this->assertAuthenticatedAs($second, 'web');
        $this->assertSame(2, ActivityLog::query()->count());
    }

    #[DataProvider('resetBuckets')]
    public function test_reset_submission_is_bounded_before_transaction_and_mutation(bool $rotateIp): void
    {
        $user = User::factory()->create();
        Password::createToken($user);
        $beforeUsers = DB::table('users')->get()->toJson();
        $beforeTokens = DB::table('password_reset_tokens')->get()->toJson();
        $transactions = 0;
        DB::connection()->beforeStartingTransaction(function () use (&$transactions): void {
            $transactions++;
        });
        $cookies = [];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $rotateIp ? '192.0.2.'.($attempt + 1) : '192.0.2.1']);
            $email = $rotateIp ? 'unknown@example.test' : 'unknown'.$attempt.'@example.test';
            $this->requestAs('POST', '/reset-sandi', [
                'email' => $email, 'token' => 'arbitrary-token',
                'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
            ], $cookies)->assertRedirect()->assertSessionHasErrors('email');
        }
        $this->assertSame(5, $transactions);
        $this->withServerVariables(['REMOTE_ADDR' => $rotateIp ? '192.0.2.100' : '192.0.2.1']);
        $this->requestAs('POST', '/reset-sandi', [
            'email' => $rotateIp ? 'unknown@example.test' : 'another@example.test', 'token' => 'arbitrary-token',
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ], $cookies)->assertStatus(429);
        $this->assertSame(5, $transactions);
        $this->assertSame($beforeUsers, DB::table('users')->get()->toJson());
        $this->assertSame($beforeTokens, DB::table('password_reset_tokens')->get()->toJson());
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertGuest('web');
    }

    public function test_remembered_login_is_recorded_once_after_validation(): void
    {
        $user = User::factory()->create();
        $original = $this->login($user, true);
        $this->assertSame(1, ActivityLog::query()->where('action', 'auth.login')->count());
        $recaller = Auth::guard('web')->getRecallerName();
        $remembered = [$recaller => $original[$recaller]];
        $this->requestAs('GET', '/pengaturan/sesi', [], $remembered)->assertOk();
        $this->assertTrue(Auth::guard('web')->viaRemember());
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame(2, ActivityLog::query()->where('action', 'auth.login')->count());
        $record = ActivityLog::query()->latest('id')->first();
        $this->assertSame($user->id, $record->actor_id);
        $this->assertSame($user->id, $record->subject_id);
        $this->assertSame([], $record->fields);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->requestAs('GET', '/pengaturan/sesi', [], $remembered)->assertOk();
        $this->assertFalse(Auth::guard('web')->viaRemember());
        $this->assertSame(2, ActivityLog::query()->where('action', 'auth.login')->count());
    }

    public function test_remembered_login_is_recorded_once_before_reauthentication_throttles(): void
    {
        $user = User::factory()->create();
        $original = $this->login($user, true);
        $recaller = Auth::guard('web')->getRecallerName();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->requestAs('DELETE', '/pengaturan/sesi', [
                '_token' => session()->token(), 'sessions_password' => 'wrong-password',
            ], $original)->assertRedirect()->assertSessionHasErrors('sessions_password');
        }
        $this->assertSame(1, ActivityLog::query()->where('action', 'auth.login')->count());

        // Establish a guest session and CSRF token before attaching the retained recaller.
        $remembered = [];
        $this->requestAs('GET', '/login', [], $remembered)->assertOk();
        $this->assertGuest('web');
        $this->assertArrayNotHasKey($recaller, $remembered);
        $this->assertArrayHasKey(config('session.cookie'), $remembered);
        $csrf = session()->token();
        $remembered[$recaller] = $original[$recaller];

        $this->requestAs('DELETE', '/pengaturan/sesi', [
            '_token' => $csrf, 'sessions_password' => 'wrong-password',
        ], $remembered)->assertStatus(429);
        $this->assertTrue(Auth::guard('web')->viaRemember());
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame(2, ActivityLog::query()->where('action', 'auth.login')->count());

        $this->requestAs('GET', '/pengaturan/sesi', [], $remembered)->assertOk();
        $this->assertFalse(Auth::guard('web')->viaRemember());
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame(2, ActivityLog::query()->where('action', 'auth.login')->count());
    }

    public function test_remembered_login_is_recorded_once_before_route_binding_returns_not_found(): void
    {
        $user = User::factory()->admin()->create();
        $original = $this->login($user, true);
        $recaller = Auth::guard('web')->getRecallerName();
        $this->assertSame(1, ActivityLog::query()->where('action', 'auth.login')->count());
        $remembered = [];
        $this->requestAs('GET', '/login', [], $remembered)->assertOk();
        $csrf = session()->token();
        $remembered[$recaller] = $original[$recaller];

        $this->requestAs('DELETE', '/pengguna/0', ['_token' => $csrf], $remembered)->assertNotFound();
        $this->assertTrue(Auth::guard('web')->viaRemember());
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame(2, ActivityLog::query()->where('action', 'auth.login')->count());

        $this->requestAs('GET', '/pengaturan/sesi', [], $remembered)->assertOk();
        $this->assertFalse(Auth::guard('web')->viaRemember());
        $this->assertSame(2, ActivityLog::query()->where('action', 'auth.login')->count());
    }

    public function test_remembered_inactive_account_is_not_logged(): void
    {
        $user = User::factory()->create();
        $original = $this->login($user, true);
        $recaller = Auth::guard('web')->getRecallerName();
        $remembered = [$recaller => $original[$recaller]];
        // Keep a valid recaller to exercise middleware validation, not observer revocation.
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->requestAs('GET', '/pengaturan/sesi', [], $remembered)->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertSame(1, ActivityLog::query()->where('action', 'auth.login')->count());
        $this->assertArrayNotHasKey($recaller, $remembered);
        $this->assertNull(session('account_version'));
    }

    public function test_remembered_activity_failure_clears_current_device_session_and_csrf(): void
    {
        $user = User::factory()->create();
        $original = $this->login($user, true);
        $recaller = Auth::guard('web')->getRecallerName();
        $remembered = [];
        $this->requestAs('GET', '/login', [], $remembered)->assertOk();
        $oldId = session()->getId();
        $oldCsrf = session()->token();
        $remembered[$recaller] = $original[$recaller];
        DB::unprepared("CREATE TEMP TRIGGER fail_login_activity BEFORE INSERT ON activity_logs WHEN NEW.action = 'auth.login' BEGIN SELECT RAISE(ABORT, 'activity unavailable'); END");
        $this->requestAs('GET', '/pengaturan/sesi', [], $remembered)->assertStatus(500);
        $this->assertGuest('web');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        $this->assertNull(session('account_version'));
        $this->assertArrayNotHasKey($recaller, $remembered);
        $this->assertSame(1, ActivityLog::query()->where('action', 'auth.login')->count());
        $this->requestAs('GET', '/pengaturan/sesi', [], $remembered)->assertRedirect(route('login'));
        $this->requestAs('GET', '/pengaturan/sesi', [], $original)->assertOk();
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_intended_sessions_destination_survives_wrong_then_correct_password(): void
    {
        $user = User::factory()->create();
        $cookies = [];
        $this->requestAs('GET', '/pengaturan/sesi', [], $cookies)->assertRedirect(route('login'));
        $oldId = session()->getId();
        $oldCsrf = session()->token();
        $this->requestAs('POST', '/login', ['email' => $user->email, 'password' => 'wrong-password'], $cookies)
            ->assertSessionHasErrors('email')->assertSessionHas('url.intended', route('sessions.index'));
        $this->assertGuest('web');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.failed', 'actor_id' => null, 'subject_id' => $user->id]);
        $rejected = ActivityLog::query()->where('action', 'auth.failed')->sole();
        // Request context is captured, but the address itself is never stored.
        $this->assertNotNull($rejected->ip_hash);
        $this->assertNotSame('203.0.113.7', $rejected->ip_hash);
        $this->assertNotNull($rejected->user_agent);
        $this->assertStringNotContainsString('wrong-password', $rejected->toJson());
        $this->requestAs('POST', '/login', ['email' => $user->email, 'password' => 'password'], $cookies)
            ->assertRedirect(route('sessions.index'))->assertSessionHasNoErrors();
        $this->requestAs('GET', '/pengaturan/sesi', [], $cookies)->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame(1, ActivityLog::query()->where('action', 'auth.login')->count());
    }

    public function test_intended_internal_path_survives_wrong_password(): void
    {
        $user = User::factory()->create();
        $cookies = [];
        $this->requestAs('GET', '/login', [], $cookies)->assertOk();
        session()->put('url.intended', '/pengaturan/sesi?tab=current');
        session()->save();
        $this->requestAs('POST', '/login', ['email' => $user->email, 'password' => 'wrong-password'], $cookies)
            ->assertSessionHasErrors('email')->assertSessionHas('url.intended', '/pengaturan/sesi?tab=current');
        $this->requestAs('POST', '/login', ['email' => $user->email, 'password' => 'password'], $cookies)
            ->assertRedirect(url('/pengaturan/sesi?tab=current'));
    }

    #[DataProvider('unsafeIntendedDestinations')]
    public function test_intended_unsafe_destination_is_never_used(mixed $destination, bool $wrongFirst): void
    {
        $user = User::factory()->create();
        $cookies = [];
        $this->requestAs('GET', '/login', [], $cookies)->assertOk();
        session()->put('url.intended', $destination);
        session()->save();
        if ($wrongFirst) {
            $this->requestAs('POST', '/login', ['email' => $user->email, 'password' => 'wrong-password'], $cookies)
                ->assertSessionHasErrors('email')->assertSessionMissing('url.intended');
        }
        $this->requestAs('POST', '/login', ['email' => $user->email, 'password' => 'password'], $cookies)
            ->assertRedirect(route('petani.dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public static function unsafeIntendedDestinations(): array
    {
        return [
            'offsite direct' => ['https://evil.example/steal', false],
            'offsite after failure' => ['https://evil.example/steal', true],
            'protocol relative' => ['//evil.example/steal', false],
            'backslash authority' => ['/\\evil.example/steal', false],
            'host suffix' => ['http://localhost.evil.example/steal', false],
            'different port' => ['http://localhost:81/steal', false],
            'different scheme' => ['https://localhost/steal', false],
            'userinfo' => ['http://localhost@evil.example/steal', false],
            'script scheme' => ['javascript:alert(1)', false],
            'array' => [['https://evil.example/steal'], false],
        ];
    }

    public function test_intended_is_not_restored_when_successful_login_activity_fails(): void
    {
        $user = User::factory()->create();
        $cookies = [];
        $this->requestAs('GET', '/pengaturan/sesi', [], $cookies)->assertRedirect(route('login'));
        $oldId = session()->getId();
        $oldCsrf = session()->token();
        DB::unprepared("CREATE TEMP TRIGGER fail_explicit_login_activity BEFORE INSERT ON activity_logs WHEN NEW.action = 'auth.login' BEGIN SELECT RAISE(ABORT, 'activity unavailable'); END");
        $this->requestAs('POST', '/login', [
            'email' => $user->email, 'password' => 'password', 'remember' => true,
        ], $cookies)->assertStatus(500)->assertSessionMissing('url.intended');
        $this->assertGuest('web');
        $this->assertNull(session('account_version'));
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        $this->assertArrayNotHasKey(Auth::guard('web')->getRecallerName(), $cookies);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->requestAs('GET', '/pengaturan/sesi', [], $cookies)->assertRedirect(route('login'));
    }

    public static function resetBuckets(): array
    {
        return ['email across IPs' => [true], 'IP across emails' => [false]];
    }

    public function test_reset_submission_array_email_is_safe_and_throttled(): void
    {
        $cookies = [];
        $payload = [
            'email' => ['unknown@example.test'], 'token' => 'arbitrary-token',
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->requestAs('POST', '/reset-sandi', $payload, $cookies)->assertRedirect()->assertSessionHasErrors('email');
        }
        $this->requestAs('POST', '/reset-sandi', $payload, $cookies)->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_reset_submission_and_link_request_have_separate_budgets(): void
    {
        $cookies = [];
        $email = 'unknown@example.test';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->requestAs('POST', '/lupa-sandi', ['email' => $email], $cookies)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->requestAs('POST', '/lupa-sandi', ['email' => $email], $cookies)->assertStatus(429);
        $payload = [
            'email' => $email, 'token' => 'arbitrary-token',
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->requestAs('POST', '/reset-sandi', $payload, $cookies)->assertRedirect()->assertSessionHasErrors('email');
        }
        $this->requestAs('POST', '/reset-sandi', $payload, $cookies)->assertStatus(429);
        $this->assertDatabaseCount('activity_logs', 0);
    }
}
