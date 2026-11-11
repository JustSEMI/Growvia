<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileReadingResource;
use App\Models\Reading;
use App\Support\MobileInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReadingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = MobileInput::validate($request, [
            'node' => ['nullable', 'string', Rule::in(Reading::NODES)],
            'metric' => ['nullable', 'string', Rule::in(array_keys(Reading::METRICS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = Reading::query();
        if ($node = $filters['node'] ?? null) {
            $query->where('node', $node);
        }
        if ($metric = $filters['metric'] ?? null) {
            $query->where('metric', $metric);
        }
        if ($from = $filters['from'] ?? null) {
            $query->whereDate('recorded_at', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->whereDate('recorded_at', '<=', $to);
        }

        $readings = $query->orderByDesc('recorded_at')->orderByDesc('id')
            ->limit((int) ($filters['limit'] ?? 300))
            ->get();

        // Kontrak lama dipertahankan: tanpa data, mobile tetap menerima status deferred.
        if ($readings->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'available' => false,
                    'reason' => 'Integrasi sensor belum aktif. Belum ada riwayat pembacaan.',
                    'source' => 'rest_api',
                ],
            ])->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'data' => MobileReadingResource::collection($readings)->resolve($request),
            'meta' => ['available' => true, 'count' => $readings->count(), 'source' => 'rest_api'],
        ])->header('Cache-Control', 'no-store');
    }
}
