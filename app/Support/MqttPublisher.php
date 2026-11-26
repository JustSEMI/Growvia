<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Mockery\MockInterface;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * Mengirim konfigurasi ambang dan perintah kontrol manual ke broker MQTT.
 */
final class MqttPublisher
{
    /** @param array<string, string>|null $settings */
    public static function publishSectionConfig(string $section, ?array $settings = null): bool
    {
        $nodes = match ($section) {
            'th1', 'climate' => ['node1'],
            'th2', 'soil' => ['node2'],
            'mqtt', 'general' => ['node1', 'node2'],
            default => [],
        };

        if ($nodes === []) {
            return true;
        }

        return self::publishNodesConfig($nodes, $settings);
    }

    /**
     * @param  list<string>  $nodes
     * @param  array<string, string>|null  $settings
     */
    public static function publishNodesConfig(array $nodes, ?array $settings = null): bool
    {
        if (app()->environment('testing') && ! (MQTT::getFacadeRoot() instanceof MockInterface)) {
            return false;
        }

        $values = $settings ?? self::loadSettings();
        $base = MqttConnectionSettings::baseTopic();
        $messages = [];

        foreach ($nodes as $node) {
            $payload = $node === 'node1'
                ? [
                    'th_suhu_min' => (float) $values['th_suhu_min'],
                    'th_suhu_max' => (float) $values['th_suhu_max'],
                    'th_hum_min' => (float) $values['th_hum_min'],
                    'th_hum_max' => (float) $values['th_hum_max'],
                    'th_light_min' => (float) $values['th_light_min'],
                    'update_interval' => (int) $values['update_interval'],
                ]
                : [
                    'soil_on' => (float) $values['soil_on'],
                    'soil_off' => (float) $values['soil_off'],
                    'tank_min' => (float) $values['tank_min'],
                    'pump_max_run' => (int) $values['pump_max_run'],
                    'pump_mode' => (string) $values['pump_mode'],
                    'update_interval' => (int) $values['update_interval'],
                ];

            $messages[] = [
                'topic' => $base.'/'.$node.'/config',
                'payload' => (string) json_encode($payload, JSON_THROW_ON_ERROR),
                'retain' => true,
            ];
        }

        return self::publishMessages($messages);
    }

    /** @param array<string, string>|null $settings */
    public static function publishControl(
        string $node,
        string $actuator,
        ?bool $state,
        string $mode = 'manual',
        ?array $settings = null,
        bool $syncConfig = false,
    ): bool {
        if (app()->environment('testing') && ! (MQTT::getFacadeRoot() instanceof MockInterface)) {
            return false;
        }

        $values = $settings ?? self::loadSettings();
        $base = MqttConnectionSettings::baseTopic();

        $payload = [
            'actuator' => $actuator,
            'mode' => $mode,
            'issued_at' => now()->utc()->toIso8601String(),
        ];
        if ($state !== null) {
            $payload['state'] = $state ? 1 : 0;
        }
        if ($node === 'node2') {
            $payload['soil_on'] = (float) $values['soil_on'];
            $payload['soil_off'] = (float) $values['soil_off'];
            $payload['tank_min'] = (float) $values['tank_min'];
            $payload['max_run_minutes'] = (int) $values['pump_max_run'];
        }

        $messages = [[
            'topic' => $base.'/'.$node.'/control',
            'payload' => (string) json_encode($payload, JSON_THROW_ON_ERROR),
            'retain' => false,
        ]];

        if ($syncConfig && $node === 'node2') {
            $messages[] = [
                'topic' => $base.'/node2/config',
                'payload' => (string) json_encode([
                    'soil_on' => (float) $values['soil_on'],
                    'soil_off' => (float) $values['soil_off'],
                    'tank_min' => (float) $values['tank_min'],
                    'pump_max_run' => (int) $values['pump_max_run'],
                    'pump_mode' => $mode,
                    'update_interval' => (int) $values['update_interval'],
                ], JSON_THROW_ON_ERROR),
                'retain' => true,
            ];
        }

        return self::publishMessages($messages);
    }

    /** @return array<string, string> */
    private static function loadSettings(): array
    {
        return array_replace(SettingRules::DEFAULTS, Setting::query()->pluck('value', 'key')->all());
    }

    /** @param list<array{topic: string, payload: string, retain: bool}> $messages */
    private static function publishMessages(array $messages): bool
    {
        MqttConnectionSettings::apply();

        $connectionName = MqttConnectionSettings::connectionName();
        $prefix = 'mqtt-client.connections.'.$connectionName;
        $originalClientId = config($prefix.'.client_id');

        // Pakai client_id unik per publish agar tidak menendang koneksi mqtt:consume dari broker.
        config([$prefix.'.client_id' => ($originalClientId ?: 'growvia-backend').'-pub-'.bin2hex(random_bytes(3))]);

        try {
            $client = MQTT::connection($connectionName);
            foreach ($messages as $message) {
                $client->publish($message['topic'], $message['payload'], 0, $message['retain']);
            }
            MQTT::disconnect($connectionName);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        } finally {
            config([$prefix.'.client_id' => $originalClientId]);
        }
    }
}
