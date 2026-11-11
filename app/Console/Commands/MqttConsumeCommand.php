<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\SettingsController;
use App\Models\Reading;
use App\Models\Setting;
use App\Support\ReadingIngester;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\Mqtt;
use Throwable;

class MqttConsumeCommand extends Command
{
    protected $signature = 'mqtt:consume';

    protected $description = 'Menerima data sensor dari broker MQTT lalu menyimpannya ke tabel readings';

    public function handle(): int
    {
        // Topik dasar mengikuti pengaturan admin (panel web); default 'growvia/node'
        // menghasilkan filter growvia/node1/data dan growvia/node2/data.
        $prefix = rtrim((string) (Setting::query()->where('key', 'mqtt_topic')->value('value')
            ?: SettingsController::DEFAULTS['mqtt_topic']), '/');

        try {
            Mqtt::connect();
        } catch (Throwable $exception) {
            $this->error('Gagal terhubung ke broker MQTT: '.$exception->getMessage());

            return self::FAILURE;
        }

        $accepted = 0;
        foreach (Reading::NODES as $node) {
            Mqtt::subscribe($prefix.$node.'/data', function (string $topic, string $message) use (&$accepted): void {
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

        $this->info(sprintf('Menunggu data sensor di %s{node1,node2}/data (Ctrl+C untuk berhenti)…', $prefix));
        Mqtt::loop(true);

        return self::SUCCESS;
    }
}
