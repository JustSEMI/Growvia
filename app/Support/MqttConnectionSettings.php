<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Menimpa koneksi broker dari pengaturan admin; field kosong tetap pakai
 * .env, dan password selalu dari .env karena tidak ada di form.
 */
final class MqttConnectionSettings
{
    /** @var array<string, string> kunci settings => jalur config koneksi */
    private const MAP = [
        'mqtt_host' => 'host',
        'mqtt_port' => 'port',
        'mqtt_user' => 'connection_settings.auth.username',
    ];

    /** Timpa config koneksi default php-mqtt. */
    public static function apply(): void
    {
        $prefix = 'mqtt-client.connections.'.config('mqtt-client.default', 'default');
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
