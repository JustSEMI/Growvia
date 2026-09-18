<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Buat akun', false);
    }

    public function test_user_cannot_access_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Petani Satu',
            'email' => 'petani1@growvia.id',
            'password' => 'rahasia123',
            'role' => 'user',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'petani1@growvia.id')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('rahasia123', $created->password));
        $this->assertSame('user', $created->role);
    }

    public function test_admin_can_update_and_reset_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name' => 'Nama Ganti',
            'email' => $user->email,
            'password' => 'baru12345',
            'admin_password' => 'password',
            'role' => 'user',
        ])->assertRedirect(route('users.index'));

        $fresh = $user->fresh();
        $this->assertSame('Nama Ganti', $fresh->name);
        $this->assertTrue(Hash::check('baru12345', $fresh->password));
    }

    public function test_admin_cannot_delete_self_or_demote_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertNotNull($admin->fresh());

        $this->actingAs($admin)->patch(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'user',
        ])->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_can_delete_other_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh());
    }
}
