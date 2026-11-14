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
}
