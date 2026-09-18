<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_user_can_access_petani_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('petani.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('petani.node1'))->assertOk();
        $this->actingAs($user)->get(route('petani.node2'))->assertOk();
        $this->actingAs($user)->get(route('petani.edukasi'))->assertOk();
    }

    public function test_admin_can_access_both_panels(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Iklim rumah kaca', false)
            ->assertSee('Tanah &amp; air', false)
            ->assertSee('Menunggu data', false)
            ->assertDontSee('Mock');
        $this->actingAs($admin)->get(route('petani.dashboard'))->assertOk();
    }
}
