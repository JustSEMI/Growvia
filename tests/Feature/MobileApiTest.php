<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiTransport;
use App\Http\Middleware\ThrottleMobileApiRequests;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_versioned_json_without_sensitive_configuration(): void
    {
        $response = $this->get('/api/v1/health')->assertOk()
            ->assertJsonPath('app', 'Growvia')
            ->assertJsonPath('api_version', 'v1')
            ->assertJsonPath('status', 'ok');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertJsonMissingPath('database')->assertJsonMissingPath('app_key');
    }

    public function test_existing_account_credentials_issue_a_hashed_scoped_token_without_a_web_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('  Mobile secret  ')]);
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => '  Mobile secret  ', 'device_name' => 'Ponsel pengujian',
        ])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.is_active', true);
        $plain = $response->json('token');
        $this->assertIsString($plain);
        $stored = PersonalAccessToken::findToken($plain);
        $this->assertNotNull($stored);
        $this->assertNotSame($plain, $stored->token);
        $this->assertSame(['mobile:access'], $stored->abilities);
        $this->assertTrue($stored->expires_at->isFuture());
        $this->assertSame((string) $user->auth_version, (string) $stored->account_version);
        $this->assertSame(['id', 'name', 'email', 'role', 'is_active'], array_keys($response->json('user')));
        $this->assertEmpty($response->headers->getCookies());
        $this->assertGuest('web');
        $this->assertSame(1, ActivityLog::query()->where('action', 'auth.login')->count());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_bearer_me_and_deferred_readings_use_the_existing_account_then_logout_revokes_only_that_token(): void
    {
        $user = User::factory()->create();
        $first = $this->loginToken($user);
        $second = $this->loginToken($user);
        $this->api('GET', '/api/v1/auth/me', $first)->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('user.password');
        $this->api('GET', '/api/v1/readings', $first)->assertOk()->assertExactJson([
            'data' => [],
            'meta' => ['available' => false, 'reason' => 'Integrasi sensor belum aktif. Belum ada riwayat pembacaan.', 'source' => 'rest_api'],
        ]);
        $this->api('POST', '/api/v1/auth/logout', $first)->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($first));
        $this->api('GET', '/api/v1/auth/me', $first)->assertUnauthorized();
        $this->api('GET', '/api/v1/auth/me', $second)->assertOk();
    }

    public function test_missing_malformed_or_unscoped_bearer_tokens_do_not_use_web_authentication(): void
    {
        foreach (['/api/v1/auth/me', '/api/v1/readings'] as $url) {
            $this->api('GET', $url)->assertUnauthorized();
            $this->api('GET', $url, 'not-a-valid-token')->assertUnauthorized();
        }
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web')->getJson('/api/v1/auth/me')->assertUnauthorized();
        $token = $admin->createToken('Unscoped', ['other:read'])->plainTextToken;
        $this->api('GET', '/api/v1/auth/me', $token)->assertForbidden();
    }

    public function test_account_security_changes_revoke_mobile_tokens_and_profile_name_edits_do_not(): void
    {
        $mutations = [
            ['password' => Hash::make('new-password-123')],
            ['email' => 'mobile-changed@example.test'],
            ['role' => 'admin'],
            ['is_active' => false],
            ['auth_version' => str_repeat('v', 40)],
        ];
        foreach ($mutations as $changes) {
            $user = User::factory()->create();
            $plain = $this->loginToken($user);
            $user->forceFill(['name' => 'Nama diperbarui'])->save();
            $this->api('GET', '/api/v1/auth/me', $plain)->assertOk();
            $user->forceFill($changes)->save();
            $this->assertNull(PersonalAccessToken::findToken($plain));
            $this->api('GET', '/api/v1/auth/me', $plain)->assertUnauthorized();
        }
    }

    public function test_deleted_accounts_and_expired_tokens_cannot_authenticate(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);
        PersonalAccessToken::findToken($token)->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->api('GET', '/api/v1/auth/me', $token)->assertUnauthorized();
        $other = $this->loginToken($user);
        $user->delete();
        $this->assertNull(PersonalAccessToken::findToken($other));
        $this->api('GET', '/api/v1/auth/me', $other)->assertUnauthorized();
    }

    public function test_inactive_missing_and_wrong_password_accounts_receive_the_same_response(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $messages = [];
        foreach ([[$active->email, 'wrong'], [$inactive->email, 'password'], ['unknown@example.test', 'password']] as [$email, $password]) {
            $messages[] = $this->api('POST', '/api/v1/auth/login', data: ['email' => $email, 'password' => $password, 'device_name' => 'Ponsel uji'])
                ->assertUnauthorized()->json();
        }
        $this->assertSame($messages[0], $messages[1]);
        $this->assertSame($messages[1], $messages[2]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        // One audit row per rejected attempt, none of them carrying credentials.
        $this->assertSame(3, ActivityLog::query()->where('action', 'auth.failed')->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'auth.login')->count());
        $this->assertSame(['', '', ''], ActivityLog::query()->orderBy('id')->get()
            ->map(fn (ActivityLog $log): string => implode(',', $log->fields))->all());
        $this->assertStringNotContainsString('unknown@example.test', ActivityLog::all()->toJson());
        $this->assertStringNotContainsString('wrong', ActivityLog::all()->toJson());
    }

    public function test_audit_failure_rolls_back_token_creation_but_never_prevents_logout(): void
    {
        $user = User::factory()->create();
        DB::unprepared("CREATE TEMP TRIGGER fail_mobile_login BEFORE INSERT ON activity_logs WHEN NEW.action = 'auth.login' BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->api('POST', '/api/v1/auth/login', data: ['email' => $user->email, 'password' => 'password', 'device_name' => 'Ponsel uji'])->assertStatus(500);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        DB::unprepared('DROP TRIGGER fail_mobile_login');
        $token = $this->loginToken($user);
        DB::unprepared("CREATE TEMP TRIGGER fail_mobile_logout BEFORE INSERT ON activity_logs WHEN NEW.action = 'auth.logout' BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->api('POST', '/api/v1/auth/logout', $token)->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_login_rate_limit_applies_before_credential_checks(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->api('POST', '/api/v1/auth/login', data: ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Ponsel uji'])->assertUnauthorized();
        }
        $this->api('POST', '/api/v1/auth/login', data: ['email' => $user->email, 'password' => 'password', 'device_name' => 'Ponsel uji'])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_production_http_is_refused_and_local_http_does_not_require_https(): void
    {
        $this->app->instance('env', 'production');
        $this->getJson('http://localhost/api/v1/health')->assertStatus(426)->assertJsonPath('message', 'HTTPS wajib digunakan untuk API.');
        $this->getJson('https://growvia.example.test/api/v1/health')->assertOk();
        $this->app->instance('env', 'local');
        $this->getJson('http://127.0.0.1/api/v1/health')->assertOk();
    }

    public function test_cors_only_allows_local_browser_origins_without_cookie_credentials(): void
    {
        $this->withHeader('Origin', 'http://127.0.0.1:8150')->getJson('/api/v1/health')->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:8150');
        $this->withHeader('Origin', 'https://untrusted.example')->getJson('/api/v1/health')->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_validation_is_json_indonesian_and_password_is_never_echoed(): void
    {
        $this->api('POST', '/api/v1/auth/login', data: ['email' => ['bad'], 'password' => ['bad'], 'device_name' => ['bad']])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'device_name']);
        $this->api('POST', '/api/v1/auth/login')->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Kolom alamat email wajib diisi.');
    }

    public function test_review_invalid_bearers_are_ip_limited_before_authentication(): void
    {
        config(['app.debug' => true]);
        $this->freezeTime();
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->api('GET', '/api/v1/auth/me', 'invalid-token')->assertUnauthorized();
        }
        $response = $this->api('GET', '/api/v1/auth/me', 'another-invalid-token')
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(['message'], array_keys($response->json()));
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->getJson('https://growvia.example.test/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_review_successful_requests_are_not_double_counted(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->getJson('/api/v1/health')->assertOk();
        }
        $this->getJson('/api/v1/health')->assertStatus(429);
    }

    public function test_review_debug_not_found_and_method_errors_keep_only_safe_payloads(): void
    {
        config(['app.debug' => true]);
        $missing = $this->getJson('/api/v1/review-missing')->assertNotFound();
        $method = $this->postJson('/api/v1/health')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD');
        foreach ([$missing, $method] as $response) {
            $this->assertSame(['message'], array_keys($response->json()));
            $this->assertStringNotContainsString(base_path(), $response->getContent());
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_review_debug_forbidden_and_unauthorized_keep_safe_messages_and_headers(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/review-forbidden', fn () => abort(403, 'Akses ditolak.'));
        Route::get('/api/review-challenge', fn () => abort(401, 'Token diperlukan.', ['WWW-Authenticate' => 'Bearer']));
        $this->getJson('/api/review-forbidden')->assertForbidden()->assertExactJson(['message' => 'Akses ditolak.']);
        $this->getJson('/api/review-challenge')->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer')->assertExactJson(['message' => 'Token diperlukan.']);
    }

    public function test_review_debug_validation_keeps_field_errors(): void
    {
        config(['app.debug' => true]);
        $response = $this->postJson('/api/v1/auth/login')->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'device_name'])
            ->assertJsonPath('errors.email.0', 'Kolom alamat email wajib diisi.');
        $this->assertEqualsCanonicalizing(['message', 'errors'], array_keys($response->json()));
    }

    public function test_review_debug_maintenance_preserves_retry_after(): void
    {
        config(['app.debug' => true]);
        $maintenance = $this->mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(true);
        $maintenance->shouldReceive('data')->andReturn(['status' => 503, 'retry' => 75]);
        $this->getJson('/api/v1/health')->assertStatus(503)->assertHeader('Retry-After', '75')
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Layanan sementara tidak tersedia. Coba kembali nanti.']);
    }

    public function test_review_server_errors_discard_debug_and_stale_representation_headers(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/review-unavailable', fn () => abort(503, 'Private '.base_path(), [
            'Retry-After' => '90', 'Content-Length' => '99999', 'Content-Type' => 'text/plain',
        ]));
        $response = $this->getJson('/api/review-unavailable')->assertStatus(503)
            ->assertHeader('Retry-After', '90')->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Layanan sementara tidak tersedia. Coba kembali nanti.']);
        $length = $response->headers->get('Content-Length');
        $this->assertTrue($length === null || (int) $length === strlen($response->getContent()));
    }

    public function test_review_explicit_trusted_proxy_https_and_preflight_are_accepted(): void
    {
        $this->app->instance('env', 'production');
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        Request::setTrustedProxies([], 0);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeader('X-Forwarded-Proto', 'https');
        $this->getJson('http://growvia.example.test/api/v1/health')->assertOk();
        $this->options('http://growvia.example.test/api/v1/health', [], [
            'Origin' => 'http://127.0.0.1:8150', 'Access-Control-Request-Method' => 'GET',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:8150');
    }

    public function test_review_untrusted_forwarded_https_and_insecure_preflight_are_rejected(): void
    {
        $this->app->instance('env', 'production');
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        foreach (['192.0.2.11', '192.0.2.10'] as $ip) {
            $headers = ['X-Forwarded-Proto' => $ip === '192.0.2.11' ? 'https' : 'http'];
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders($headers);
            $this->getJson('http://growvia.example.test/api/v1/health')->assertStatus(426);
            $this->options('http://growvia.example.test/api/v1/health', [], [
                'Origin' => 'http://127.0.0.1:8150', 'Access-Control-Request-Method' => 'GET',
            ])->assertStatus(426)->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    public function test_review_middleware_order_preserves_web_authentication_priority(): void
    {
        $router = $this->app['router'];
        $mobile = $router->gatherRouteMiddleware($router->getRoutes()->getByName('api.v1.auth.me'));
        $limiter = array_search(ThrottleMobileApiRequests::class.':mobile-api', $mobile, true);
        $this->assertNotFalse($limiter);
        $this->assertLessThan(array_search(Authenticate::class.':sanctum', $mobile, true), $limiter);
        $this->assertNotContains(ThrottleRequests::class.':mobile-api', $mobile);
        $route = Route::get('/review-web-order', fn () => 'ok')
            ->middleware(['web', 'throttle:6,1', 'auth']);
        $resolved = $router->gatherRouteMiddleware($route);
        $this->assertLessThan(
            array_search(ThrottleRequests::class.':6,1', $resolved, true),
            array_search(Authenticate::class, $resolved, true),
        );
        $stack = $this->app->make(Kernel::class)->getGlobalMiddleware();
        $transport = array_search(EnsureApiTransport::class, $stack, true);
        $this->assertLessThan($transport, array_search(TrustProxies::class, $stack, true));
        $this->assertGreaterThan($transport, array_search(HandleCors::class, $stack, true));
    }

    private function loginToken(User $user): string
    {
        return $this->api('POST', '/api/v1/auth/login', data: ['email' => $user->email, 'password' => 'password', 'device_name' => 'Ponsel uji'])
            ->assertOk()->json('token');
    }

    private function api(string $method, string $url, ?string $token = null, array $data = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');

        return $this->json($method, $url, $data, $token === null ? [] : ['Authorization' => 'Bearer '.$token]);
    }
}
