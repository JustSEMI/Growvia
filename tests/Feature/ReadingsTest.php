<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\User;
use App\Support\ReadingIngester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReadingsTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(): string
    {
        $user = User::factory()->create(['password' => Hash::make('password-123')]);

        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password-123',
            'device_name' => 'Pengujian',
        ])->assertOk()->json('token');
    }

    public function test_readings_endpoint_serves_ingested_telemetry_with_the_mobile_contract(): void
    {
        Carbon::setTestNow('2026-11-06 08:00:00');
        $token = $this->bearerToken();
        $headers = ['Authorization' => 'Bearer '.$token];

        ReadingIngester::ingestPayload('growvia/node1/data', (string) json_encode([
            'device' => 'node1', 'temperature' => 27.41, 'humidity' => 68, 'light' => 1.85, 'co2' => 812,
        ]));
        ReadingIngester::ingestPayload('growvia/node2/data', (string) json_encode([
            'device' => 'node2', 'soil_moisture' => 44.1, 'soil_temperature' => 25.3, 'water_level' => 72.5, 'pump' => 1,
        ]));

        $response = $this->getJson('/api/v1/readings?node=node1', $headers)->assertOk()
            ->assertJsonPath('meta.available', true)
            ->assertJsonCount(4, 'data');

        $row = $response->json('data.0');
        $this->assertSame(['id', 'node', 'metric', 'value', 'unit', 'recorded_at'], array_keys($row));
        $this->assertIsInt($row['id']);
        // JSON number: float biasa, atau int bila nilai bilangan bulat (json_encode 812.0 -> 812).
        $this->assertTrue(is_float($row['value']) || is_int($row['value']), 'value harus JSON number');
        $temperature = collect($response->json('data'))->firstWhere('metric', 'temperature');
        $this->assertIsFloat($temperature['value']);
        $this->assertSame(27.41, $temperature['value']);
        $this->assertSame('°C', $temperature['unit']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(
            ['co2', 'humidity', 'light', 'temperature'],
            collect($response->json('data'))->pluck('metric')->sort()->values()->all()
        );
        foreach ($response->json('data') as $item) {
            $this->assertSame('node1', $item['node']);
            $this->assertStringEndsWith('+00:00', $item['recorded_at']);
        }

        // Tanpa filter node, data kedua node tetap terpisah jelas (4 + 4 baris).
        $all = $this->getJson('/api/v1/readings', $headers);
        $this->assertSame(8, $all->json('meta.count'));
        $this->assertSame(
            ['node1', 'node2'],
            collect($all->json('data'))->pluck('node')->unique()->sort()->values()->all()
        );
    }

    public function test_ingester_keeps_the_truthful_contract_and_survives_garbage(): void
    {
        Carbon::setTestNow('2026-11-06 08:00:00');

        $this->assertSame(0, ReadingIngester::ingestPayload('growvia/node9/data', '{"temperature":25}'));
        $this->assertSame(0, ReadingIngester::ingestPayload('growvia/node1/status', 'online'));
        $this->assertSame(0, ReadingIngester::ingestPayload('growvia/node1/data', '{bukan json'));
        $this->assertSame(0, ReadingIngester::ingestPayload('growvia/node1/data', '"teks biasa"'));
        $this->assertSame(0, Reading::count());

        // Metric tak dikenal dan nilai non-numerik diabaikan, sisanya disimpan.
        $this->assertSame(3, ReadingIngester::ingestPayload('growvia/node1/data', (string) json_encode([
            'temperature' => 25.5, 'light' => 0.42, 'co2' => 700, 'humidity' => 'bukan-angka', 'mystery' => 1,
        ])));
        $this->assertSame(3, Reading::count());

        // Pengiriman ulang broker pada detik yang sama tidak menduplikasi baris.
        $this->assertSame(2, ReadingIngester::ingestPayload('growvia/node1/data', (string) json_encode([
            'temperature' => 25.5, 'co2' => 700,
        ])));
        $this->assertSame(3, Reading::count());

        $this->assertSame(4, ReadingIngester::ingestPayload('growvia/node2/data', (string) json_encode([
            'soil_moisture' => 44, 'soil_temperature' => 25.3, 'water_level' => 72.5, 'pump' => 1,
        ])));
        $this->assertDatabaseHas('readings', ['node' => 'node2', 'metric' => 'pump', 'value' => 1, 'unit' => 'state']);
    }

    public function test_readings_endpoint_rejects_unknown_filters_and_anonymous_clients(): void
    {
        $this->getJson('/api/v1/readings')->assertUnauthorized();
        $this->getJson('/api/v1/readings', ['Authorization' => 'Bearer not-a-token'])->assertUnauthorized();

        $headers = ['Authorization' => 'Bearer '.$this->bearerToken()];
        $this->getJson('/api/v1/readings?node=node9', $headers)->assertUnprocessable();
        $this->getJson('/api/v1/readings?metric=salinity', $headers)->assertUnprocessable();
        $this->getJson('/api/v1/readings?limit=2000', $headers)->assertUnprocessable();
        $this->getJson('/api/v1/readings?petani=1', $headers)->assertUnprocessable();
    }

    public function test_workspace_telemetry_availability_follows_ingested_data(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->bearerToken()];

        $this->getJson('/api/v1/workspace', $headers)->assertOk()
            ->assertJsonPath('telemetry_available', false);

        ReadingIngester::ingestPayload('growvia/node1/data', (string) json_encode(['temperature' => 26.4]));

        $this->getJson('/api/v1/workspace', $headers)->assertOk()
            ->assertJsonPath('telemetry_available', true);
    }
}
