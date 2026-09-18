<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_notification_values_never_partially_write_or_create_activity(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['notif', 'th2'] as $section) {
            $this->actingAs($admin)->patch(route('settings.system'), ['section' => $section, 'notif_pompa' => ['unexpected'], 'pump_mode' => 'manual'])->assertSessionHasErrors('notif_pompa');
        }
        $this->assertDatabaseCount('settings', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_boolean_preferences_are_always_stored_as_zero_or_one(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('settings.system'), ['section' => 'th2', 'notif_pompa' => false, 'notif_cahaya' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['key' => 'notif_pompa', 'value' => '0']);
        $this->assertDatabaseHas('settings', ['key' => 'notif_cahaya', 'value' => '1']);
    }

    public function test_admin_can_view_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Broker MQTT', false);
    }

    public function test_user_cannot_access_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }

    public function test_admin_can_update_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('settings.profile'), [
            'name' => 'Nama Baru',
            'email' => $admin->email,
        ])->assertRedirect();

        $this->assertSame('Nama Baru', $admin->fresh()->name);
    }

    public function test_admin_can_change_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('settings.password'), [
            'current_password' => 'password',
            'password' => 'rahasia-baru',
            'password_confirmation' => 'rahasia-baru',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('rahasia-baru', $admin->fresh()->password));
    }

    public function test_system_settings_persist_and_validate_ranges(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('settings.system'), [
            'section' => 'th1',
            'th_suhu_min' => '26',
            'th_suhu_max' => '31',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('26', Setting::where('key', 'th_suhu_min')->first()->value);

        $this->actingAs($admin)->patch(route('settings.system'), [
            'section' => 'th1',
            'th_suhu_min' => '35',
            'th_suhu_max' => '31',
        ])->assertSessionHasErrors('th_suhu_min');
    }

    public function test_user_can_access_own_settings_and_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('petani.settings'))->assertOk();

        $this->actingAs($user)->patch(route('settings.profile'), [
            'name' => 'Petani Update',
            'email' => $user->email,
        ])->assertRedirect();

        $this->assertSame('Petani Update', $user->fresh()->name);
    }

    public function test_user_still_cannot_access_admin_settings_or_system(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('settings.system'), ['section' => 'mqtt'])
            ->assertForbidden();
    }
}
