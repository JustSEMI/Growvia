<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileActivityResource;
use App\Models\ActivityLog;
use App\Models\Reading;
use App\Models\Setting;
use App\Models\User;
use App\Support\DeviceControl;
use App\Support\MobileInput;
use App\Support\MobileMutation;
use App\Support\MobileSettings;
use App\Support\MqttPublisher;
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
        $input = MobileInput::validate($request, ['section' => ['required', 'string', Rule::in(array_keys(MobileSettings::SECTIONS))], 'values' => ['present', 'array']]);
        $settings = MobileMutation::run($request, function (User $actor) use ($input): array {
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
            if (($values['pump_mode'] ?? $stored['pump_mode']) === 'auto') {
                Setting::query()->where('key', 'pump_manual_state')->delete();
            }

            return array_replace($stored, $values);
        }, admin: true);

        $published = MqttPublisher::publishSectionConfig($input['section'], $settings);

        $message = match (true) {
            in_array($input['section'], ['th1', 'th2', 'mqtt', 'climate', 'soil', 'general'], true) && $published => 'Konfigurasi tersimpan dan dikirim ke perangkat.',
            in_array($input['section'], ['th1', 'th2', 'mqtt', 'climate', 'soil', 'general'], true) => 'Konfigurasi tersimpan, namun broker MQTT belum terjangkau.',
            default => 'Konfigurasi tersimpan.',
        };

        return response()->json([
            'settings' => $settings,
            'published' => $published,
            'message' => $message,
        ])->header('Cache-Control', 'no-store');
    }

    public function control(Request $request): JsonResponse
    {
        $node = is_string($request->input('node')) ? $request->input('node') : null;
        $mode = is_string($request->input('mode')) ? $request->input('mode') : null;
        $validated = MobileInput::validate($request, DeviceControl::rules($node, $mode));
        $state = array_key_exists('state', $validated) && $validated['state'] !== null
            ? (bool) $validated['state']
            : null;

        $plan = DeviceControl::plan(
            $validated['node'],
            $validated['actuator'],
            $state,
            $validated['mode'] ?? null,
        );

        $published = MqttPublisher::publishControl(
            $validated['node'],
            $validated['actuator'],
            $plan['state'],
            $plan['mode'],
            $plan['settings'],
            $plan['sync_config'],
        );

        $control = [
            'node' => $validated['node'],
            'actuator' => $validated['actuator'],
            'mode' => $plan['mode'],
            'state' => $plan['state'],
        ];

        if (! $published) {
            return response()->json([
                'message' => 'Broker MQTT tidak terhubung. Perintah tidak terkirim ke perangkat.',
                'published' => false,
                'control' => $control,
            ], 503)->header('Cache-Control', 'no-store');
        }

        MobileMutation::run($request, fn (User $actor) => DeviceControl::commit(
            $actor,
            $validated['node'],
            $validated['actuator'],
            $plan['state'],
            $plan['mode'],
        ));

        return response()->json([
            'message' => $plan['mode'] === 'auto'
                ? 'Mode perangkat dikembalikan ke otomatis.'
                : 'Perintah perangkat dikirim ke broker MQTT.',
            'published' => true,
            'control' => $control,
        ])->header('Cache-Control', 'no-store');
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
        $page = ActivityLog::query()
            ->filter($filters)
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);

        return response()->json(MobileInput::page($page, MobileActivityResource::class, $request) + ['actions' => ActivityLog::actionLabels(), 'timezone' => 'UTC'])->header('Cache-Control', 'no-store');
    }
}
