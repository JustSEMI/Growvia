<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Fillable(['action', 'actor_id', 'actor_name', 'subject_id', 'subject_name', 'ip_hash', 'user_agent', 'fields'])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    /**
     * @param  array<array-key, mixed>  $fields
     */
    public static function record(string $action, ?User $actor, ?User $subject = null, array $fields = []): self
    {
        if (! array_key_exists($action, self::actionLabels())) {
            throw new InvalidArgumentException('Jenis aktivitas tidak dikenal.');
        }

        $allowedFields = self::fieldLabels();
        $safeFields = [];

        foreach ($fields as $key => $field) {
            if (is_int($key) && is_string($field) && array_key_exists($field, $allowedFields)) {
                $safeFields[] = $field;
            }
        }

        return self::query()->create([
            'action' => $action,
            'actor_id' => $actor?->exists ? $actor->id : null,
            'actor_name' => mb_substr($actor?->name ?? 'Sistem', 0, 255),
            'subject_id' => $subject?->exists ? $subject->id : null,
            'subject_name' => $subject === null ? null : mb_substr($subject->name, 0, 255),
            'fields' => array_values(array_unique($safeFields)),
        ]);
    }

    /**
     * Record a rejected authentication attempt.
     *
     * Kept separate from record(): a rejection has no actor (by definition the
     * caller failed to prove who they are), and it is the only action that
     * carries request context. The IP is stored as a salted hash so the audit
     * trail can still group repeated attempts from one source without keeping
     * the address itself.
     *
     * Never store the attempted password or a guessed email address: an
     * unknown address has no subject to attach.
     */
    public static function recordRejected(?User $attempted, ?string $ip, ?string $userAgent): self
    {
        $log = self::query()->create([
            'action' => 'auth.failed',
            'actor_id' => null,
            'actor_name' => 'Sistem',
            'subject_id' => $attempted?->exists ? $attempted->id : null,
            'subject_name' => $attempted?->exists ? mb_substr($attempted->name, 0, 255) : null,
            'ip_hash' => self::ipHash($ip),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'fields' => [],
        ]);

        return $log;
    }

    /**
     * Salt the address with APP_KEY before hashing, so the stored value cannot
     * be recovered by hashing a list of candidate addresses.
     */
    public static function ipHash(?string $ip): ?string
    {
        if ($ip === null || trim($ip) === '') {
            return null;
        }

        $key = (string) config('app.key');

        return hash('sha256', $key.'|'.trim($ip));
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'user.created' => 'Akun dibuat',
            'user.updated' => 'Akun diperbarui',
            'user.deleted' => 'Akun dihapus',
            'user.activated' => 'Akun diaktifkan',
            'user.deactivated' => 'Akun dinonaktifkan',
            'account.profile_updated' => 'Profil diperbarui',
            'account.email_changed' => 'Email akun diganti',
            'account.password_changed' => 'Kata sandi diganti',
            'account.password_reset' => 'Kata sandi direset',
            'account.sessions_revoked' => 'Sesi lain diakhiri',
            'auth.login' => 'Masuk ke aplikasi',
            'auth.failed' => 'Percobaan masuk gagal',
            'auth.logout' => 'Keluar dari aplikasi',
            'settings.updated' => 'Pengaturan sistem diperbarui',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'role' => 'Hak akses',
            'is_active' => 'Status akun',
            'mqtt_host' => 'Alamat broker MQTT',
            'mqtt_port' => 'Port MQTT',
            'mqtt_user' => 'Nama pengguna MQTT',
            'mqtt_topic' => 'Topik MQTT',
            'update_interval' => 'Interval pembaruan',
            'th_suhu_min' => 'Suhu minimum',
            'th_suhu_max' => 'Suhu maksimum',
            'th_hum_min' => 'Kelembapan udara minimum',
            'th_hum_max' => 'Kelembapan udara maksimum',
            'th_light_min' => 'Cahaya minimum',
            'soil_on' => 'Ambang mulai menyiram',
            'soil_off' => 'Ambang berhenti menyiram',
            'tank_min' => 'Air tandon minimum',
            'pump_max_run' => 'Batas durasi pompa',
            'pump_mode' => 'Mode pompa',
            'notif_pompa' => 'Notifikasi pompa',
            'notif_cahaya' => 'Notifikasi cahaya',
            'notif_tandon' => 'Notifikasi tandon',
            'notif_offline' => 'Notifikasi perangkat terputus',
        ];
    }

    /**
     * Filter on the stored UTC timestamp without wrapping the column in a SQL
     * function, so the created_at index stays usable. `to` still covers the
     * whole day, matching the previous whereDate() behaviour.
     *
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        if (is_string($from) && $from !== '') {
            $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $from, 'UTC')->startOfDay());
        }

        if (is_string($to) && $to !== '') {
            $query->where('created_at', '<', Carbon::createFromFormat('Y-m-d', $to, 'UTC')->addDay()->startOfDay());
        }

        return $query;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}
