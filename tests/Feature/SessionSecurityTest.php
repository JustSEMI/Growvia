<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
    }

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
            if ($cookie->getValue() === '') {
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
        $this->requestAs('POST', '/login', ['email' => $user->email, 'password' => 'password', 'remember' => $remember], $cookies)->assertRedirect();
        $this->requestAs('GET', '/petani', [], $cookies)->assertOk();

        return $cookies;
    }

    public function test_legacy_session_reappearing_after_a_revocation_still_fails_the_version_check(): void
    {
        $user = User::factory()->create();
        $owner = $this->login($user);
        $rows = DB::table('sessions')->where('user_id', $user->id)->get();
        $row = (array) $rows->first();
        $payload = json_decode(base64_decode($row['payload']), true);
        unset($payload['account_version']);
        $row['payload'] = base64_encode(json_encode($payload));
        $user->update(['password' => 'changed-password-123']);
        DB::table('sessions')->insert($row);
        $this->requestAs('GET', '/petani', [], $owner)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_email_change_rotates_this_session_and_csrf_while_revoking_other_devices(): void
    {
        $user = User::factory()->create();
        $other = $this->login($user, true);
        $owner = $this->login($user);
        $oldSessionId = session()->getId();
        $oldCsrf = session()->token();
        $this->requestAs('PATCH', '/pengaturan/profil', [
            'name' => $user->name, 'email' => 'email-session-change@example.test', 'profile_password' => 'password',
        ], $owner)->assertSessionHasNoErrors();
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        $this->requestAs('GET', '/pengaturan/sesi', [], $owner)->assertOk()->assertSee('Perangkat ini');
        $this->requestAs('GET', '/petani', [], $other)->assertRedirect(route('login'));
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_password_change_revokes_a_real_other_session_and_remember_cookie(): void
    {
        $user = User::factory()->create();
        $other = $this->login($user, true);
        $recaller = Auth::guard('web')->getRecallerName();
        $remember = [$recaller => $other[$recaller]];
        $owner = $this->login($user);
        $this->requestAs('PATCH', '/pengaturan/kata-sandi', [
            'current_password' => 'password', 'password' => 'updated-password-123', 'password_confirmation' => 'updated-password-123',
        ], $owner)->assertRedirect()->assertSessionHasNoErrors();
        $this->requestAs('GET', '/petani', [], $owner)->assertOk();
        $this->requestAs('GET', '/petani', [], $other)->assertRedirect(route('login'));
        $this->requestAs('GET', '/petani', [], $remember)->assertRedirect(route('login'));
    }

    public function test_admin_reset_and_recovery_revoke_real_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $target = $this->login($user);
        $adminJar = $this->login($admin);
        $this->requestAs('PATCH', '/pengguna/'.$user->id, [
            'name' => $user->name, 'email' => $user->email, 'role' => 'user', 'password' => 'reset-by-admin-123', 'admin_password' => 'password',
        ], $adminJar)->assertSessionHasNoErrors();
        $this->requestAs('GET', '/petani', [], $target)->assertRedirect(route('login'));

        $second = User::factory()->create();
        $old = $this->login($second, true);
        $guest = [];
        $token = Password::createToken($second);
        $this->requestAs('POST', '/reset-sandi', ['email' => $second->email, 'token' => $token, 'password' => 'reset-recovery-123', 'password_confirmation' => 'reset-recovery-123'], $guest)->assertRedirect(route('login'));
        $this->requestAs('GET', '/petani', [], $old)->assertRedirect(route('login'));
    }

    public function test_disabling_and_reenabling_cannot_restore_old_sessions_or_remember_access(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $target = $this->login($user, true);
        $remember = [Auth::guard()->getRecallerName() => $target[Auth::guard()->getRecallerName()]];
        $adminJar = $this->login($admin);
        $this->requestAs('PATCH', '/pengguna/'.$user->id.'/status', ['is_active' => '0'], $adminJar)->assertSessionHasNoErrors();
        $this->requestAs('GET', '/petani', [], $target)->assertRedirect(route('login'));
        $this->requestAs('PATCH', '/pengguna/'.$user->id.'/status', ['is_active' => '1'], $adminJar)->assertSessionHasNoErrors();
        $this->requestAs('GET', '/petani', [], $remember)->assertRedirect(route('login'));
        $newSession = $this->login($user->fresh(), true);
        unset($newSession[config('session.cookie')]);
        $this->requestAs('GET', '/petani', [], $newSession)->assertOk();
    }

    public function test_logout_others_preserves_current_cookie_and_cannot_delete_another_users_sessions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $other = $this->login($user, true);
        $owner = $this->login($user);
        $outsider = $this->login($otherUser);
        $this->requestAs('DELETE', '/pengaturan/sesi', ['sessions_password' => 'password'], $owner)->assertSessionHasNoErrors();
        $this->requestAs('GET', '/petani', [], $owner)->assertOk();
        $this->requestAs('GET', '/petani', [], $other)->assertRedirect(route('login'));
        $this->requestAs('GET', '/petani', [], $outsider)->assertOk();
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $otherUser->id)->count());
    }
}
