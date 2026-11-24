<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccountMutation;
use App\Support\DeviceControl;
use App\Support\MqttPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeviceControlController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            DeviceControl::rules($request->input('node'), $request->input('mode')),
        );

        $state = array_key_exists('state', $validated) && $validated['state'] !== null
            ? (bool) $validated['state']
            : null;

        $prepared = AccountMutation::run($request, function (User $actor) use ($validated, $state): array {
            abort_unless($actor->isAdmin(), 403);

            return DeviceControl::prepare(
                $actor,
                $validated['node'],
                $validated['actuator'],
                $state,
                $validated['mode'] ?? null,
            );
        });

        $published = MqttPublisher::publishControl(
            $validated['node'],
            $validated['actuator'],
            $prepared['state'],
            $prepared['mode'],
            $prepared['settings'],
            $prepared['sync_config'],
        );

        if (! $published) {
            return back()->withErrors([
                'control' => 'Broker MQTT tidak terhubung. Perintah tidak terkirim ke perangkat.',
            ]);
        }

        $label = match ($validated['actuator']) {
            'fan1' => 'Kipas 1',
            'fan2' => 'Kipas 2',
            default => 'Pompa',
        };

        $actionText = $prepared['mode'] === 'auto'
            ? 'dikembalikan ke mode otomatis'
            : ($prepared['state'] ? 'dinyalakan (mode manual)' : 'dimatikan (mode manual)');

        return back()->with('status', "{$label} {$actionText} dan dikirim ke broker MQTT.");
    }
}
