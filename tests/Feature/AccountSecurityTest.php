<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_accounts_cannot_log_in_or_use_an_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get(route('petani.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unchanged_email_needs_no_password_but_a_new_email_requires_valid_reauthentication(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('settings.profile'), ['name' => 'Nama baru', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->patch(route('settings.profile'), ['name' => $user->name, 'email' => 'changed@example.test', 'profile_password' => 'not-correct'])->assertSessionHasErrors('profile_password');
        $this->assertNotSame('changed@example.test', $user->fresh()->email);
        $this->assertArrayNotHasKey('profile_password', session()->getOldInput());
    }

    public function test_password_changes_revoke_outstanding_recovery_links_and_legacy_sessions(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->actingAs($user)->patch(route('settings.password'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->assertNotEmpty($user->auth_version);
        $this->get(route('petani.dashboard'))->assertOk();
        session()->forget('account_version');
        $this->get(route('petani.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_security_changes_rotate_the_session_version_and_revoke_tokens_for_old_email(): void
    {
        $user = User::factory()->create();
        $oldEmail = $user->email;
        $token = Password::createToken($user);
        $originalVersion = (string) $user->auth_version;
        $this->actingAs($user)->patch(route('settings.profile'), [
            'name' => $user->name, 'email' => 'verified-change@example.test', 'profile_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->assertSame('verified-change@example.test', $user->fresh()->email);
        $this->assertNotSame($originalVersion, (string) $user->fresh()->auth_version);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_admin_sensitive_edits_require_reauthentication_even_for_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('users.update', $admin), [
            'name' => $admin->name, 'email' => 'admin-changed@example.test', 'role' => 'admin',
        ])->assertSessionHasErrors('admin_password');
        $this->assertNotSame('admin-changed@example.test', $admin->fresh()->email);
    }

    public function test_session_list_only_exposes_the_signed_in_users_devices_without_session_secrets(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'own-session-secret', 'user_id' => $user->id, 'ip_address' => '192.0.2.10', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/140.0', 'payload' => 'private-payload', 'last_activity' => now()->timestamp],
            ['id' => 'someone-elses-session', 'user_id' => $other->id, 'ip_address' => '192.0.2.20', 'user_agent' => 'Other browser', 'payload' => 'other-secret', 'last_activity' => now()->timestamp],
        ]);
        $this->actingAs($user)->get('/pengaturan/sesi')->assertOk()
            ->assertSee('192.0.2.10')->assertDontSee('192.0.2.20')
            ->assertDontSee('own-session-secret')->assertDontSee('private-payload')->assertDontSee('someone-elses-session');
    }

    public function test_other_sessions_require_password_then_revoke_remember_tokens_and_keep_this_session(): void
    {
        $user = User::factory()->create();
        $remember = $user->remember_token;
        $this->actingAs($user)->delete('/pengaturan/sesi')->assertSessionHasErrors('sessions_password');
        $this->delete('/pengaturan/sesi', ['sessions_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNotSame($remember, $user->fresh()->remember_token);
        $this->assertNotEmpty($user->fresh()->auth_version);
        $this->get(route('petani.dashboard'))->assertOk();
        session()->put('account_version', 'stale-session-version');
        $this->get(route('petani.dashboard'))->assertRedirect(route('login'));
    }

    public function test_recovery_responses_do_not_reveal_unknown_inactive_or_throttled_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        $message = session('status');
        foreach ([$user->email, 'unknown@example.test', $inactive->email] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertSessionHasNoErrors()->assertSessionHas('status', $message);
        }
        Notification::assertNotSentTo($inactive, ResetPassword::class);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_recovery_links_use_the_configured_origin_and_malformed_email_never_renders_raw_arrays(): void
    {
        Notification::fake();
        config(['app.url' => 'https://growvia.example.test']);
        $user = User::factory()->create();
        $this->withServerVariables(['HTTP_HOST' => 'attacker.invalid'])->post(route('password.email', [], false), ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertSame('growvia.example.test', parse_url($url, PHP_URL_HOST));
            $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));

            return true;
        });
        $this->get('/reset-sandi/fixture?email[]=invalid')->assertStatus(422);
    }

    public function test_deleting_or_disabling_accounts_invalidates_their_recovery_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $email = $user->email;
        $oldToken = Password::createToken($user);
        $this->actingAs($admin)->delete(route('users.destroy', $user))->assertRedirect();
        $replacement = User::factory()->create(['email' => $email]);
        $this->assertFalse(Password::tokenExists($replacement, $oldToken));
        $token = Password::createToken($replacement);
        $this->patch(route('users.status', $replacement), ['is_active' => false])->assertRedirect();
        $this->assertFalse(Password::tokenExists($replacement, $token));
    }

    public function test_recovery_has_an_ip_rate_limit_and_will_not_reset_an_inactive_account(): void
    {
        Notification::fake();
        $inactive = User::factory()->create(['is_active' => false]);
        $token = Password::createToken($inactive);
        $before = $inactive->password;
        $this->post(route('password.update'), ['email' => $inactive->email, 'token' => $token, 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'])->assertSessionHasErrors('email');
        $this->assertSame($before, $inactive->fresh()->password);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'unknown-'.$attempt.'@example.test'])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'another@example.test'])->assertStatus(429);
    }

    public function test_recovery_reset_rotates_the_version_and_consumes_token_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $before = (string) $user->auth_version;
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'fresh-password-123', 'password_confirmation' => 'fresh-password-123'];
        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $this->assertNotSame($before, (string) $user->fresh()->auth_version);
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
    }

    public function test_recovery_failure_rolls_back_password_and_version_instead_of_partially_changing_credentials(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $oldPassword = 'password';
        User::updated(function (User $changed): void {
            if ($changed->wasChanged('password')) {
                throw new \RuntimeException('Forced transaction failure');
            }
        });
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'new-failing-123', 'password_confirmation' => 'new-failing-123'])->assertStatus(500);
        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_current_password_fields_and_session_link_render_in_both_account_panels(): void
    {
        foreach ([false, true] as $isAdmin) {
            $user = User::factory()->create(['role' => $isAdmin ? 'admin' : 'user']);
            $route = $isAdmin ? 'settings.index' : 'petani.settings';
            $response = $this->actingAs($user)->get(route($route))->assertOk();
            $this->assertStringContainsString('name="profile_password"', $response->getContent());
            $this->assertStringContainsString(route('sessions.index'), $response->getContent());
            $this->assertStringNotContainsString('value="password"', $response->getContent());
        }
    }

    public function test_security_version_never_leaks_or_changes_for_a_name_only_update(): void
    {
        $user = User::factory()->create();
        $user->update(['email' => 'changed-for-version@example.test']);
        $version = $user->auth_version;
        $user->update(['name' => 'Nama baru']);
        $this->assertSame($version, $user->auth_version);
        $this->assertArrayNotHasKey('auth_version', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_creating_an_account_does_not_inherit_an_orphaned_recovery_token(): void
    {
        $old = User::factory()->create();
        $token = Password::createToken($old);
        $email = $old->email;
        DB::table('users')->where('id', $old->id)->delete();
        $replacement = User::factory()->create(['email' => $email]);
        $this->assertFalse(Password::tokenExists($replacement, $token));
    }

    public function test_authenticated_petani_visiting_login_returns_to_their_own_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('login'))->assertRedirect(route('petani.dashboard'));
    }

    public function test_stale_versions_fail_closed_for_json_and_do_not_rotate_current_remember_credentials(): void
    {
        $user = User::factory()->create();
        $oldRemember = $user->remember_token;
        $user->forceFill(['auth_version' => 'current-version'])->save();
        $this->actingAs($user)->withSession(['account_version' => 'stale-version'])->getJson(route('petani.dashboard'))->assertUnauthorized();
        $this->assertSame($oldRemember, $user->fresh()->remember_token);
    }

    public function test_failed_reauthentication_never_flashes_custom_password_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('settings.profile'), [
            'name' => $user->name, 'email' => 'safe-change@example.test', 'profile_password' => 'private-failed-secret',
        ])->assertSessionHasErrors('profile_password')->assertSessionMissing('_old_input.profile_password');
        $this->delete(route('sessions.destroy'), ['sessions_password' => 'private-failed-secret'])
            ->assertSessionHasErrors('sessions_password')->assertSessionMissing('_old_input.sessions_password');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name' => $user->name, 'email' => 'safe-admin-change@example.test', 'role' => 'user', 'admin_password' => 'private-failed-secret',
        ])->assertSessionHasErrors('admin_password')->assertSessionMissing('_old_input.admin_password');
    }

    public function test_custom_reauthentication_fields_preserve_password_whitespace(): void
    {
        $user = User::factory()->create(['password' => '  fixture-spaced-password  ']);
        $this->actingAs($user)->patch(route('settings.profile'), [
            'name' => $user->name, 'email' => 'changed-spaced@example.test', 'profile_password' => '  fixture-spaced-password  ',
        ])->assertSessionHasNoErrors();
        $this->assertSame('changed-spaced@example.test', $user->fresh()->email);
    }

    public function test_recovery_email_limit_is_independent_of_the_request_ip(): void
    {
        Notification::fake();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])->post(route('password.email'), ['email' => 'same-unknown@example.test'])->assertRedirect();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->post(route('password.email'), ['email' => 'SAME-UNKNOWN@example.test'])->assertStatus(429);
    }

    public function test_mail_delivery_failure_has_the_same_public_recovery_response(): void
    {
        $user = User::factory()->create();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('private-mail-error'));
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status');
        $message = session('status');
        $this->post(route('password.email'), ['email' => 'unknown-mail@example.test'])->assertRedirect()->assertSessionHas('status', $message);
    }

    public function test_session_pagination_labels_count_sessions_not_people(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        foreach (range(1, 16) as $index) {
            DB::table('sessions')->insert(['id' => str_pad((string) $index, 40, 's'), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }
        $this->actingAs($user)->get(route('sessions.index'))->assertOk()->assertSee('dari 16 sesi')->assertDontSee('dari 16 pengguna');
    }

    public function test_recovery_token_issuance_holds_a_database_transaction_until_the_token_is_stored(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $level = null;
        $this->app['db']->connection()->beforeExecuting(function (string $query) use (&$level): void {
            if (str_starts_with(strtolower($query), 'insert into "password_reset_tokens"')) {
                $level = $this->app['db']->connection()->transactionLevel();
            }
        });
        $before = $this->app['db']->connection()->transactionLevel();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame($before + 1, $level);
    }

    public function test_changing_recovery_email_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        $oldEmail = $user->email;

        $this->actingAs($user)->patch(route('settings.profile'), [
            'name' => $user->name,
            'email' => 'changed@example.test',
        ])->assertSessionHasErrors('profile_password');

        $this->assertSame($oldEmail, $user->fresh()->email);
    }
}
