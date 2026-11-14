<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reading;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReadingsWebController extends Controller
{
    /**
     * Kolom riwayat: hanya sensor yang benar-benar terpasang.
     * Device 1: 2 SHT + 2 kipas. Device 2: 3 soil + ultrasonic + pompa.
     */
    private const COLUMNS = [
        'temperature', 'humidity', 'temperature2', 'humidity2',
        'soil_moisture', 'soil_moisture2', 'soil_moisture3',
        'water_level', 'pump', 'fan1', 'fan2',
    ];

    /**
     * Kartu metrik dashboard: satu kartu per besaran, nilainya rata-rata
     * dari sensor-sensor yang mengukur besaran yang sama. Sensor yang
     * belum mengirim tetap dihitung sebagai "1 dari N" di catatan kartu.
     *
     * @var array<string, list<array{metrics: list<string>, label: string, icon: string, tone: string, unit: string}>>
     */
    private const DASHBOARD_TILES = [
        'node1' => [
            ['metrics' => ['temperature', 'temperature2'], 'label' => 'Suhu udara', 'icon' => 'thermometer', 'tone' => 'amber', 'unit' => '°C'],
            ['metrics' => ['humidity', 'humidity2'], 'label' => 'Kelembapan udara', 'icon' => 'drop', 'tone' => 'blue', 'unit' => '%'],
        ],
        'node2' => [
            ['metrics' => ['soil_moisture', 'soil_moisture2', 'soil_moisture3'], 'label' => 'Kelembapan tanah', 'icon' => 'leaf', 'tone' => 'green', 'unit' => '%'],
            ['metrics' => ['water_level'], 'label' => 'Tandon', 'icon' => 'tank', 'tone' => 'blue', 'unit' => '%'],
        ],
    ];

    /**
     * Kartu status perangkat (aktuator): nilai 0/1 jadi Mati/Menyala.
     *
     * @var array<string, list<array{metric: string, label: string}>>
     */
    private const DASHBOARD_STATES = [
        'node1' => [
            ['metric' => 'fan1', 'label' => 'Kipas 1'],
            ['metric' => 'fan2', 'label' => 'Kipas 2'],
        ],
        'node2' => [
            ['metric' => 'pump', 'label' => 'Pompa'],
        ],
    ];

    /**
     * Halaman admin: ringkasan kebun dengan nilai terbaru, grafik tren,
     * dan status data. Bagian live di-refresh Turbo tiap 15 detik.
     */
    public function dashboard(): View
    {
        $lastRecorded = Reading::query()->max('recorded_at');
        $latest = $this->latestPerNodeMetric();

        return view('dashboard', [
            'headerTitle' => 'Halo, '.auth()->user()->name,
            'latest' => $latest,
            'counts' => $this->countsPerNode(),
            'lastRecorded' => $lastRecorded,
            'live' => $lastRecorded !== null
                && Carbon::parse($lastRecorded)->gt(now()->subSeconds(90)),
            'series' => $this->temperatureSeries(),
            'tiles' => $this->buildTiles($latest),
            'states' => self::DASHBOARD_STATES,
        ]);
    }

    /**
     * Hitung nilai rata-rata per kartu dari pembacaan terbaru tiap sensor,
     * beserta catatan jumlah sensor yang ikut dihitung.
     *
     * @param  array<string, array<string, Reading>>  $latest
     * @return array<string, list<array{label: string, icon: string, tone: string, unit: string, value: ?string, note: string}>>
     */
    private function buildTiles(array $latest): array
    {
        $tiles = [];
        foreach (self::DASHBOARD_TILES as $node => $defs) {
            $tiles[$node] = [];
            foreach ($defs as $def) {
                $readings = [];
                foreach ($def['metrics'] as $metric) {
                    $reading = $latest[$node][$metric] ?? null;
                    if ($reading !== null) {
                        $readings[] = $reading;
                    }
                }

                $expected = count($def['metrics']);
                $last = null;
                foreach ($readings as $reading) {
                    if ($last === null || $reading->recorded_at->gt($last->recorded_at)) {
                        $last = $reading;
                    }
                }

                $values = array_map(fn (Reading $reading): float => (float) $reading->value, $readings);
                $avg = $values === [] ? null : array_sum($values) / count($values);
                $detail = implode(' / ', array_map(fn (float $value): string => number_format($value, 1), $values));

                $note = match (true) {
                    $last === null => 'Menunggu data sensor',
                    $expected === 1 => 'Terakhir '.$last->recorded_at->timezone('UTC')->format('d/m H:i').' UTC',
                    count($values) === $expected => sprintf(
                        'Rata-rata %d sensor · %s · terakhir %s UTC',
                        count($values),
                        $detail,
                        $last->recorded_at->timezone('UTC')->format('d/m H:i'),
                    ),
                    default => sprintf('Rata-rata %d dari %d sensor · %s', count($values), $expected, $detail),
                };

                $tiles[$node][] = [
                    'label' => $def['label'],
                    'icon' => $def['icon'],
                    'tone' => $def['tone'],
                    'unit' => $def['unit'],
                    'value' => $avg === null ? null : number_format($avg, 1),
                    'note' => $note,
                ];
            }
        }

        return $tiles;
    }

    /**
     * Tabel data mentah vacuum 14 kolom, paginasi 20.
     */
    public function history(Request $request): View
    {
        $node = $request->query('node');
        if (! in_array($node, Reading::NODES, true)) {
            $node = null;
        }

        $query = Reading::query();
        if ($node) {
            $query->where('node', $node);
        }

        $paginator = $query->orderByDesc('recorded_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('riwayat', [
            'rows' => $this->pivot($paginator->getCollection()),
            'paginator' => $paginator,
            'node' => $node,
        ]);
    }

    public function apiDocs(): View
    {
        $base = rtrim((string) config('app.url'), '/');

        return view('api-docs', [
            'base' => $base,
            'metrics' => Reading::METRICS,
            'nodes' => Reading::NODES,
        ]);
    }

    public function mqtt(): View
    {
        $config = config('mqtt-client.connections.default');

        return view('mqtt', [
            'mqtt' => [
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 1883,
                'clientId' => $config['clientId'] ?? 'growvia-backend',
                'hasAuth' => filled($config['username'] ?? null) || filled($config['password'] ?? null),
            ],
            'topics' => [
                'growvia/node1/data',
                'growvia/node2/data',
            ],
            'counts' => $this->countsPerNode(),
            'lastRecorded' => Reading::query()->max('recorded_at'),
        ]);
    }

    /**
     * Suhu udara node1 6 jam terakhir (rata-rata dua sensor per timestamp),
     * disampling maksimal 60 titik untuk digambar sebagai garis SVG.
     *
     * @return array{points: string, min: float, max: float, from: string, to: string, count: int}|array<never, never>
     */
    private function temperatureSeries(): array
    {
        $rows = Reading::query()
            ->where('node', 'node1')
            ->whereIn('metric', ['temperature', 'temperature2'])
            ->where('recorded_at', '>=', now()->subHours(6))
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['value', 'metric', 'recorded_at']);

        $count = $rows->count();
        if ($count < 2) {
            return [];
        }

        // Kedua SHT dikirim dalam payload yang sama, jadi berbagi timestamp:
        // rata-ratakan nilai per titik waktu sebelum disampling.
        $averaged = $rows
            ->groupBy(fn (Reading $reading): string => $reading->recorded_at->format('Y-m-d\TH:i:s.uP'))
            ->map(fn (Collection $group): object => (object) [
                'recorded_at' => $group->first()->recorded_at,
                'value' => (float) $group->avg('value'),
            ])
            ->sortBy('recorded_at')
            ->values();

        $step = (int) max(1, ceil($averaged->count() / 60));
        $sampled = $averaged->filter(fn (object $point, int $index): bool => $index % $step === 0)->values();

        $width = 560.0;
        $height = 140.0;
        $padX = 8.0;
        $padY = 16.0;

        $first = $sampled->first();
        $last = $sampled->last();
        $min = (float) $sampled->min('value');
        $max = (float) $sampled->max('value');
        $span = max($max - $min, 0.5);
        $spanMs = max(1, $last->recorded_at->getTimestampMs() - $first->recorded_at->getTimestampMs());

        $points = $sampled->map(function (object $point) use ($first, $spanMs, $min, $span, $width, $height, $padX, $padY): string {
            $x = $padX + ($point->recorded_at->getTimestampMs() - $first->recorded_at->getTimestampMs()) / $spanMs * ($width - 2 * $padX);
            $y = $padY + (1 - ($point->value - $min) / $span) * ($height - 2 * $padY);

            return sprintf('%.1f,%.1f', $x, $y);
        })->implode(' ');

        return [
            'points' => $points,
            'min' => $min,
            'max' => $max,
            'from' => $first->recorded_at->format('H:i'),
            'to' => $last->recorded_at->format('H:i'),
            'count' => $sampled->count(),
        ];
    }

    /**
     * @return array<string, array<string, Reading>>
     */
    private function latestPerNodeMetric(): array
    {
        $rows = Reading::query()
            ->orderByDesc('recorded_at')->orderByDesc('id')
            ->get(['node', 'metric', 'value', 'unit', 'recorded_at']);

        return $rows->groupBy('node')->map(
            fn (Collection $group) => $group->unique('metric')->keyBy('metric')
        )->all();
    }

    /**
     * @return array<string, int>
     */
    private function countsPerNode(): array
    {
        $counts = Reading::query()
            ->selectRaw('node, COUNT(*) as total')
            ->groupBy('node')
            ->pluck('total', 'node')
            ->all();

        return [
            'node1' => (int) ($counts['node1'] ?? 0),
            'node2' => (int) ($counts['node2'] ?? 0),
        ];
    }

    /**
     * Pivot baris long-format menjadi 1 baris per timestamp.
     *
     * @param  Collection<int, Reading>  $readings
     * @return Collection<int, array<string, mixed>>
     */
    private function pivot(Collection $readings): Collection
    {
        $columns = self::COLUMNS;

        return $readings->groupBy(fn (Reading $r) => $r->recorded_at->format('Y-m-d H:i:s'))
            ->map(function (Collection $group, string $time) use ($columns) {
                $row = [
                    'time' => $time,
                    'node' => $group->firstWhere('node', 'node1') ? 'node1/node2' : $group->first()->node,
                ];
                foreach ($columns as $col) {
                    $found = $group->firstWhere('metric', $col);
                    $row[$col] = $found ? $found->value : null;
                }

                return $row;
            })->values();
    }
}
