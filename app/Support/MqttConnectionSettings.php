<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Reading;
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

    /**
     * Topik dasar dari pengaturan admin dinormalisasi menjadi {base}/node{1,2}/data.
     *
     * @return list<string>
     */
    public static function topics(): array
    {
        $base = rtrim((string) (Setting::query()->where('key', 'mqtt_topic')->value('value')
            ?: SettingRules::DEFAULTS['mqtt_topic']), '/');
        if (str_ends_with($base, '/node')) {
            $base = substr($base, 0, -strlen('/node'));
        }

        return array_map(fn (string $node): string => $base.'/'.$node.'/data', Reading::NODES);
    }
}
