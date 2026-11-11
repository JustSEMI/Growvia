<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileActivityResource;
use App\Models\ActivityLog;
use App\Models\Reading;
use App\Models\Setting;
use App\Models\User;
use App\Support\MobileInput;
use App\Support\MobileMutation;
use App\Support\MobileSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        MobileInput::validate($request, []);
        $admin = $request->user()->isAdmin();

        return response()->json([
            'settings' => MobileSettings::values(),
            'counts' => $admin ? [
                'users_total' => User::query()->count(),
                'users_active' => User::query()->where('is_active', true)->count(),
                'users_inactive' => User::query()->where('is_active', false)->count(),
                'admins' => User::query()->where('role', 'admin')->count(),
            ] : null,
            'recent_activities' => $admin ? MobileActivityResource::collection(ActivityLog::query()->latest('created_at')->latest('id')->limit(5)->get())->resolve($request) : [],
            'telemetry_available' => Reading::query()->exists(),
        ])->header('Cache-Control', 'no-store');
    }

    public function settings(Request $request): JsonResponse
    {
        MobileInput::validate($request, []);

        return response()->json(['settings' => MobileSettings::values()])->header('Cache-Control', 'no-store');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $settings = MobileMutation::run($request, function (User $actor) use ($request): array {
            $input = MobileInput::validate($request, ['section' => ['required', 'string', Rule::in(array_keys(MobileSettings::SECTIONS))], 'values' => ['present', 'array']]);
            $stored = MobileSettings::values(true);
            $values = MobileSettings::validate($input['section'], $input['values'], $stored);
            $changed = [];
            foreach ($values as $key => $value) {
                if ($stored[$key] !== $value) {
                    $changed[] = $key;
                }
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
            if ($changed !== []) {
                ActivityLog::record('settings.updated', $actor, null, $changed);
            }

            return array_replace($stored, $values);
        }, admin: true);

        return response()->json(['settings' => $settings, 'message' => 'Konfigurasi tersimpan. Belum diterapkan ke perangkat.'])->header('Cache-Control', 'no-store');
    }

    public function activities(Request $request): JsonResponse
    {
        $filters = MobileInput::validate($request, [
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', Rule::in(array_keys(ActivityLog::actionLabels()))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $query = ActivityLog::query();
        $search = $filters['q'] ?? '';
        if ($search !== '') {
            $query->where(fn ($query) => $query->where('actor_name', 'like', '%'.$search.'%')->orWhere('subject_name', 'like', '%'.$search.'%'));
        }
        if ($action = $filters['action'] ?? null) {
            $query->where('action', $action);
        }
        $query->betweenDates($filters['from'] ?? null, $filters['to'] ?? null);
        $page = $query->latest('created_at')->latest('id')->paginate(15);

        return response()->json(MobileInput::page($page, MobileActivityResource::class, $request) + ['actions' => ActivityLog::actionLabels(), 'timezone' => 'UTC'])->header('Cache-Control', 'no-store');
    }
}
