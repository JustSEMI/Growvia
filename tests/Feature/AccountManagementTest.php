<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_are_active_and_an_admin_can_filter_inactive_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $active = User::factory()->create(['name' => 'Petani Aktif']);
        $this->assertTrue($active->fresh()->is_active);
        $inactive = User::factory()->create(['name' => 'Petani Nonaktif', 'is_active' => false]);

        $response = $this->actingAs($admin)->get(route('users.index', ['status' => 'inactive']))->assertOk();
        $this->assertSame([$inactive->id], $response->viewData('users')->pluck('id')->all());
    }

    public function test_admin_can_disable_and_reenable_an_account_but_cannot_disable_self(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->actingAs($admin)->patch('/pengguna/'.$user->id.'/status', ['is_active' => '0'])->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->patch('/pengguna/'.$user->id.'/status', ['is_active' => '1'])->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
        $this->patch('/pengguna/'.$admin->id.'/status', ['is_active' => '0'])->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->is_active);
        $this->actingAs($user)->patch('/pengguna/'.$admin->id.'/status', ['is_active' => '0'])->assertForbidden();
    }

    public function test_filters_reject_invalid_types_and_preserve_pagination_and_sort_order(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(12)->create(['name' => 'Petani Bersama']);
        $this->actingAs($admin)->getJson(route('users.index', ['sort' => 'name desc; drop table users']))->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson(route('users.index', ['q' => ['unexpected']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $response = $this->get(route('users.index', ['q' => 'Petani', 'role' => 'user', 'sort' => 'oldest']))->assertOk();
        $paginator = $response->viewData('users');
        $this->assertSame(12, $paginator->total());
        $this->assertCount(10, $paginator->items());
        parse_str(parse_url($paginator->url(2), PHP_URL_QUERY), $query);
        $this->assertSame('Petani', $query['q']);
        $this->assertSame('user', $query['role']);
        $this->assertSame('oldest', $query['sort']);
    }

    public function test_filters_and_account_status_actions_are_available_in_the_existing_user_layout(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee('Cari nama atau email')->assertSee('Status akun')->assertSee('Urutkan')
            ->assertSee(route('users.status', $user), false)->assertSee('Nonaktifkan');
        $this->get(route('users.index', ['q' => 'no-matching-person']))->assertOk()->assertSee('Tidak ada pengguna yang cocok');
    }

    public function test_malformed_old_input_renders_validation_errors_instead_of_crashing_the_form(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->from(route('users.index'))->post(route('users.store'), [
            'name' => ['unexpected'], 'email' => 'candidate@example.test', 'password' => 'fixture-password', 'role' => 'user',
        ])->assertSessionHasErrors('name');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('users.index'))->assertOk();
    }

    public function test_web_validation_and_navigation_are_consistently_indonesian(): void
    {
        $this->postJson(route('login'), [])->assertUnprocessable()->assertJsonPath('errors.email.0', 'Kolom alamat email wajib diisi.');
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $labels = [];
        foreach ($xpath->query('//nav[@aria-label="Navigasi utama"]') as $navigation) {
            $labels[] = $navigation->textContent;
        }
        $text = implode(' ', $labels);
        $this->assertStringContainsString('Ringkasan', $text);
        $this->assertStringContainsString('Ruang kerja', $text);
        $this->assertStringNotContainsString('Workspace', $text);
        $this->assertStringNotContainsString('Dashboard', $text);
    }

    public function test_admin_can_search_names_and_emails_without_escaping_role_filters(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Pengelola Utama']);
        $first = User::factory()->create(['name' => 'Rani Kebun', 'email' => 'rani@example.test']);
        $second = User::factory()->create(['name' => 'Bima Kebun', 'email' => 'bima@example.test']);
        User::factory()->admin()->create(['name' => 'Kebun Pengelola']);

        $response = $this->actingAs($admin)->get(route('users.index', ['q' => 'Kebun', 'role' => 'user', 'sort' => 'name_asc']))->assertOk();
        $this->assertSame([$second->id, $first->id], $response->viewData('users')->pluck('id')->all());
        $response = $this->get(route('users.index', ['q' => 'rani@']))->assertOk();
        $this->assertSame([$first->id], $response->viewData('users')->pluck('id')->all());
    }
}
