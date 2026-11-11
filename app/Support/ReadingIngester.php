<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Reading;
use Throwable;

final class ReadingIngester
{
    /**
     * Simpan satu pesan MQTT "…/data" ke tabel readings.
     * Pesan asing (topik lain, JSON rusak, metric tak dikenal) ditolak dengan aman:
     * prinsip truthfulness project: tidak ada baris palsu yang masuk.
     *
     * @return int jumlah metric valid yang diterima (0 bila pesan ditolak)
     */
    public static function ingestPayload(string $topic, string $payload): int
    {
        $node = self::nodeFromTopic($topic);
        if ($node === null) {
            return 0;
        }

        try {
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return 0;
        }

        if (! is_array($decoded)) {
            return 0;
        }

        return self::ingest($node, $decoded);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return int jumlah metric valid yang diterima
     */
    public static function ingest(string $node, array $metrics): int
    {
        if (! in_array($node, Reading::NODES, true)) {
            return 0;
        }

        $recordedAt = now()->startOfSecond();
        $rows = [];
        foreach (Reading::METRICS as $metric => $unit) {
            if (! array_key_exists($metric, $metrics)) {
                continue;
            }
            $value = $metrics[$metric];
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                continue;
            }
            $rows[] = [
                'node' => $node,
                'metric' => $metric,
                'value' => (float) $value,
                'unit' => $unit,
                'recorded_at' => $recordedAt,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        // Pengiriman ulang broker (QoS > 0) tidak menduplikasi baris.
        Reading::upsert($rows, ['node', 'metric', 'recorded_at'], ['value', 'unit']);

        return count($rows);
    }

    private static function nodeFromTopic(string $topic): ?string
    {
        if (! str_ends_with($topic, '/data')) {
            return null;
        }
        $parts = explode('/', rtrim($topic, '/'));
        if (count($parts) < 2) {
            return null;
        }
        $node = $parts[count($parts) - 2];

        return in_array($node, Reading::NODES, true) ? $node : null;
    }
}
