<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\MobileSettings;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_settings_and_activity_are_role_scoped_allowlists(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        User::factory()->create(['is_active' => false]);
        Setting::query()->create(['key' => 'mqtt_host', 'value' => 'private-broker']);
        Setting::query()->create(['key' => 'update_interval', 'value' => '20']);
        ActivityLog::record('user.created', $admin, $member, ['email', 'password', 'mqtt_host']);
        $this->api('GET', 'workspace')->assertUnauthorized();
        $token = $this->token($member);
        $memberView = $this->api('GET', 'workspace', $token)->assertOk()
            ->assertJsonPath('counts', null)->assertJsonPath('recent_activities', [])
            ->assertJsonPath('telemetry_available', false)->assertJsonPath('settings.update_interval', '20');
        $this->assertCount(count(array_merge(...array_values(MobileSettings::SECTIONS))), $memberView->json('settings'));
        foreach (['settings', 'activities'] as $url) {
            $this->api('GET', $url)->assertUnauthorized();
            $this->api('GET', $url, $token)->assertForbidden();
        }
        $token = $this->token($admin);
        $response = $this->api('GET', 'workspace', $token)->assertOk()
            ->assertJsonPath('counts', ['users_total' => 3, 'users_active' => 2, 'users_inactive' => 1, 'admins' => 1])
            ->assertJsonPath('recent_activities.0.fields', ['Email']);
        $this->assertStringNotContainsString('mqtt', $response->getContent());
        $this->assertStringNotContainsString('private-broker', $response->getContent());
        $this->api('GET', 'settings', $token)->assertOk()->assertJsonPath('settings.pump_mode', 'auto');
        $this->api('GET', 'activities', $token)->assertOk()->assertJsonPath('timezone', 'UTC')
            ->assertJsonPath('meta.per_page', 15)->assertJsonPath('data.0.label', 'Akun dibuat')
            ->assertJsonMissingPath('data.0.actor_id')->assertJsonMissingPath('data.0.subject_id');
    }

    public function test_settings_sections_validate_merge_audit_and_never_touch_mqtt(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);
        Setting::query()->create(['key' => 'mqtt_host', 'value' => 'untouched']);
        $before = DB::table('settings')->where('key', 'mqtt_host')->first();
        $this->api('PATCH', 'settings')->assertUnauthorized();
        $this->api('PATCH', 'settings', $this->token(User::factory()->create()))->assertForbidden();
        foreach ([
            ['general', ['update_interval' => 30]],
            ['climate', ['th_suhu_max' => 35]],
            ['soil', ['pump_mode' => 'manual']],
            ['notifications', ['notif_pompa' => false, 'notif_cahaya' => 1]],
        ] as [$section, $values]) {
            $this->api('PATCH', 'settings', $token, compact('section', 'values'))->assertOk();
        }
        $this->api('GET', 'settings', $token)->assertJsonPath('settings.notif_pompa', '0')
            ->assertJsonPath('settings.notif_tandon', '1')->assertJsonPath('settings.th_suhu_min', '25');
        foreach ([
            ['section' => 'general', 'values' => ['mqtt_host' => 'bad']],
            ['section' => 'general', 'values' => ['soil_on' => 20]],
            ['section' => 'climate', 'values' => ['th_suhu_min' => 99]],
            ['section' => 'soil', 'values' => ['soil_on' => 90]],
            ['section' => 'notifications', 'values' => ['notif_pompa' => 'yes']],
            ['section' => 'general', 'values' => ['update_interval' => 4]],
            ['section' => 'unknown', 'values' => []],
            ['section' => 'general', 'values' => [], 'extra' => true],
        ] as $payload) {
            $this->api('PATCH', 'settings', $token, $payload)->assertUnprocessable();
        }
        $this->assertEquals($before, DB::table('settings')->where('key', 'mqtt_host')->first());
        $this->assertDatabaseCount('activity_logs', 4);
        DB::unprepared("CREATE TEMP TRIGGER parity_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit down'); END");
        $this->api('PATCH', 'settings', $token, ['section' => 'general', 'values' => ['update_interval' => 40]])->assertStatus(500);
        $this->assertDatabaseHas('settings', ['key' => 'update_interval', 'value' => '30']);
    }

    public function test_activity_filters_pagination_and_invalid_inputs(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);
        ActivityLog::factory()->count(17)->create(['action' => 'user.created', 'actor_name' => 'Selected', 'created_at' => '2026-01-02 12:00:00']);
        ActivityLog::factory()->create(['action' => 'auth.login', 'actor_name' => 'Other', 'created_at' => '2026-01-03 12:00:00']);
        $this->api('GET', 'activities?q=Selected&action=user.created&from=2026-01-02&to=2026-01-02&page=2', $token)
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta', ['current_page' => 2, 'last_page' => 2, 'per_page' => 15, 'total' => 17]);
        foreach (['page=0', 'q[]=bad', 'action=no', 'from=2026-02-30', 'from=2026-02-02&to=2026-01-01', 'per_page=100'] as $query) {
            $this->api('GET', 'activities?'.$query, $token)->assertUnprocessable();
        }
    }

    public function test_users_crud_filters_validation_and_self_protection(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);
        $member = User::factory()->create();
        $memberToken = $this->token($member);
        foreach ([['GET', 'users'], ['POST', 'users'], ['PATCH', 'users/'.$member->id], ['PATCH', 'users/'.$member->id.'/status'], ['DELETE', 'users/'.$member->id]] as [$method, $url]) {
            $this->api($method, $url)->assertUnauthorized();
            $this->api($method, $url, $memberToken)->assertForbidden();
        }
        $payload = ['name' => 'Created', 'email' => 'created@example.test', 'password' => '  secret password  ', 'role' => 'user'];
        $created = $this->api('POST', 'users', $token, $payload)->assertCreated()->json('user');
        $this->assertTrue(Hash::check($payload['password'], User::find($created['id'])->password));
        $this->api('POST', 'users', $token, $payload)->assertUnprocessable();
        $this->api('POST', 'users', $token, $payload + ['is_active' => false])->assertUnprocessable();
        $this->api('PATCH', 'users/'.$created['id'], $token, ['name' => 'Changed', 'email' => 'changed@example.test', 'role' => 'user'])->assertUnprocessable()->assertJsonValidationErrors('admin_password');
        $this->api('PATCH', 'users/'.$created['id'], $token, ['name' => 'Changed', 'email' => 'changed@example.test', 'role' => 'user', 'admin_password' => 'password'])->assertOk()->assertJsonPath('user.name', 'Changed')->assertJsonMissingPath('token');
        $this->api('PATCH', 'users/'.$created['id'].'/status', $token, ['is_active' => false])->assertOk()->assertJsonPath('user.is_active', false);
        $this->api('GET', 'users?q=changed&role=user&status=inactive&sort=name_asc', $token)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 15);
        foreach (['role=root', 'status=bad', 'sort=bad', 'q[]=bad', 'page=0', 'per_page=100'] as $query) {
            $this->api('GET', 'users?'.$query, $token)->assertUnprocessable();
        }
        $this->api('PATCH', 'users/'.$admin->id, $token, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'user'])->assertUnprocessable();
        $this->api('PATCH', 'users/'.$admin->id.'/status', $token, ['is_active' => false])->assertUnprocessable();
        $this->api('DELETE', 'users/'.$admin->id, $token)->assertUnprocessable();
        $this->api('DELETE', 'users/'.$created['id'], $token)->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $created['id']]);
        User::factory()->count(16)->create();
        $this->api('GET', 'users?sort=oldest&page=2', $token)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 18);
    }

    public function test_profile_password_and_admin_self_edits_rotate_only_when_sensitive(): void
    {
        $user = User::factory()->admin()->create(['password' => Hash::make('  old password  ')]);
        $token = $this->token($user);
        foreach (['account/profile', 'account/password'] as $url) {
            $this->api('PATCH', $url)->assertUnauthorized();
        }
        $this->api('PATCH', 'account/profile', $token, ['name' => 'New name', 'email' => $user->email])->assertOk()->assertJsonMissingPath('token');
        $this->api('GET', 'auth/me', $token)->assertOk()->assertJsonPath('user.name', 'New name');
        $this->api('PATCH', 'account/profile', $token, ['name' => 'Bad', 'email' => 'new@example.test', 'current_password' => 'wrong'])->assertUnprocessable();
        $other = $this->token($user);
        Password::createToken($user);
        $response = $this->api('PATCH', 'account/profile', $token, ['name' => 'New name', 'email' => 'new@example.test', 'current_password' => '  old password  '])->assertOk();
        $next = $response->json('token');
        $this->assertIsString($next);
        $this->api('GET', 'auth/me', $token)->assertUnauthorized();
        $this->api('GET', 'auth/me', $other)->assertUnauthorized();
        $this->api('GET', 'auth/me', $next)->assertOk()->assertJsonPath('user.email', 'new@example.test');
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->api('PATCH', 'account/password', $next, ['current_password' => '  old password  ', 'password' => '  new password  ', 'password_confirmation' => 'different'])->assertUnprocessable();
        $response = $this->api('PATCH', 'account/password', $next, ['current_password' => '  old password  ', 'password' => '  new password  ', 'password_confirmation' => '  new password  '])->assertOk();
        $last = $response->json('token');
        $this->assertTrue(Hash::check('  new password  ', $user->fresh()->password));
        $this->api('GET', 'auth/me', $next)->assertUnauthorized();
        $this->api('GET', 'auth/me', $last)->assertOk();
        $this->travel(61)->seconds();
        $response = $this->api('PATCH', 'users/'.$user->id, $last, ['name' => 'Admin', 'email' => 'admin-new@example.test', 'role' => 'admin', 'admin_password' => '  new password  '])->assertOk();
        $this->api('GET', 'auth/me', $response->json('token'))->assertOk()->assertJsonPath('user.email', 'admin-new@example.test');
        $this->api('GET', 'auth/me', $last)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[DataProvider('mutations')]
    public function test_all_mutations_recheck_deleted_expired_or_changed_actor_inside_transaction(string $action, string $race): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $token = $this->token($admin);
        [$method, $url, $payload] = $this->mutation($action, $admin, $target);
        $fired = false;
        DB::connection()->beforeStartingTransaction(function () use (&$fired, $race, $token, $admin): void {
            if (! $fired) {
                $fired = true;
                match ($race) {
                    'deleted' => PersonalAccessToken::findToken($token)->delete(),
                    'expired' => PersonalAccessToken::findToken($token)->forceFill(['expires_at' => now()->subSecond()])->save(),
                    'role' => DB::table('users')->where('id', $admin->id)->update(['role' => 'user']),
                    'inactive' => DB::table('users')->where('id', $admin->id)->update(['is_active' => false]),
                    'version' => DB::table('users')->where('id', $admin->id)->update(['auth_version' => 'revoked']),
                };
            }
        });
        $this->api($method, $url, $token, $payload)->assertStatus($race === 'role' ? 403 : 401);
        $this->assertTrue($fired);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('settings', 0);
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertTrue($target->fresh()->is_active);
    }

    public static function mutations(): array
    {
        $cases = [];
        foreach (['create', 'edit', 'status', 'delete', 'settings', 'profile', 'password', 'revoke'] as $action) {
            foreach (['deleted', 'expired', 'inactive', 'version', 'role'] as $race) {
                if ($race !== 'role' || in_array($action, ['create', 'edit', 'status', 'delete', 'settings'], true)) {
                    $cases[$action.'-'.$race] = [$action, $race];
                }
            }
        }

        return $cases;
    }

    private function mutation(string $action, User $admin, User $target): array
    {
        return match ($action) {
            'create' => ['POST', 'users', ['name' => 'Created', 'email' => 'created@example.test', 'role' => 'user', 'password' => 'password123']],
            'edit' => ['PATCH', 'users/'.$target->id, ['name' => 'Edited', 'email' => $target->email, 'role' => 'user']],
            'status' => ['PATCH', 'users/'.$target->id.'/status', ['is_active' => false]],
            'delete' => ['DELETE', 'users/'.$target->id, []],
            'settings' => ['PATCH', 'settings', ['section' => 'general', 'values' => ['update_interval' => 25]]],
            'profile' => ['PATCH', 'account/profile', ['name' => 'Edited', 'email' => $admin->email]],
            'password' => ['PATCH', 'account/password', ['current_password' => 'password', 'password' => 'newpassword', 'password_confirmation' => 'newpassword']],
            'revoke' => ['POST', 'account/sessions/revoke-others', ['current_password' => 'password']],
        };
    }

    public function test_sessions_paginate_only_own_valid_devices_and_revoke_others_rotates(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user, 'Current phone');
        for ($i = 0; $i < 16; $i++) {
            $this->token($user, 'Other phone '.$i);
        }
        $this->token(User::factory()->create(), 'Private other account');
        $expired = $this->token($user, 'Expired phone');
        PersonalAccessToken::findToken($expired)->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->api('GET', 'account/sessions')->assertUnauthorized();
        $this->api('POST', 'account/sessions/revoke-others')->assertUnauthorized();
        $response = $this->api('GET', 'account/sessions', $token)->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17)->assertJsonPath('web_supported', false)
            ->assertJsonPath('data.0.current', true)->assertJsonPath('data.0.label', 'Current phone');
        $this->assertSame(['kind', 'label', 'device', 'ip', 'current', 'last_active', 'expires_at'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString('Private other account', $response->getContent());
        $this->assertStringNotContainsString(PersonalAccessToken::findToken($token)->token, $response->getContent());
        $this->api('GET', 'account/sessions?page=2', $token)->assertOk()->assertJsonCount(2, 'data');
        $this->api('GET', 'account/sessions?page=0', $token)->assertUnprocessable();
        $this->api('GET', 'account/sessions?user_id=1', $token)->assertUnprocessable();
        $this->api('POST', 'account/sessions/revoke-others', $token, ['current_password' => 'wrong'])->assertUnprocessable();
        $oldVersion = $user->auth_version;
        $recovery = Password::createToken($user);
        $response = $this->api('POST', 'account/sessions/revoke-others', $token, ['current_password' => 'password'])->assertOk();
        $next = $response->json('token');
        $this->assertNotSame($oldVersion, $user->fresh()->auth_version);
        $this->assertNotSame($user->remember_token, $user->fresh()->remember_token);
        $this->assertFalse(Password::tokenExists($user, $recovery));
        $this->api('GET', 'auth/me', $token)->assertUnauthorized();
        $this->api('GET', 'account/sessions', $next)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.current', true);
        $this->assertTrue(PersonalAccessToken::findToken($next)->expires_at->isFuture());
    }

    public function test_web_sessions_on_separate_connection_are_private_and_rollback_with_audit_failure(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        config(['database.connections.parity_sessions' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'session.driver' => 'database', 'session.connection' => 'parity_sessions']);
        $db = DB::connection('parity_sessions');
        $db->getSchemaBuilder()->create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->integer('user_id');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity');
        });
        $db->table('sessions')->insert([
            ['id' => 'private-session-id', 'user_id' => $user->id, 'ip_address' => '192.0.2.1', 'user_agent' => 'Mozilla Windows Chrome/100', 'payload' => 'private-payload', 'last_activity' => now()->timestamp],
            ['id' => 'unrelated-session', 'user_id' => 999, 'ip_address' => '192.0.2.2', 'user_agent' => 'Other', 'payload' => 'private-other', 'last_activity' => now()->timestamp],
        ]);
        $response = $this->api('GET', 'account/sessions', $token)->assertOk()->assertJsonPath('web_supported', true)->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.1.kind', 'web')->assertJsonPath('data.1.device', 'Windows')->assertJsonPath('data.1.current', false);
        foreach (['private-session-id', 'private-payload', 'unrelated-session', '192.0.2.2'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        DB::unprepared("CREATE TEMP TRIGGER parity_session_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit down'); END");
        $this->api('POST', 'account/sessions/revoke-others', $token, ['current_password' => 'password'])->assertStatus(500);
        $this->assertSame(2, $db->table('sessions')->count());
        $this->assertNotNull(PersonalAccessToken::findToken($token));
        $this->assertSame($user->auth_version, $user->fresh()->auth_version);
        DB::unprepared('DROP TRIGGER parity_session_audit');
        $this->api('POST', 'account/sessions/revoke-others', $token, ['current_password' => 'password'])->assertOk();
        $this->assertSame(['unrelated-session'], $db->table('sessions')->pluck('id')->all());
    }

    public function test_recovery_neutral_response_uses_existing_broker_and_revokes_artifacts(): void
    {
        $user = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $token = $this->token($user);
        $responses = [];
        foreach ([$user->email, $inactive->email, 'unknown@example.test'] as $email) {
            $responses[] = $this->api('POST', 'auth/forgot-password', data: compact('email'))->assertOk()->json();
        }
        $this->assertSame($responses[0], $responses[1]);
        $this->assertSame($responses[1], $responses[2]);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $transport = app('mailer')->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $this->assertStringContainsString('/reset-sandi/', $transport->messages()->first()->getOriginalMessage()->getHtmlBody());
        $reset = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $reset, 'password' => '  restored password  ', 'password_confirmation' => '  restored password  '];
        $this->api('POST', 'auth/reset-password', data: array_replace($payload, ['token' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->api('POST', 'auth/reset-password', data: $payload)->assertOk()->assertJsonMissingPath('token');
        $this->assertTrue(Hash::check('  restored password  ', $user->fresh()->password));
        $this->api('GET', 'auth/me', $token)->assertUnauthorized();
        $this->api('POST', 'auth/reset-password', data: $payload)->assertUnprocessable();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.password_reset']);
        $this->api('POST', 'auth/forgot-password', data: ['email' => [], 'extra' => true])->assertUnprocessable();
    }

    public function test_password_attempt_and_recovery_budgets_are_shared_with_web(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        $this->freezeTime();
        for ($i = 0; $i < 6; $i++) {
            $this->api('PATCH', 'account/password', $token, ['current_password' => 'wrong', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertUnprocessable();
        }
        $this->api('PATCH', 'account/profile', $token, ['name' => 'Changed', 'email' => $user->email])->assertStatus(429)->assertHeader('Retry-After');
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->actingAs($user, 'web')->patchJson(route('settings.password'), ['current_password' => 'password', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertStatus(429);
        Auth::forgetGuards();
        Auth::shouldUse('web');
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', 'auth/forgot-password', data: ['email' => 'unknown@example.test'])->assertOk();
        }
        $this->postJson(route('password.email'), ['email' => 'unknown@example.test'])->assertStatus(429)->assertHeader('Retry-After');
    }

    #[DataProvider('auditMutations')]
    public function test_audit_failure_rolls_back_every_mutation(string $action): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $token = $this->token($admin);
        $other = $this->token($admin);
        $reset = Password::createToken($admin);
        [$method, $url, $payload] = $this->mutation($action, $admin, $target);
        DB::unprepared("CREATE TEMP TRIGGER parity_all_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit down'); END");
        $this->api($method, $url, $token, $payload)->assertStatus(500)->assertJsonMissingPath('token');
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('settings', 0);
        $this->assertSame($admin->auth_version, $admin->fresh()->auth_version);
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertTrue(Password::tokenExists($admin, $reset));
        $this->api('GET', 'auth/me', $token)->assertOk();
        $this->api('GET', 'auth/me', $other)->assertOk();
    }

    public static function auditMutations(): array
    {
        return array_map(fn (string $action): array => [$action], ['create', 'edit', 'status', 'delete', 'settings', 'profile', 'password', 'revoke']);
    }

    public function test_logout_rechecks_expiry_inside_transaction(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        $fired = false;
        DB::connection()->beforeStartingTransaction(function () use (&$fired, $token): void {
            if (! $fired) {
                $fired = true;
                PersonalAccessToken::findToken($token)->forceFill(['expires_at' => now()->subSecond()])->save();
            }
        });
        $this->api('POST', 'auth/logout', $token)->assertUnauthorized();
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_search_zero_is_not_silently_ignored(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrator', 'email' => 'admin@example.test']);
        $token = $this->token($admin);
        User::factory()->create(['name' => 'Zero 0', 'email' => 'zero@example.test']);
        ActivityLog::record('auth.login', $admin);
        $this->api('GET', 'users?q=0', $token)->assertOk()->assertJsonPath('meta.total', 1);
        $this->api('GET', 'activities?q=0', $token)->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_fresh_password_is_checked_not_cached_guard_credentials(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        $changed = false;
        DB::connection()->beforeStartingTransaction(function () use (&$changed, $user): void {
            if (! $changed) {
                $changed = true;
                DB::table('users')->where('id', $user->id)->update(['password' => Hash::make('changed-in-flight')]);
            }
        });
        $this->api('PATCH', 'account/profile', $token, ['name' => 'Changed', 'email' => 'changed@example.test', 'current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_target_version_rotation_and_new_token_failure_cannot_partially_commit(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $token = $this->token($admin);
        $changed = false;
        DB::connection()->beforeStartingTransaction(function () use (&$changed, $target): void {
            if (! $changed) {
                $changed = true;
                $target->update(['email' => 'concurrent@example.test']);
            }
        });
        $this->api('PATCH', 'users/'.$target->id, $token, ['name' => 'Stale', 'email' => $target->email, 'role' => 'user'])->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->assertSame($target->name, $target->fresh()->name);
        DB::unprepared("CREATE TEMP TRIGGER parity_token_failure BEFORE INSERT ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'token down'); END");
        $this->api('PATCH', 'account/password', $token, ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertStatus(500);
        $this->assertSame($admin->auth_version, $admin->fresh()->auth_version);
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
        $this->api('GET', 'auth/me', $token)->assertOk();
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_recovery_failure_rolls_back_and_reset_limits_are_shared_by_email_across_ips(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        $reset = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $reset, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        DB::unprepared("CREATE TEMP TRIGGER parity_reset_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit down'); END");
        $this->api('POST', 'auth/reset-password', data: $payload)->assertStatus(500);
        $this->assertTrue(Password::tokenExists($user, $reset));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->api('GET', 'auth/me', $token)->assertOk();
        DB::unprepared('DROP TRIGGER parity_reset_audit');
        $this->freezeTime();
        for ($i = 1; $i <= 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i, 'HTTPS' => 'on']);
            $this->api('POST', 'auth/reset-password', data: array_replace($payload, ['token' => 'invalid']))->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->postJson(route('password.update'), $payload)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_member_profile_password_and_secret_allowlists_and_cors(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        $this->api('PATCH', 'account/profile', $token, ['name' => 'Member', 'email' => $user->email, 'role' => 'admin'])->assertUnprocessable();
        $this->api('PATCH', 'account/profile', $token, ['name' => 'Member', 'email' => $user->email])->assertOk();
        $response = $this->api('PATCH', 'account/password', $token, ['current_password' => 'password', 'password' => 'member-password', 'password_confirmation' => 'member-password'])->assertOk();
        $this->assertSame(['id', 'name', 'email', 'role', 'is_active'], array_keys($response->json('user')));
        foreach ([$user->password, $user->remember_token, 'member-password', 'auth_version'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $this->api('GET', 'auth/me', $response->json('token'))->assertOk();
        foreach (['PATCH', 'DELETE'] as $method) {
            $response = $this->options('/api/v1/users/1', [], ['Origin' => 'http://localhost:8150', 'Access-Control-Request-Method' => $method])->assertNoContent();
            $this->assertStringContainsString($method, $response->headers->get('Access-Control-Allow-Methods'));
        }
    }

    public function test_mobile_settings_reads_only_explicit_non_mqtt_keys(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, '"settings"') && str_starts_with($query->sql, 'select')) {
                $queries[] = $query;
            }
        });
        $this->api('GET', 'workspace', $token)->assertOk();
        $this->api('PATCH', 'settings', $token, ['section' => 'soil', 'values' => ['tank_min' => 22]])->assertOk();
        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertStringContainsString('"key"', $query->sql);
            $this->assertNotEmpty($query->bindings);
            foreach ($query->bindings as $binding) {
                $this->assertFalse(is_string($binding) && str_starts_with($binding, 'mqtt_'));
            }
        }
    }

    private function token(User $user, string $name = 'Parity device'): string
    {
        $token = $user->createToken($name, ['mobile:access'], now()->addDay());
        $token->accessToken->forceFill(['account_version' => $user->auth_version])->save();

        return $token->plainTextToken;
    }

    private function api(string $method, string $url, ?string $token = null, array $data = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');

        return $this->json($method, 'https://localhost/api/v1/'.$url, $data, $token === null ? [] : ['Authorization' => 'Bearer '.$token]);
    }
}
