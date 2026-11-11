<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reading;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReadingsWebController extends Controller
{
    /**
     * Halaman admin: dashboard ringkas = status data backend.
     * Detail lengkap ada di aplikasi mobile via API.
     */
    public function dashboard(): View
    {
        return view('dashboard', [
            'headerTitle' => 'Halo, '.auth()->user()->name,
            'latest' => $this->latestPerNodeMetric(),
            'counts' => $this->countsPerNode(),
            'lastRecorded' => Reading::query()->max('recorded_at'),
        ]);
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
        $columns = array_keys(Reading::METRICS);

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
