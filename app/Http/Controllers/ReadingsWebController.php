<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Support\MqttConnectionSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Satu kartu per besaran: rata-rata sensor yang mengukurnya, dan
     * sensor yang belum mengirim tetap dilaporkan sebagai "1 dari N".
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

    /** Ringkasan kebun admin; bagian live di-refresh Turbo tiap 15 detik. */
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
            'series' => $this->metricSeries('node1', ['temperature', 'temperature2']),
            'humiditySeries' => $this->metricSeries('node1', ['humidity', 'humidity2']),
            'soilSeries' => $this->metricSeries('node2', ['soil_moisture', 'soil_moisture2', 'soil_moisture3']),
            'waterSeries' => $this->metricSeries('node2', ['water_level']),
            'tiles' => $this->buildTiles($latest),
            'states' => self::DASHBOARD_STATES,
        ]);
    }

    public function petaniDashboard(): View
    {
        $lastRecorded = Reading::query()->max('recorded_at');
        $latest = $this->latestPerNodeMetric();

        return view('petani.dashboard', [
            'lastRecorded' => $lastRecorded,
            'live' => $lastRecorded !== null
                && Carbon::parse($lastRecorded)->gt(now()->subSeconds(90)),
            'tiles' => $this->buildTiles($latest),
        ]);
    }

    public function petaniNode1(): View
    {
        $lastRecorded = Reading::query()->where('node', 'node1')->max('recorded_at');
        $latest = $this->latestPerNodeMetric()['node1'] ?? [];

        return view('petani.node1', [
            'latest' => $latest,
            'lastRecorded' => $lastRecorded,
            'live' => $lastRecorded !== null
                && Carbon::parse($lastRecorded)->gt(now()->subSeconds(90)),
            'series' => $this->temperatureSeries(),
        ]);
    }

    public function petaniNode2(): View
    {
        $lastRecorded = Reading::query()->where('node', 'node2')->max('recorded_at');
        $latest = $this->latestPerNodeMetric()['node2'] ?? [];

        return view('petani.node2', [
            'latest' => $latest,
            'lastRecorded' => $lastRecorded,
            'live' => $lastRecorded !== null
                && Carbon::parse($lastRecorded)->gt(now()->subSeconds(90)),
        ]);
    }

    /**
     * Rata-rata pembacaan terbaru per kartu, dengan catatan sensor yang ikut dihitung.
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

                $tz = config('app.timezone');
                $note = match (true) {
                    $last === null => 'Menunggu data sensor',
                    $expected === 1 => 'Terakhir '.$last->recorded_at->timezone($tz)->format('d/m H:i T'),
                    count($values) === $expected => sprintf(
                        'Rata-rata %d sensor · %s · %s',
                        count($values),
                        $detail,
                        $last->recorded_at->timezone($tz)->format('d/m H:i T'),
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
     * Tabel data mentah, paginasi 20 baris waktu per halaman.
     */
    public function history(Request $request): View
    {
        [$base, $filters] = $this->historyQuery($request);

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $total = (int) (clone $base)->distinct()->count('recorded_at');
        $timestamps = (clone $base)
            ->toBase()
            ->select('recorded_at')
            ->distinct()
            ->orderByDesc('recorded_at')
            ->forPage($page, $perPage)
            ->pluck('recorded_at');

        $readings = $timestamps->isEmpty()
            ? collect()
            : (clone $base)
                ->whereIn('recorded_at', $timestamps)
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->get();

        $rows = $this->pivot($readings);
        $paginator = (new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString();

        return view('riwayat', [
            'rows' => $rows,
            'paginator' => $paginator,
            'node' => $filters['node'] ?? null,
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
            'filtered' => collect($filters)->contains(fn (?string $value): bool => $value !== null && $value !== ''),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function exportHistory(Request $request): StreamedResponse
    {
        [$base] = $this->historyQuery($request);
        $filename = 'growvia-riwayat-'.now()->timezone(config('app.timezone'))->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($base): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['waktu', 'node', ...self::COLUMNS]);

            $timestamps = (clone $base)
                ->toBase()
                ->select('recorded_at')
                ->distinct()
                ->orderByDesc('recorded_at')
                ->pluck('recorded_at');

            foreach ($timestamps->chunk(200) as $chunk) {
                $readings = (clone $base)
                    ->whereIn('recorded_at', $chunk)
                    ->orderByDesc('recorded_at')
                    ->orderByDesc('id')
                    ->get();

                foreach ($this->pivot($readings) as $row) {
                    $line = [$row['time'], $row['node']];
                    foreach (self::COLUMNS as $col) {
                        $line[] = $row[$col] ?? '';
                    }
                    fputcsv($handle, $line);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return array{0: Builder<Reading>, 1: array<string, mixed>}
     */
    private function historyQuery(Request $request): array
    {
        $filters = Validator::make($request->query(), [
            'node' => ['nullable', 'string', Rule::in(Reading::NODES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ], [
            'node.in' => 'Pilih node yang tersedia.',
            'from.date_format' => 'Tanggal mulai harus berformat tahun-bulan-tanggal.',
            'to.date_format' => 'Tanggal akhir harus berformat tahun-bulan-tanggal.',
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ])->validate();

        $tz = config('app.timezone');
        $query = Reading::query();

        if (! empty($filters['node'])) {
            $query->where('node', $filters['node']);
        }
        if (! empty($filters['from'])) {
            $query->where('recorded_at', '>=', Carbon::createFromFormat('Y-m-d', $filters['from'], $tz)->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('recorded_at', '<', Carbon::createFromFormat('Y-m-d', $filters['to'], $tz)->addDay()->startOfDay()->utc());
        }

        return [$query, $filters];
    }

    public function apiDocs(): View
    {
        $base = rtrim((string) config('app.url'), '/');

        return view('api-docs', [
            'base' => $base,
            'metrics' => Reading::METRICS,
            'nodes' => Reading::NODES,
            'endpoints' => [
                ['method' => 'GET', 'path' => '/health', 'access' => 'Publik', 'tone' => 'neutral', 'summary' => 'Status layanan dan versi API.'],
                ['method' => 'POST', 'path' => '/auth/login', 'access' => 'Publik', 'tone' => 'neutral', 'summary' => 'Login dengan email, password, dan device_name untuk token Sanctum.'],
                ['method' => 'POST', 'path' => '/auth/forgot-password', 'access' => 'Publik', 'tone' => 'neutral', 'summary' => 'Kirim tautan pemulihan kata sandi.'],
                ['method' => 'POST', 'path' => '/auth/reset-password', 'access' => 'Publik', 'tone' => 'neutral', 'summary' => 'Atur ulang kata sandi memakai token pemulihan.'],
                ['method' => 'GET', 'path' => '/auth/me', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Data akun yang sedang login.'],
                ['method' => 'POST', 'path' => '/auth/logout', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Cabut token Sanctum perangkat ini.'],
                ['method' => 'GET', 'path' => '/workspace', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Pengaturan, ringkasan admin, dan telemetry_available.'],
                ['method' => 'GET', 'path' => '/readings', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Riwayat sensor (query: node, metric, from, to, limit maks 1000).'],
                ['method' => 'GET', 'path' => '/account/sessions', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Daftar token mobile dan sesi web aktif.'],
                ['method' => 'PATCH', 'path' => '/account/profile', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Ubah nama atau email (wajib current_password jika ganti email).'],
                ['method' => 'PATCH', 'path' => '/account/password', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Ubah kata sandi akun sendiri.'],
                ['method' => 'POST', 'path' => '/account/sessions/revoke-others', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Akhiri sesi di perangkat lain.'],
                ['method' => 'GET', 'path' => '/users', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Daftar pengguna dengan filter q, role, status, sort, dan page.'],
                ['method' => 'POST', 'path' => '/users', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Buat akun pengguna baru.'],
                ['method' => 'PATCH', 'path' => '/users/{user}', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Perbarui profil, hak akses, atau kata sandi pengguna.'],
                ['method' => 'PATCH', 'path' => '/users/{user}/status', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Aktifkan atau nonaktifkan akun pengguna.'],
                ['method' => 'DELETE', 'path' => '/users/{user}', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Hapus akun pengguna.'],
                ['method' => 'GET', 'path' => '/settings', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Ambil konfigurasi sistem saat ini.'],
                ['method' => 'PATCH', 'path' => '/settings', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Perbarui konfigurasi sistem per section.'],
                ['method' => 'GET', 'path' => '/activities', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Log aktivitas aplikasi dengan filter tanggal dan aksi.'],
                ['method' => 'GET', 'path' => '/backups', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Daftar berkas cadangan database dan jadwalnya.'],
                ['method' => 'POST', 'path' => '/backups', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Jalankan backup database sekarang.'],
                ['method' => 'GET', 'path' => '/backups/{name}/download', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Unduh berkas backup (sql, sql.gz, tgz).'],
                ['method' => 'DELETE', 'path' => '/backups/{name}', 'access' => 'Admin', 'tone' => 'amber', 'summary' => 'Hapus berkas backup tertentu.'],
                ['method' => 'POST', 'path' => '/devices/control', 'access' => 'Bearer', 'tone' => 'blue', 'summary' => 'Kirim perintah manual kipas/pompa atau mode otomatis pompa via MQTT.'],
            ],
        ]);
    }

    public function mqtt(): View
    {
        MqttConnectionSettings::apply();
        $connection = config('mqtt-client.default', 'default');
        $config = config('mqtt-client.connections.'.$connection);
        $lastRecorded = Reading::query()->max('recorded_at');

        return view('mqtt', [
            'mqtt' => [
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 1883,
                'clientId' => $config['clientId'] ?? 'growvia-backend',
                'hasAuth' => filled($config['connection_settings']['auth']['username'] ?? null)
                    || filled($config['connection_settings']['auth']['password'] ?? null),
            ],
            'topics' => MqttConnectionSettings::topics(),
            'configTopics' => MqttConnectionSettings::topics('config'),
            'controlTopics' => MqttConnectionSettings::topics('control'),
            'counts' => $this->countsPerNode(),
            'lastRecorded' => $lastRecorded,
            'live' => $lastRecorded !== null
                && Carbon::parse($lastRecorded)->gt(now()->subSeconds(90)),
        ]);
    }

    /**
     * Deret waktu satu metrik (atau rata-rata multi-sensor per detik pada node yang sama)
     * dalam 6 jam terakhir (atau jendela 6 jam data terbaru), disampling maksimal 60 titik.
     *
     * @param  list<string>  $metrics
     * @return array{points: list<array{x: int, y: float}>, min: float, mid: float, max: float, avg: float, latest: float, from: string, midTime: string, to: string, period: string, isRecent: bool, count: int}|array<never, never>
     */
    private function metricSeries(string $node, array $metrics): array
    {
        $tz = config('app.timezone');
        $isRecent = true;

        $averaged = $this->loadAveragedSeries($node, $metrics, now()->subHours(6));
        if ($averaged->count() < 2) {
            $lastAt = Reading::query()
                ->where('node', $node)
                ->whereIn('metric', $metrics)
                ->max('recorded_at');

            if ($lastAt === null) {
                return [];
            }

            $isRecent = false;
            $anchor = Carbon::parse($lastAt);
            $averaged = $this->loadAveragedSeries($node, $metrics, $anchor->copy()->subHours(6));
            if ($averaged->count() < 2) {
                $averaged = $this->loadAveragedSeries($node, $metrics, null, 120);
            }
        }

        if ($averaged->count() < 2) {
            return [];
        }

        $step = (int) max(1, ceil($averaged->count() / 60));
        $sampled = $averaged->filter(fn (object $point, int $index): bool => $index % $step === 0)->values();
        if ($sampled->last() !== $averaged->last()) {
            $sampled->push($averaged->last());
        }

        $first = $sampled->first();
        $last = $sampled->last();
        $min = (float) $sampled->min('value');
        $max = (float) $sampled->max('value');
        $avg = (float) $sampled->avg('value');
        $midPoint = $sampled->get((int) floor($sampled->count() / 2));

        $points = $sampled->map(fn (object $point): array => [
            'x' => $point->recorded_at->getTimestampMs(),
            'y' => round((float) $point->value, 2),
        ])->all();

        return [
            'points' => $points,
            'min' => $min,
            'mid' => ($min + $max) / 2,
            'max' => $max,
            'avg' => $avg,
            'latest' => (float) $last->value,
            'from' => $first->recorded_at->timezone($tz)->format('H:i'),
            'midTime' => $midPoint->recorded_at->timezone($tz)->format('H:i'),
            'to' => $last->recorded_at->timezone($tz)->format('H:i'),
            'period' => $isRecent ? '6 jam terakhir' : 'Data terbaru · '.$last->recorded_at->timezone($tz)->format('d/m'),
            'isRecent' => $isRecent,
            'count' => $sampled->count(),
        ];
    }

    /**
     * Suhu udara node1; tetap dipanggil oleh panel petani node1.
     *
     * @return array{points: list<array{x: int, y: float}>, min: float, mid: float, max: float, avg: float, latest: float, from: string, midTime: string, to: string, period: string, isRecent: bool, count: int}|array<never, never>
     */
    private function temperatureSeries(): array
    {
        return $this->metricSeries('node1', ['temperature', 'temperature2']);
    }

    /**
     * Sensor sejenis pada node yang sama dikirim dalam payload yang sama, jadi dirata-rata per detik.
     *
     * @param  list<string>  $metrics
     * @return Collection<int, object{recorded_at: Carbon, value: float}>
     */
    private function loadAveragedSeries(string $node, array $metrics, ?Carbon $since, ?int $limit = null): Collection
    {
        $query = Reading::query()
            ->where('node', $node)
            ->whereIn('metric', $metrics);

        if ($since !== null) {
            $query->where('recorded_at', '>=', $since)->orderBy('recorded_at')->orderBy('id');
        } else {
            $query->orderByDesc('recorded_at')->orderByDesc('id')->limit($limit ?? 120);
        }

        return $query->get(['value', 'metric', 'recorded_at'])
            ->groupBy(fn (Reading $reading): string => $reading->recorded_at->format('Y-m-d\TH:i:sP'))
            ->map(fn (Collection $group): object => (object) [
                'recorded_at' => $group->first()->recorded_at,
                'value' => (float) $group->avg('value'),
            ])
            ->sortBy('recorded_at')
            ->values();
    }

    /**
     * @return array<string, array<string, Reading>>
     */
    private function latestPerNodeMetric(): array
    {
        $latestTimes = Reading::query()
            ->selectRaw('node, metric, MAX(recorded_at) as max_at')
            ->groupBy('node', 'metric');

        $latestIds = Reading::query()
            ->joinSub($latestTimes, 'latest', function ($join): void {
                $join->on('readings.node', '=', 'latest.node')
                    ->on('readings.metric', '=', 'latest.metric')
                    ->on('readings.recorded_at', '=', 'latest.max_at');
            })
            ->selectRaw('MAX(readings.id) as id')
            ->groupBy('readings.node', 'readings.metric');

        $rows = Reading::query()
            ->whereIn('id', $latestIds)
            ->get(['node', 'metric', 'value', 'unit', 'recorded_at']);

        return $rows->groupBy('node')->map(
            fn (Collection $group) => $group->keyBy('metric')
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
        $tz = config('app.timezone');

        return $readings->groupBy(fn (Reading $r) => $r->recorded_at->timezone($tz)->format('Y-m-d H:i:s'))
            ->map(function (Collection $group, string $time) use ($columns) {
                $row = [
                    'time' => $time,
                    'node' => $group->pluck('node')->unique()->sort()->implode('/'),
                ];
                foreach ($columns as $col) {
                    $found = $group->firstWhere('metric', $col);
                    $row[$col] = $found ? $found->value : null;
                }

                return $row;
            })->values();
    }
}
