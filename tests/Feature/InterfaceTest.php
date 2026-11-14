<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Mockery;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_csp_keeps_font_sources_tight_outside_the_vite_dev_server(): void
    {
        $this->swapViteHotState(false);

        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("font-src 'self';", $csp);
        $this->assertStringNotContainsString('127.0.0.1', $csp);
    }

    public function test_csp_allows_vite_dev_server_fonts_while_hot(): void
    {
        $this->swapViteHotState(true);

        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("font-src 'self' http://127.0.0.1:* http://localhost:*", $csp);
    }

    /**
     * Menyantakan keadaan file `public/hot` dari keputusan CSP agar test
     * tidak berubah hasil ketika `npm run dev` kebetulan sedang berjalan.
     */
    private function swapViteHotState(bool $hot): void
    {
        $vite = Mockery::mock(Vite::class)->makePartial();
        $vite->shouldReceive('isRunningHot')->andReturn($hot);
        $this->app->instance(Vite::class, $vite);
    }

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

    public function test_dashboard_shows_backend_status_simple(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Kebun hari ini')
            ->assertSee('Status data')
            ->assertSee('Belum ada cukup data suhu')
            ->assertSee('Iklim · Device 1')
            ->assertSee('Tanah &amp; air · Device 2', false)
            ->assertDontSee('Pengaturan yang kesimpen');
    }

    public function test_dashboard_renders_latest_sensor_values_and_trend(): void
    {
        $admin = User::factory()->admin()->create();

        Reading::query()->create(['node' => 'node1', 'metric' => 'temperature', 'value' => 27.4, 'unit' => '°C', 'recorded_at' => now()->subMinutes(30)]);
        Reading::query()->create(['node' => 'node1', 'metric' => 'temperature', 'value' => 28.2, 'unit' => '°C', 'recorded_at' => now()->subMinutes(10)]);
        Reading::query()->create(['node' => 'node1', 'metric' => 'temperature2', 'value' => 29.0, 'unit' => '°C', 'recorded_at' => now()->subMinutes(10)]);
        Reading::query()->create(['node' => 'node1', 'metric' => 'humidity', 'value' => 71.5, 'unit' => '%', 'recorded_at' => now()]);
        Reading::query()->create(['node' => 'node1', 'metric' => 'fan1', 'value' => 1, 'unit' => 'state', 'recorded_at' => now()]);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Rata-rata 2 sensor')
            ->assertSee('28.6')
            ->assertSee('28.2 / 29.0')
            ->assertSee('27.4')
            ->assertSee('71.5')
            ->assertSee('Rata-rata 1 dari 2 sensor')
            ->assertSee('Menyala')
            ->assertSee('Live')
            ->assertSee('<polyline class="trend-line"', false);
    }

    public function test_dashboard_sidebar_no_node_sections(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//aside[contains(@class, "sidebar")]//nav[@aria-label="Navigasi utama"]//a[@data-nav-section]');
        $this->assertSame(0, $links->length);
        $toggle = $xpath->query('//header[contains(@class, "topbar")]//button[@data-sidebar-toggle and @hidden and @aria-expanded="true"]');
        $this->assertSame(1, $toggle->length);
        $this->assertStringContainsString('Ciutkan navigasi', $toggle->item(0)->getAttribute('aria-label'));
        $mobileToggle = $xpath->query('//header[contains(@class, "topbar")]//summary[contains(@class, "mobile-menu-toggle") and @aria-label="Buka navigasi"]');
        $this->assertSame(1, $mobileToggle->length);
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
            $this->assertSame('ID', trim($xpath->query('//select[@data-language-select]/option[@value="id"]')->item(0)->textContent));
            $this->assertSame('EN', trim($xpath->query('//select[@data-language-select]/option[@value="en"]')->item(0)->textContent));
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
