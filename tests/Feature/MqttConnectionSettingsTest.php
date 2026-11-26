<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\MqttConnectionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MqttConnectionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_override_the_env_connection(): void
    {
        config(['mqtt-client.connections.default.host' => 'env-broker.test']);
        config(['mqtt-client.connections.default.port' => 1883]);
        config(['mqtt-client.connections.default.connection_settings.auth.username' => 'env-user']);
        Setting::query()->create(['key' => 'mqtt_host', 'value' => 'settings-broker.test']);
        Setting::query()->create(['key' => 'mqtt_port', 'value' => '1884']);
        Setting::query()->create(['key' => 'mqtt_user', 'value' => 'settings-user']);

        MqttConnectionSettings::apply();

        $this->assertSame('settings-broker.test', config('mqtt-client.connections.default.host'));
        $this->assertSame(1884, config('mqtt-client.connections.default.port'));
        $this->assertSame('settings-user', config('mqtt-client.connections.default.connection_settings.auth.username'));
    }

    public function test_blank_settings_keep_the_env_connection(): void
    {
        config(['mqtt-client.connections.default.host' => 'env-broker.test']);
        config(['mqtt-client.connections.default.port' => 1883]);
        Setting::query()->create(['key' => 'mqtt_host', 'value' => '   ']);

        MqttConnectionSettings::apply();

        $this->assertSame('env-broker.test', config('mqtt-client.connections.default.host'));
        $this->assertSame(1883, config('mqtt-client.connections.default.port'));
    }

    public function test_tls_port_8883_enables_tls_and_client_id_matches_package_key(): void
    {
        $this->assertSame(
            config('mqtt-client.default'),
            config('mqtt-client.default_connection'),
        );
        $this->assertNotNull(config('mqtt-client.connections.default.client_id'));

        Setting::query()->create(['key' => 'mqtt_host', 'value' => 'tls-broker.test']);
        Setting::query()->create(['key' => 'mqtt_port', 'value' => '8883']);

        MqttConnectionSettings::apply();

        $this->assertSame('tls-broker.test', config('mqtt-client.connections.default.host'));
        $this->assertSame(8883, config('mqtt-client.connections.default.port'));
        $this->assertTrue((bool) config('mqtt-client.connections.default.connection_settings.tls.enabled'));
    }
}
