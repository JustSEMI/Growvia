<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Facades\MQTT;
use RuntimeException;
use Tests\TestCase;

class MqttControlTest extends TestCase
{
    use RefreshDatabase;

    private function mockMqttPublish(array &$published): void
    {
        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('publish')
            ->andReturnUsing(function (string $topic, string $message, int $qos, bool $retain) use (&$published): void {
                $published[] = [
                    'topic' => $topic,
                    'payload' => json_decode($message, true, 512, JSON_THROW_ON_ERROR),
                    'qos' => $qos,
                    'retain' => $retain,
                ];
            });

        MQTT::shouldReceive('connection')->with('default')->andReturn($client);
        MQTT::shouldReceive('disconnect')->with('default')->andReturnNull();
    }

    public function test_web_system_settings_publish_retained_node_configs(): void
    {
        $published = [];
        $this->mockMqttPublish($published);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch(route('settings.system'), [
                'section' => 'th1',
                'th_suhu_min' => '24',
                'th_suhu_max' => '29',
                'th_hum_min' => '55',
                'th_hum_max' => '80',
                'th_light_min' => '25',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Pengaturan sistem tersimpan dan dikirim ke broker MQTT.');

        $this->actingAs($admin)
            ->patch(route('settings.system'), [
                'section' => 'th2',
                'soil_on' => '32',
                'soil_off' => '64',
                'tank_min' => '25',
                'pump_max_run' => '12',
                'pump_mode' => 'auto',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Pengaturan sistem tersimpan dan dikirim ke broker MQTT.');

        $this->assertCount(2, $published);
        $this->assertSame('growvia/node1/config', $published[0]['topic']);
        $this->assertTrue($published[0]['retain']);
        $this->assertEquals(29, $published[0]['payload']['th_suhu_max']);

        $this->assertSame('growvia/node2/config', $published[1]['topic']);
        $this->assertTrue($published[1]['retain']);
        $this->assertEquals(32, $published[1]['payload']['soil_on']);
        $this->assertSame('auto', $published[1]['payload']['pump_mode']);
    }

    public function test_mobile_settings_publish_retained_config_and_tolerate_broker_failure(): void
    {
        $published = [];
        $this->mockMqttPublish($published);
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('Mobile Admin')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/settings', [
                'section' => 'soil',
                'values' => [
                    'soil_on' => '35',
                    'soil_off' => '68',
                    'tank_min' => '18',
                    'pump_max_run' => '20',
                    'pump_mode' => 'manual',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('published', true)
            ->assertJsonPath('settings.pump_mode', 'manual');

        $this->assertCount(1, $published);
        $this->assertSame('growvia/node2/config', $published[0]['topic']);
        $this->assertTrue($published[0]['retain']);
        $this->assertEquals(35, $published[0]['payload']['soil_on']);
        $this->assertSame('manual', $published[0]['payload']['pump_mode']);

        $failing = Mockery::mock();
        $failing->shouldReceive('connection')->andThrow(new RuntimeException('Broker offline'));
        MQTT::swap($failing);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/settings', [
                'section' => 'climate',
                'values' => [
                    'th_suhu_min' => '23',
                    'th_suhu_max' => '28',
                    'th_hum_min' => '60',
                    'th_hum_max' => '80',
                    'th_light_min' => '20',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('published', false);

        $this->assertSame('28', Setting::query()->where('key', 'th_suhu_max')->value('value'));
    }

    public function test_web_dashboard_control_sends_pump_and_fan_commands_to_mqtt(): void
    {
        $published = [];
        $this->mockMqttPublish($published);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('dashboard.control'), [
                'node' => 'node2',
                'actuator' => 'pump',
                'mode' => 'manual',
                'state' => 1,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status');

        $this->assertSame('manual', Setting::query()->where('key', 'pump_mode')->value('value'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'settings.updated',
            'actor_id' => $admin->id,
        ]);

        // Turning pump manually switches pump_mode to manual and syncs retained node2/config.
        $this->assertSame('growvia/node2/control', $published[0]['topic']);
        $this->assertFalse($published[0]['retain']);
        $this->assertSame('pump', $published[0]['payload']['actuator']);
        $this->assertSame('manual', $published[0]['payload']['mode']);
        $this->assertSame(1, $published[0]['payload']['state']);

        $this->assertSame('growvia/node2/config', $published[1]['topic']);
        $this->assertTrue($published[1]['retain']);
        $this->assertSame('manual', $published[1]['payload']['pump_mode']);

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('dashboard.control'), [
                'node' => 'node1',
                'actuator' => 'fan1',
                'mode' => 'manual',
                'state' => 0,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status');

        $this->assertSame('growvia/node1/control', $published[2]['topic']);
        $this->assertSame('fan1', $published[2]['payload']['actuator']);
        $this->assertSame(0, $published[2]['payload']['state']);
    }

    public function test_non_admin_cannot_post_admin_web_control_and_broker_failure_fails_closed(): void
    {
        $petani = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($petani)
            ->post(route('dashboard.control'), [
                'node' => 'node2',
                'actuator' => 'pump',
                'mode' => 'manual',
                'state' => 1,
            ])
            ->assertForbidden();

        // Without an MQTT mock in testing env, publish returns false so web fails closed with an error.
        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('dashboard.control'), [
                'node' => 'node1',
                'actuator' => 'fan2',
                'mode' => 'manual',
                'state' => 1,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('control');
    }

    public function test_mobile_api_control_supports_petani_pump_manual_and_auto_and_returns_503_when_offline(): void
    {
        $published = [];
        $this->mockMqttPublish($published);
        $petani = User::factory()->create(['role' => 'user']);
        $token = $petani->createToken('Mobile Petani')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices/control', [
                'node' => 'node2',
                'actuator' => 'pump',
                'mode' => 'manual',
                'state' => true,
            ])
            ->assertOk()
            ->assertJsonPath('published', true)
            ->assertJsonPath('control.mode', 'manual')
            ->assertJsonPath('control.state', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices/control', [
                'node' => 'node2',
                'actuator' => 'pump',
                'mode' => 'auto',
            ])
            ->assertOk()
            ->assertJsonPath('published', true)
            ->assertJsonPath('control.mode', 'auto')
            ->assertJsonPath('control.state', null);

        $this->assertSame('auto', Setting::query()->where('key', 'pump_mode')->value('value'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices/control', [
                'node' => 'node1',
                'actuator' => 'pump',
                'state' => true,
            ])
            ->assertUnprocessable();

        $failing = Mockery::mock();
        $failing->shouldReceive('connection')->andThrow(new RuntimeException('Broker timeout'));
        MQTT::swap($failing);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices/control', [
                'node' => 'node1',
                'actuator' => 'fan1',
                'mode' => 'manual',
                'state' => false,
            ])
            ->assertStatus(503)
            ->assertJsonPath('published', false);

        $this->assertSame(2, ActivityLog::query()->where('action', 'settings.updated')->count());
    }

    public function test_dashboard_mqtt_and_api_docs_pages_show_control_and_publish_topics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kontrol perangkat')
            ->assertSee('/dashboard/kontrol')
            ->assertSee('role="switch"', false)
            ->assertSee('Sakelar daya pompa air')
            ->assertSee('Sakelar mode otomatis pompa air')
            ->assertSee('Sakelar Kipas 1')
            ->assertSee('Sakelar Kipas 2');

        $this->actingAs($admin)
            ->get('/dashboard/mqtt')
            ->assertOk()
            ->assertSee('growvia/node1/config')
            ->assertSee('growvia/node2/control');

        $this->actingAs($admin)
            ->get('/dashboard/api-docs')
            ->assertOk()
            ->assertSee('/devices/control');
    }
}
