<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActivityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_changes_status_and_deletion_record_real_actions_but_noops_do_not(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Nama sebelumnya']);
        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name' => 'Nama diperbarui', 'email' => $user->email, 'role' => 'user',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.updated', 'actor_id' => $admin->id, 'subject_id' => $user->id]);
        $this->assertSame(['name'], ActivityLog::query()->sole()->fields);
        $this->patch(route('users.update', $user), ['name' => 'Nama diperbarui', 'email' => $user->email, 'role' => 'user'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('activity_logs', 1);
        $this->patch(route('users.status', $user), ['is_active' => false])->assertSessionHasNoErrors();
        $this->patch(route('users.status', $user), ['is_active' => true])->assertSessionHasNoErrors();
        $this->delete(route('users.destroy', $user))->assertSessionHasNoErrors();
        $this->assertSame(['user.updated', 'user.deactivated', 'user.activated', 'user.deleted'], ActivityLog::query()->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted', 'subject_id' => null, 'subject_name' => 'Nama diperbarui']);
    }

    public function test_profile_password_and_session_security_actions_are_recorded_without_secrets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('settings.profile'), ['name' => 'Nama tercatat', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.profile_updated', 'actor_id' => $user->id]);
        $this->patch(route('settings.profile'), ['name' => 'Nama tercatat', 'email' => 'new-private@example.test', 'profile_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.email_changed', 'actor_id' => $user->id]);
        $this->patch(route('settings.password'), ['current_password' => 'password', 'password' => 'fixture-updated-123', 'password_confirmation' => 'fixture-updated-123'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.password_changed', 'actor_id' => $user->id]);
        $this->delete(route('sessions.destroy'), ['sessions_password' => 'fixture-updated-123'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.sessions_revoked', 'actor_id' => $user->id]);
        $json = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString('fixture-updated-123', $json);
        $this->assertStringNotContainsString('new-private@example.test', $json);
    }

    public function test_successful_login_logout_and_recovery_are_recorded_and_failures_are_recorded_without_secrets(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.failed', 'actor_id' => null, 'subject_id' => $user->id]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'actor_id' => $user->id]);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout', 'actor_id' => $user->id]);
        $token = Password::createToken($user);
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'fixture-recovered-123', 'password_confirmation' => 'fixture-recovered-123'])->assertRedirect(route('login'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.password_reset', 'actor_id' => $user->id, 'subject_id' => $user->id]);
        $json = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString($token, $json);
        // The attempted password must never reach the audit trail.
        $this->assertStringNotContainsString('incorrect', $json);
    }

    public function test_failed_login_for_an_unknown_account_records_no_subject_and_no_email(): void
    {
        $this->post('/login', ['email' => 'unknown-person@example.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $activity = ActivityLog::query()->sole();
        $this->assertSame('auth.failed', $activity->action);
        $this->assertNull($activity->actor_id);
        $this->assertNull($activity->subject_id);
        $this->assertNull($activity->subject_name);
        // Neither the guessed address nor the password is stored.
        $this->assertStringNotContainsString('unknown-person@example.test', ActivityLog::all()->toJson());
        $this->assertStringNotContainsString('incorrect', ActivityLog::all()->toJson());
    }

    public function test_system_settings_record_only_changed_field_names_and_roll_back_with_the_log(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('settings.system'), ['section' => 'th2', 'pump_mode' => 'manual'])->assertSessionHasNoErrors();
        $activity = ActivityLog::query()->sole();
        $this->assertSame('settings.updated', $activity->action);
        $this->assertSame(['pump_mode'], $activity->fields);
        $this->patch(route('settings.system'), ['section' => 'th2', 'pump_mode' => 'manual'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('activity_logs', 1);
        ActivityLog::creating(fn () => throw new \RuntimeException('Forced logging failure'));
        $this->patch(route('settings.system'), ['section' => 'th2', 'pump_mode' => 'auto'])->assertStatus(500);
        $this->assertDatabaseHas('settings', ['key' => 'pump_mode', 'value' => 'manual']);
    }

    public function test_production_activity_route_and_admin_dashboard_show_real_recent_entries(): void
    {
        $this->assertTrue(Route::has('activity.index'));
        $admin = User::factory()->admin()->create();
        ActivityLog::record('user.created', $admin, null, ['name']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Akun dibuat')->assertSee(route('activity.index'))->assertDontSee('Catatan perubahan dan peristiwa dari perangkat.');
        $this->get(route('activity.index'))->assertOk()->assertSee('Akun dibuat');
        $this->actingAs(User::factory()->create())->get(route('activity.index'))->assertForbidden();
    }

    public function test_log_failure_rolls_back_account_creation_and_security_updates(): void
    {
        $admin = User::factory()->admin()->create();
        ActivityLog::creating(fn () => throw new \RuntimeException('Forced logging failure'));
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Should roll back', 'email' => 'rollback@example.test', 'password' => 'fixture-password', 'role' => 'user'])->assertStatus(500);
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->patch(route('settings.password'), ['current_password' => 'password', 'password' => 'should-not-change', 'password_confirmation' => 'should-not-change'])->assertStatus(500);
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_activity_failure_cannot_leave_login_half_authenticated_or_prevent_logout(): void
    {
        $user = User::factory()->create();
        ActivityLog::creating(fn () => throw new \RuntimeException('Forced logging failure'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(500);
        $this->assertGuest();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_and_automatic_rehash_are_inside_the_same_transaction_as_the_activity(): void
    {
        $user = User::factory()->create();
        config(['hashing.bcrypt.rounds' => 5]);
        Hash::forgetDrivers();
        $level = null;
        Event::listen(Login::class, function () use (&$level): void {
            $level = DB::transactionLevel();
        });
        $before = DB::transactionLevel();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertSame($before + 1, $level);
        $this->assertNotNull($user->fresh()->auth_version);
        $this->assertSame((string) $user->fresh()->auth_version, session('account_version'));
        $this->get(route('petani.dashboard'))->assertOk();
        $this->assertDatabaseMissing('activity_logs', ['action' => 'account.password_changed']);
    }

    public function test_account_creation_records_the_actor_without_password_or_email_values(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Petani Tercatat', 'email' => 'log-candidate@example.test', 'password' => 'fixture-create-123', 'role' => 'user',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $created = User::query()->where('email', 'log-candidate@example.test')->firstOrFail();
        $activity = ActivityLog::query()->sole();
        $this->assertSame('user.created', $activity->action);
        $this->assertSame($admin->id, $activity->actor_id);
        $this->assertSame($created->id, $activity->subject_id);
        $this->assertSame(['name', 'email', 'role'], $activity->fields);
        $this->assertStringNotContainsString('fixture-create-123', $activity->toJson());
        $this->assertStringNotContainsString('log-candidate@example.test', $activity->toJson());
    }
}
