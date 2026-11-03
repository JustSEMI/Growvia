<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_workspace_has_accessible_navigation_and_a_main_target(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lewati ke konten')
            ->assertSee('id="main-content"', false)
            ->assertSee('aria-label="Navigasi utama"', false)
            ->assertSee('Buka navigasi')
            ->assertSee('Panel petani')
            ->assertDontSee('cdn.jsdelivr.net', false)
            ->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_user_names_are_data_not_inline_javascript_in_delete_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $name = "Petani '+(globalThis.growviaMarker=1)+'";
        User::factory()->create(['name' => $name]);

        $response = $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//*[@onsubmit]')->length);
        $form = $xpath->query('//form[@data-confirm-name]')->item(0);
        $this->assertNotNull($form);
        $this->assertSame($name, $form->getAttribute('data-confirm-name'));
        $response->assertSee('Konfirmasi hapus akun');
    }

    public function test_dashboard_distinguishes_saved_configuration_from_sensor_state(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::create(['key' => 'pump_mode', 'value' => 'manual']);
        Setting::create(['key' => 'soil_on', 'value' => '42']);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Sensornya belum nyambung')
            ->assertSee('Pengaturan yang kesimpen')
            ->assertSee('Manual')
            ->assertSee('42%')
            ->assertDontSee('Pompa · mode Auto');
    }

    public function test_dashboard_sidebar_marks_node_sections_for_scroll_highlight(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//aside[contains(@class, "sidebar")]//nav[@aria-label="Navigasi utama"]//a[@data-nav-section]');
        $sections = [];
        foreach ($links as $link) {
            $sections[] = $link->getAttribute('data-nav-section');
        }
        $this->assertSame(['top', 'node1', 'node2'], $sections);
        $this->assertSame(1, $xpath->query('//aside[contains(@class, "sidebar")]//nav//a[@data-nav-section="top" and contains(@class, "is-active")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="node1"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="node2"]')->length);
        $toggle = $xpath->query('//header[contains(@class, "topbar")]//button[@data-sidebar-toggle and @hidden and @aria-expanded="true"]');
        $this->assertSame(1, $toggle->length);
        $this->assertStringContainsString('Ciutkan navigasi', $toggle->item(0)->getAttribute('aria-label'));
        $this->assertSame(1, $xpath->query('//header[contains(@class, "topbar")]//span[contains(@class, "topbar-divider")]')->length);
    }

    public function test_system_validation_errors_are_visible_and_linked_to_the_field(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->from(route('settings.index'))->patch(route('settings.system'), [
            'section' => 'th1',
            'th_hum_min' => '101',
        ])->assertSessionHasErrors('th_hum_min');
        $message = session('errors')->first('th_hum_min');
        $sessionId = session()->getId();

        $this->withCookie(config('session.cookie'), $sessionId)->get(route('settings.index'))->assertOk()
            ->assertSee($message)
            ->assertSee('data-error-summary', false)
            ->assertSee('href="#th_hum_min"', false)
            ->assertSee('id="th_hum_min-error"', false);
    }

    public function test_editing_an_account_keeps_creation_in_a_separate_form_context(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $response = $this->actingAs($admin)->get(route('users.index', ['edit' => $user->id]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//form[@method="POST" and @action="'.route('users.store').'"]')->length);
        $this->assertSame(1, $xpath->query('//form[@action="'.route('users.update', $user).'" and input[@name="_method" and @value="PATCH"]]')->length);
        $response->assertSee('href="'.route('users.index').'#create-account"', false);
    }

    public function test_delete_controls_wait_for_confirmation_script_before_becoming_enabled(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create();
        $response = $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//form[@data-confirm-name]//button[@disabled and @data-confirm-trigger]')->length);
        $response->assertSee('Konfirmasi hapus memerlukan JavaScript');
    }

    public function test_account_creation_and_editing_offer_custom_role_dropdowns_with_original_values(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => 'admin']);
        $pages = [
            ['url' => route('users.index'), 'id' => 'role', 'selected' => 'user'],
            ['url' => route('users.index', ['edit' => $user->id]), 'id' => 'edit_role', 'selected' => 'admin'],
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($admin)->get($page['url'])->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $selector = '//select[@id="'.$page['id'].'" and @name="role" and @data-custom-select]';
            $this->assertSame(1, $xpath->query($selector)->length);
            $this->assertSame(1, $xpath->query($selector.'/option[@value="'.$page['selected'].'" and @selected]')->length);
            $this->assertSame(2, $xpath->query($selector.'/option')->length);
        }
    }

    public function test_saved_pump_mode_uses_a_custom_dropdown_without_claiming_device_control(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::create(['key' => 'pump_mode', 'value' => 'manual']);
        $response = $this->actingAs($admin)->get(route('settings.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@id="pump_mode" and @name="pump_mode" and @data-custom-select]/option[@value="manual" and @selected]')->length);
        $response->assertSee('Kontrol perangkat belum aktif.');
    }

    public function test_shared_shells_include_an_accessible_custom_notification_region(): void
    {
        $pages = [
            $this->get(route('login'))->assertOk(),
            $this->get('/notification-test-missing-page')->assertNotFound(),
        ];
        $admin = User::factory()->admin()->create();
        $pages[] = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $user = User::factory()->create();
        $pages[] = $this->actingAs($user)->get(route('petani.dashboard'))->assertOk();

        foreach ($pages as $response) {
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//div[@data-toast-region and @role="region" and @aria-label="Notifikasi"]')->length);
            $this->assertSame(1, $xpath->query('//div[@data-toast-live and @role="status" and @aria-live="polite"]')->length);
            $this->assertSame(1, $xpath->query('//template[@data-toast-template]//button[@data-toast-close and @type="button"]')->length);
            $this->assertSame(0, $xpath->query('//form//*[@data-toast-region]')->length);
        }
    }

    public function test_shared_shells_offer_an_accessible_theme_choice_and_early_bootstrap(): void
    {
        $pages = [
            $this->get(route('login'))->assertOk(),
            $this->get('/theme-test-missing-page')->assertNotFound(),
        ];
        $admin = User::factory()->admin()->create();
        $pages[] = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $user = User::factory()->create();
        $pages[] = $this->actingAs($user)->get(route('petani.dashboard'))->assertOk();

        foreach ($pages as $response) {
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//head/script[@data-theme-bootstrap]')->length);
            $this->assertSame(1, $xpath->query('//div[@data-theme-control and @hidden]/select[@data-theme-select and @data-custom-select and @aria-label="Tema tampilan"]')->length);
            $this->assertSame(3, $xpath->query('//select[@data-theme-select]/option')->length);
            $this->assertSame(0, $xpath->query('//form//select[@data-theme-select]')->length);
            $this->assertSame(1, $xpath->query('//select[@data-theme-select]/option[@value="system" and @selected]')->length);
            $html = $response->getContent();
            $this->assertTrue(strpos($html, 'data-theme-bootstrap') < strpos($html, '<link'));
            $response->assertSee('Terang')->assertSee('Gelap')->assertSee('Sistem');
            $this->assertSame(1, $xpath->query('//div[@data-language-control and @hidden]/select[@data-language-select and @data-custom-select and @aria-label="Bahasa tampilan"]')->length);
            $this->assertSame(1, $xpath->query('//select[@data-language-select]/option[@value="id" and @selected]')->length);
            $this->assertSame(1, $xpath->query('//select[@data-language-select]/option[@value="en" and @disabled]')->length);
            $response->assertSee('Indonesia')->assertSee('segera hadir');
        }
    }

    public function test_language_choice_is_rendered_on_the_error_shell(): void
    {
        $response = $this->get('/language-test-missing-page')->assertNotFound();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//div[@data-language-control and @hidden]/select[@data-language-select]')->length);
    }
}
