<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
        $topics = MqttConnectionSettings::topics();

        // Host, port, dan username dari pengaturan admin menimpa nilai .env saat terisi.
        MqttConnectionSettings::apply();
        try {
            $client = MQTT::connection();
        } catch (Throwable $exception) {
            $this->error('Gagal terhubung ke broker MQTT: '.$exception->getMessage());

            return self::FAILURE;
        }

        $accepted = 0;
        foreach ($topics as $topic) {
            $client->subscribe($topic, function (string $topic, string $message) use (&$accepted): void {
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

        $this->info('Menunggu data sensor di '.implode(', ', $topics).' (Ctrl+C untuk berhenti)…');
        $client->loop(true);

        return self::SUCCESS;
    }
}
