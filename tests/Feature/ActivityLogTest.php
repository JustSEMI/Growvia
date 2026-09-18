<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SettingsController;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(Route::has('activity.index'));
    }

    public function test_admin_can_view_application_activity_history(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        ActivityLog::factory()->create([
            'action' => 'user.updated',
            'actor_name' => 'Admin Riwayat',
            'subject_name' => 'Petani Riwayat',
            'fields' => ['name', 'role'],
            'created_at' => '2026-09-16 04:05:06',
        ]);

        $this->actingAs($admin)->get(route('activity.index'))
            ->assertOk()
            ->assertViewIs('activity')
            ->assertSee('Riwayat aktivitas aplikasi')
            ->assertSee('Admin Riwayat')
            ->assertSee('Petani Riwayat')
            ->assertSee('Akun diperbarui')
            ->assertSee('Nama, Hak akses')
            ->assertSee('16 September 2026, 04.05.06 UTC');
    }

    public function test_history_filters_keep_name_search_grouped_with_action_and_dates(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        $matching = ActivityLog::factory()->create([
            'action' => 'user.updated', 'actor_name' => 'Petugas Biasa', 'subject_name' => 'Sari Kebun',
            'created_at' => '2026-09-16 23:59:59',
        ]);
        ActivityLog::factory()->create([
            'action' => 'auth.login', 'actor_name' => 'Sari Login', 'created_at' => '2026-09-16 10:00:00',
        ]);
        ActivityLog::factory()->create([
            'action' => 'user.updated', 'actor_name' => 'Petugas Biasa', 'subject_name' => 'Sari Lama', 'created_at' => '2026-09-15 23:59:59',
        ]);
        ActivityLog::factory()->create([
            'action' => 'user.updated', 'actor_name' => 'Sari Besok', 'created_at' => '2026-09-17 00:00:00',
        ]);
        ActivityLog::factory()->create([
            'action' => 'user.updated', 'actor_name' => 'Nama Berbeda', 'created_at' => '2026-09-16 12:00:00',
        ]);
        $filters = ['q' => 'Sari', 'action' => 'user.updated', 'from' => '2026-09-16', 'to' => '2026-09-16'];

        $this->actingAs($admin)->get(route('activity.index', $filters))
            ->assertOk()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1 && $activities->first()->is($matching))
            ->assertSee('name="q"', false)
            ->assertSee('value="Sari"', false)
            ->assertSee('data-custom-select', false)
            ->assertSee('Terapkan filter');
    }

    public function test_history_paginates_newest_first_and_retains_only_validated_filters(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        $this->travelTo(now()->setDateTime(2026, 9, 16, 12, 0, 0));
        $records = ActivityLog::factory()->count(17)->create(['action' => 'auth.login', 'actor_name' => 'Sari Kebun']);
        $filters = ['q' => 'Sari', 'action' => 'auth.login', 'from' => '2026-09-16', 'to' => '2026-09-16'];

        $response = $this->actingAs($admin)->get(route('activity.index', [...$filters, 'untrusted' => 'do-not-append']))
            ->assertOk()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->count() === 15 && $activities->total() === 17)
            ->assertSee('Navigasi halaman aktivitas')
            ->assertSee('1–15 dari 17 aktivitas');
        $activities = $response->viewData('activities');
        $this->assertSame($records->reverse()->take(15)->pluck('id')->values()->all(), $activities->pluck('id')->all());
        parse_str(parse_url($activities->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        $this->assertSame([...$filters, 'page' => '2'], $nextQuery);
        $this->assertStringContainsString(e($activities->nextPageUrl()), $response->getContent());

        $this->get($activities->nextPageUrl())
            ->assertOk()
            ->assertViewHas('activities', fn (LengthAwarePaginator $page): bool => $page->count() === 2 && $page->currentPage() === 2)
            ->assertSee('16–17 dari 17 aktivitas');
    }

    /**
     * Guards the index-friendly implementation: filtering must compare the raw
     * created_at column instead of wrapping it in a SQL date() function, which
     * would force a full table scan on every activity search.
     */
    public function test_date_filters_compare_the_raw_timestamp_column(): void
    {
        $sql = ActivityLog::query()->betweenDates('2026-09-16', '2026-09-16')->toSql();

        $this->assertStringNotContainsStringIgnoringCase('date(', $sql);
        $this->assertStringContainsString('created_at', $sql);
    }

    public function test_date_filter_boundaries_include_the_whole_named_day(): void
    {
        ActivityLog::factory()->create(['action' => 'auth.login', 'actor_name' => 'Awal Hari', 'created_at' => '2026-09-16 00:00:00']);
        ActivityLog::factory()->create(['action' => 'auth.login', 'actor_name' => 'Akhir Hari', 'created_at' => '2026-09-16 23:59:59']);
        ActivityLog::factory()->create(['action' => 'auth.login', 'actor_name' => 'Hari Sebelum', 'created_at' => '2026-09-15 23:59:59']);
        ActivityLog::factory()->create(['action' => 'auth.login', 'actor_name' => 'Hari Sesudah', 'created_at' => '2026-09-17 00:00:00']);

        $names = ActivityLog::query()
            ->betweenDates('2026-09-16', '2026-09-16')
            ->orderBy('id')
            ->pluck('actor_name')
            ->all();

        $this->assertSame(['Awal Hari', 'Akhir Hari'], $names);
    }

    public function test_factory_creates_a_valid_system_activity_without_secret_metadata(): void
    {
        $activity = ActivityLog::factory()->create()->fresh();

        $this->assertArrayHasKey($activity->action, ActivityLog::actionLabels());
        $this->assertSame('Sistem', $activity->actor_name);
        $this->assertNull($activity->actor_id);
        $this->assertNull($activity->subject_id);
        $this->assertSame([], $activity->fields);
    }

    public function test_compact_list_renders_real_activity_with_escaped_snapshots(): void
    {
        $activity = ActivityLog::factory()->create([
            'action' => 'user.deleted',
            'actor_name' => '<script>alert("actor")</script>',
            'subject_name' => '<img src=x onerror="alert(1)">',
            'created_at' => '2026-08-03 01:02:03',
        ]);

        $this->blade('<x-activity-list :activities="$activities" />', ['activities' => collect([$activity])])
            ->assertSee('Akun dihapus')
            ->assertSee($activity->actor_name)
            ->assertSee($activity->subject_name)
            ->assertDontSee($activity->actor_name, false)
            ->assertDontSee($activity->subject_name, false)
            ->assertSee('03 Agustus 2026, 01.02.03 UTC')
            ->assertDontSee('<html', false);
    }

    public function test_empty_compact_list_explains_application_logging_without_device_promises(): void
    {
        $this->blade('<x-activity-list :activities="$activities" />', ['activities' => collect()])
            ->assertSee('Belum ada aktivitas aplikasi.')
            ->assertSee('Catatan akun, sesi, dan pengaturan akan muncul di sini.')
            ->assertDontSee('perangkat')
            ->assertDontSee('realtime');
    }

    public function test_zero_is_a_name_search_instead_of_an_empty_filter(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        $matching = ActivityLog::factory()->create(['actor_name' => 'Petani 0']);
        ActivityLog::factory()->create(['actor_name' => 'Nama Lain']);

        $this->actingAs($admin)->get(route('activity.index', ['q' => '0']))
            ->assertOk()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1 && $activities->first()->is($matching));
    }

    public function test_history_distinguishes_no_filter_matches_from_an_empty_log(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        ActivityLog::factory()->create(['actor_name' => 'Petugas Kebun']);

        $this->actingAs($admin)->get(route('activity.index', ['q' => 'Tidak ditemukan']))
            ->assertOk()
            ->assertSee('Tidak ada aktivitas yang sesuai filter.')
            ->assertDontSee('Belum ada aktivitas aplikasi.');
    }

    public function test_records_activity_with_actor_and_subject_snapshots(): void
    {
        $this->freezeSecond();
        $actor = User::factory()->admin()->create(['name' => 'Admin Kebun']);
        $subject = User::factory()->create(['name' => 'Petani Satu']);

        $activity = ActivityLog::record('user.updated', $actor, $subject, ['name', 'role'])->fresh();

        $this->assertDatabaseHas('activity_logs', [
            'id' => $activity->id,
            'action' => 'user.updated',
            'actor_id' => $actor->id,
            'actor_name' => 'Admin Kebun',
            'subject_id' => $subject->id,
            'subject_name' => 'Petani Satu',
        ]);
        $this->assertSame(['name', 'role'], $activity->fields);
        $this->assertTrue($activity->created_at->equalTo(now()));
        $this->assertArrayNotHasKey('updated_at', $activity->getAttributes());
        $this->assertTrue($activity->actor->is($actor));
        $this->assertTrue($activity->subject->is($subject));
    }

    public function test_regular_user_cannot_access_activity_history(): void
    {
        $user = User::factory()->create()->refresh();

        $this->actingAs($user)->get(route('activity.index'))->assertForbidden();
    }

    public function test_guest_must_log_in_to_view_activity_history(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
    }

    public function test_empty_history_has_an_honest_application_empty_state(): void
    {
        $admin = User::factory()->admin()->create()->refresh();

        $this->actingAs($admin)->get(route('activity.index', ['q' => '', 'action' => '', 'from' => '', 'to' => '']))
            ->assertOk()
            ->assertSee('Belum ada aktivitas aplikasi.')
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 0);
    }

    public function test_invalid_filter_errors_render_in_the_indonesian_form(): void
    {
        $admin = User::factory()->admin()->create()->refresh();

        $this->actingAs($admin)->from(route('activity.index'))
            ->get(route('activity.index', ['from' => '2026-09-17', 'to' => '2026-09-16']))
            ->assertRedirect(route('activity.index'))
            ->assertSessionHasErrors('to');
        $sessionId = session()->getId();
        $this->withCookie(config('session.cookie'), $sessionId)->get(route('activity.index'))
            ->assertOk()
            ->assertSee('Periksa kembali isian berikut.')
            ->assertSee('Tanggal akhir tidak boleh sebelum tanggal mulai.')
            ->assertSee('id="to-error"', false);
    }

    public function test_history_escapes_snapshots_and_never_displays_sensitive_field_values(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        $activity = ActivityLog::factory()->create([
            'actor_name' => '<script>alert("actor")</script>',
            'subject_name' => '<img src=x onerror="alert(1)">',
            'fields' => ['mqtt_host', 'password' => 'secret-password-value', 'private@example.test', ['nested-secret']],
        ]);

        $this->actingAs($admin)->get(route('activity.index'))
            ->assertOk()
            ->assertSee($activity->actor_name)
            ->assertSee($activity->subject_name)
            ->assertDontSee($activity->actor_name, false)
            ->assertDontSee($activity->subject_name, false)
            ->assertSee('Alamat broker MQTT')
            ->assertDontSee('secret-password-value')
            ->assertDontSee('private@example.test')
            ->assertDontSee('nested-secret');
    }

    public function test_deleting_users_keeps_activity_with_original_snapshot_names(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        $actor = User::factory()->create(['name' => 'Pelaku Lama']);
        $subject = User::factory()->create(['name' => 'Subjek Lama']);
        $activity = ActivityLog::record('user.deleted', $actor, $subject);
        $actor->update(['name' => 'Pelaku Baru']);
        $subject->update(['name' => 'Subjek Baru']);
        $actor->delete();
        $subject->delete();

        $this->assertDatabaseHas('activity_logs', [
            'id' => $activity->id,
            'actor_id' => null,
            'subject_id' => null,
            'actor_name' => 'Pelaku Lama',
            'subject_name' => 'Subjek Lama',
        ]);
        $this->assertNull($activity->fresh()->actor);
        $this->assertNull($activity->fresh()->subject);
        $this->actingAs($admin)->get(route('activity.index'))
            ->assertOk()
            ->assertSee('Pelaku Lama')
            ->assertSee('Subjek Lama')
            ->assertDontSee('Pelaku Baru')
            ->assertDontSee('Subjek Baru');
    }

    public function test_record_accepts_deleted_users_without_reviving_foreign_keys(): void
    {
        $actor = User::factory()->create(['name' => 'Pelaku Terhapus']);
        $subject = User::factory()->create(['name' => 'Subjek Terhapus']);
        $actor->delete();
        $subject->delete();

        $activity = ActivityLog::record('user.deleted', $actor, $subject)->fresh();

        $this->assertNull($activity->actor_id);
        $this->assertNull($activity->subject_id);
        $this->assertSame('Pelaku Terhapus', $activity->actor_name);
        $this->assertSame('Subjek Terhapus', $activity->subject_name);
    }

    public function test_action_and_field_labels_cover_only_the_agreed_identifiers(): void
    {
        $actions = [
            'user.created', 'user.updated', 'user.deleted', 'user.activated', 'user.deactivated',
            'account.profile_updated', 'account.email_changed', 'account.password_changed',
            'account.password_reset', 'account.sessions_revoked', 'auth.login', 'auth.failed', 'auth.logout', 'settings.updated',
        ];
        $this->assertSame($actions, array_keys(ActivityLog::actionLabels()));
        $this->assertEqualsCanonicalizing(
            ['name', 'email', 'role', 'is_active', ...array_keys(SettingsController::DEFAULTS)],
            array_keys(ActivityLog::fieldLabels()),
        );

        foreach ($actions as $action) {
            $this->assertSame($action, ActivityLog::record($action, null)->fresh()->action);
        }
    }

    public function test_history_accepts_a_single_date_bound(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        $older = ActivityLog::factory()->create(['created_at' => '2026-09-15 23:59:59']);
        $newer = ActivityLog::factory()->create(['created_at' => '2026-09-16 00:00:00']);

        $this->actingAs($admin)->get(route('activity.index', ['to' => '2026-09-15']))
            ->assertOk()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1 && $activities->first()->is($older));
        $this->get(route('activity.index', ['from' => '2026-09-16']))
            ->assertOk()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1 && $activities->first()->is($newer));
    }

    public function test_name_search_is_bound_as_data_not_sql(): void
    {
        $admin = User::factory()->admin()->create()->refresh();
        $query = "' OR 1=1 --";
        $matching = ActivityLog::factory()->create(['actor_name' => $query]);
        ActivityLog::factory()->create(['actor_name' => 'Pelaku Lain']);

        $this->actingAs($admin)->get(route('activity.index', ['q' => $query]))
            ->assertOk()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1 && $activities->first()->is($matching));
    }

    #[DataProvider('invalidFilters')]
    public function test_history_rejects_invalid_filters(array $filters, string $field): void
    {
        $admin = User::factory()->admin()->create()->refresh();

        $this->actingAs($admin)->getJson(route('activity.index', $filters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'long search' => [['q' => str_repeat('a', 101)], 'q'],
            'array search' => [['q' => ['name']], 'q'],
            'unknown action' => [['action' => 'user.unknown'], 'action'],
            'array action' => [['action' => ['auth.login']], 'action'],
            'invalid start' => [['from' => '2026-02-30'], 'from'],
            'array start' => [['from' => ['2026-09-16']], 'from'],
            'invalid end format' => [['to' => '16-09-2026'], 'to'],
            'array end' => [['to' => ['2026-09-16']], 'to'],
            'reversed dates' => [['from' => '2026-09-17', 'to' => '2026-09-16'], 'to'],
        ];
    }

    public function test_record_stores_only_unique_allowlisted_field_names(): void
    {
        $activity = ActivityLog::record('settings.updated', null, null, [
            'name', 'email', 'name', 'mqtt_host', 'mqtt_user', 'notif_offline',
            'password', 'current_password', 'remember_token', 'token', 'cookie',
            'ip', 'session_id', 'mqtt_password', 'private@example.test', '192.0.2.1',
            ['role' => 'admin'], 42, null,
            'password_value' => 'role',
            'email_value' => 'private@example.test',
        ])->fresh();

        $this->assertSame(['name', 'email', 'mqtt_host', 'mqtt_user', 'notif_offline'], $activity->fields);
        $this->assertSame('Sistem', $activity->actor_name);
        $this->assertNull($activity->actor_id);
        $this->assertNull($activity->subject_name);
        $this->assertNull($activity->subject_id);
        $this->assertStringNotContainsString('private@example.test', $activity->toJson());
        $this->assertStringNotContainsString('192.0.2.1', $activity->toJson());
    }

    public function test_rejected_login_stores_a_salted_ip_hash_and_user_agent_but_no_address(): void
    {
        $user = User::factory()->create();

        $first = ActivityLog::recordRejected($user, '203.0.113.7', 'Growvia-Test-Agent/1.0')->fresh();
        $second = ActivityLog::recordRejected($user, '203.0.113.7', 'Growvia-Test-Agent/1.0')->fresh();
        $other = ActivityLog::recordRejected($user, '198.51.100.9', 'Growvia-Test-Agent/1.0')->fresh();

        // Same source groups together; a different source does not.
        $this->assertSame($first->ip_hash, $second->ip_hash);
        $this->assertNotSame($first->ip_hash, $other->ip_hash);
        // The address itself is never stored, only a salted digest.
        $this->assertNotSame('203.0.113.7', $first->ip_hash);
        $this->assertStringNotContainsString('203.0.113.7', ActivityLog::all()->toJson());
        $this->assertSame(64, strlen($first->ip_hash));
        $this->assertSame('Growvia-Test-Agent/1.0', $first->user_agent);
    }

    public function test_rejected_login_has_no_actor_and_carries_no_guessed_address(): void
    {
        $activity = ActivityLog::recordRejected(null, '203.0.113.7', 'Agent/1.0')->fresh();

        $this->assertSame('auth.failed', $activity->action);
        $this->assertNull($activity->actor_id);
        $this->assertNull($activity->subject_id);
        $this->assertNull($activity->subject_name);
        $this->assertSame([], $activity->fields);
    }

    public function test_rejected_login_tolerates_missing_request_context(): void
    {
        $activity = ActivityLog::recordRejected(null, null, null)->fresh();

        $this->assertNull($activity->ip_hash);
        $this->assertNull($activity->user_agent);
        $this->assertSame('auth.failed', $activity->action);
    }

    public function test_rejected_login_user_agent_is_bounded(): void
    {
        $activity = ActivityLog::recordRejected(null, '203.0.113.7', str_repeat('a', 400))->fresh();

        $this->assertSame(255, strlen($activity->user_agent));
    }

    public function test_record_bounds_snapshot_names_without_changing_user_names(): void
    {
        $actor = User::factory()->make(['name' => str_repeat('é', 260)]);
        $subject = User::factory()->make(['name' => str_repeat('あ', 260)]);

        $activity = ActivityLog::record('user.updated', $actor, $subject)->fresh();

        $this->assertSame(str_repeat('é', 255), $activity->actor_name);
        $this->assertSame(str_repeat('あ', 255), $activity->subject_name);
        $this->assertSame(260, mb_strlen($actor->name));
        $this->assertSame(260, mb_strlen($subject->name));
    }

    public function test_unknown_action_is_rejected_without_creating_a_record(): void
    {
        try {
            ActivityLog::record('auth.unknown', null);
            $this->fail('Aksi di luar daftar harus ditolak.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('activity_logs', 0);
        }
    }
}
