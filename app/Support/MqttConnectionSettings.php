<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Menimpa koneksi broker bawaan .env dengan pengaturan admin di tabel
 * settings. Field yang kosong berarti tetap memakai nilai .env, dan
 * password selalu dari .env (MQTT_PASSWORD) karena tak ada di form.
 */
final class MqttConnectionSettings
{
    /** @var array<string, string> kunci settings => jalur config koneksi */
    private const MAP = [
        'mqtt_host' => 'host',
        'mqtt_port' => 'port',
        'mqtt_user' => 'connection_settings.auth.username',
    ];

    /** Terapkan penimpaan pada config koneksi default php-mqtt. */
    public static function apply(): void
    {
        $prefix = 'mqtt-client.connections.'.config('mqtt-client.default_connection', 'default');
        $stored = Setting::query()->whereIn('key', array_keys(self::MAP))->pluck('value', 'key');

        foreach (self::MAP as $key => $configKey) {
            $value = trim((string) $stored->get($key, ''));
            if ($value === '') {
                continue;
            }

            config([$prefix.'.'.$configKey => $configKey === 'port' ? (int) $value : $value]);
        }
    }
}
