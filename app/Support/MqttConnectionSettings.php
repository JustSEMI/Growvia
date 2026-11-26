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

    public static function connectionName(): string
    {
        return (string) (config('mqtt-client.default_connection') ?? config('mqtt-client.default', 'default'));
    }

    /** Timpa config koneksi default php-mqtt. */
    public static function apply(): void
    {
        $prefix = 'mqtt-client.connections.'.self::connectionName();
        $stored = Setting::query()->whereIn('key', array_keys(self::MAP))->pluck('value', 'key');

        foreach (self::MAP as $key => $configKey) {
            $value = trim((string) $stored->get($key, ''));
            if ($value === '') {
                continue;
            }

            config([$prefix.'.'.$configKey => $configKey === 'port' ? (int) $value : $value]);
        }

        if ((int) config($prefix.'.port') === 8883 && env('MQTT_TLS_ENABLED') === null) {
            config([$prefix.'.connection_settings.tls.enabled' => true]);
        }
    }

    public static function baseTopic(): string
    {
        $base = rtrim((string) (Setting::query()->where('key', 'mqtt_topic')->value('value')
            ?: SettingRules::DEFAULTS['mqtt_topic']), '/');
        if (str_ends_with($base, '/node')) {
            $base = substr($base, 0, -strlen('/node'));
        }

        return $base !== '' ? $base : 'growvia';
    }

    /**
     * Topik dasar dari pengaturan admin dinormalisasi menjadi {base}/node{1,2}/data.
     *
     * @return list<string>
     */
    public static function topics(string $suffix = 'data'): array
    {
        $base = self::baseTopic();

        return array_map(fn (string $node): string => $base.'/'.$node.'/'.$suffix, Reading::NODES);
    }
}
