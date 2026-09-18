<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountMutationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('accountMutations')]
    public function test_in_flight_actor_revocation_prevents_the_requested_mutation(string $action): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        [$method, $route, $payload] = match ($action) {
            'create' => ['POST', route('users.store'), ['name' => 'New account', 'email' => 'new@example.test', 'password' => 'fixture-password', 'role' => 'user']],
            'edit' => ['PATCH', route('users.update', $target), ['name' => 'Edited account', 'email' => $target->email, 'role' => 'user']],
            'status' => ['PATCH', route('users.status', $target), ['is_active' => false]],
            'delete' => ['DELETE', route('users.destroy', $target), []],
            'profile' => ['PATCH', route('settings.profile'), ['name' => 'Edited profile', 'email' => $admin->email]],
            'password' => ['PATCH', route('settings.password'), ['current_password' => 'password', 'password' => 'fixture-updated', 'password_confirmation' => 'fixture-updated']],
            'sessions' => ['DELETE', route('sessions.destroy'), ['sessions_password' => 'password']],
            'settings' => ['PATCH', route('settings.system'), ['section' => 'th2', 'pump_mode' => 'manual']],
        };
        $revoked = false;
        DB::connection()->beforeStartingTransaction(function () use ($admin, &$revoked): void {
            if (! $revoked) {
                $revoked = true;
                $admin->fresh()->forceFill(['auth_version' => Str::random(40)])->save();
            }
        });

        $this->actingAs($admin)->json($method, $route, $payload)->assertUnauthorized();
        $this->assertTrue($revoked);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('settings', 0);
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame($admin->name, $admin->fresh()->name);
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_a_target_revoked_after_route_binding_is_not_overwritten(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $changed = false;
        DB::connection()->beforeStartingTransaction(function () use ($target, &$changed): void {
            if (! $changed) {
                $changed = true;
                $target->fresh()->update(['email' => 'concurrent-change@example.test']);
            }
        });

        $this->actingAs($admin)->patchJson(route('users.update', $target), [
            'name' => 'Stale update', 'email' => $target->email, 'role' => 'user',
        ])->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->assertSame('concurrent-change@example.test', $target->fresh()->email);
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_keeping_this_session_does_not_adopt_a_later_revocation_version(): void
    {
        $user = User::factory()->create();
        $ownVersion = null;
        $laterVersion = Str::random(40);
        User::updated(function (User $updated) use (&$ownVersion, $laterVersion): void {
            if ($updated->wasChanged('password')) {
                $ownVersion = $updated->auth_version;
                DB::table('users')->where('id', $updated->id)->update(['auth_version' => $laterVersion]);
            }
        });
        $this->actingAs($user)->patch(route('settings.password'), [
            'current_password' => 'password', 'password' => 'fixture-new-password', 'password_confirmation' => 'fixture-new-password',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($ownVersion);
        $this->assertSame($ownVersion, session('account_version'));
        $this->assertSame($laterVersion, $user->fresh()->auth_version);
        Auth::guard('web')->setUser($user->fresh());
        $this->getJson(route('petani.dashboard'))->assertUnauthorized();
    }

    public function test_cross_site_mutations_without_csrf_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);
        $this->app->instance('env', 'local');
        foreach ([route('users.status', $target), route('settings.profile'), route('settings.password')] as $url) {
            $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->patch($url, ['is_active' => false])->assertStatus(419);
        }
        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public static function accountMutations(): array
    {
        return array_map(fn (string $action): array => [$action], ['create', 'edit', 'status', 'delete', 'profile', 'password', 'sessions', 'settings']);
    }
}
