<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\SettingsController;
use App\Models\Reading;
use App\Models\Setting;
use App\Support\MqttConnectionSettings;
use App\Support\ReadingIngester;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

class MqttConsumeCommand extends Command
{
    protected $signature = 'mqtt:consume';

    protected $description = 'Menerima data sensor dari broker MQTT lalu menyimpannya ke tabel readings';

    public function handle(): int
    {
        // Topik dasar mengikuti pengaturan admin (panel web). Nilai boleh
        // 'growvia/node', 'growvia/', atau 'growvia' — dinormalisasi agar
        // filter selalu berujung {base}/node{1,2}/data sesuai ReadingIngester.
        $base = rtrim((string) (Setting::query()->where('key', 'mqtt_topic')->value('value')
            ?: SettingsController::DEFAULTS['mqtt_topic']), '/');
        if (str_ends_with($base, '/node')) {
            $base = substr($base, 0, -strlen('/node'));
        }

        // php-mqtt/laravel-client >= 1.8: MQTT::connection() membuat koneksi
        // sesuai config/mqtt-client.php dan langsung connect. Host, port, dan
        // username dari pengaturan admin menimpa nilai .env saat terisi.
        MqttConnectionSettings::apply();
        try {
            $client = MQTT::connection();
        } catch (Throwable $exception) {
            $this->error('Gagal terhubung ke broker MQTT: '.$exception->getMessage());

            return self::FAILURE;
        }

        $accepted = 0;
        foreach (Reading::NODES as $node) {
            $client->subscribe($base.'/'.$node.'/data', function (string $topic, string $message) use (&$accepted): void {
                try {
                    $stored = ReadingIngester::ingestPayload($topic, $message);
                    $accepted += $stored;
                    if ($stored > 0) {
                        $this->info(sprintf('[mqtt] %s: %d metric tersimpan', $topic, $stored));
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }, 0);
        }

        $this->info(sprintf('Menunggu data sensor di %s/{node1,node2}/data (Ctrl+C untuk berhenti)…', $base));
        $client->loop(true);

        return self::SUCCESS;
    }
}
