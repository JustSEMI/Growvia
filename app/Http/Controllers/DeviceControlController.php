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
        return $this->handleControl($request, adminOnly: true);
    }

    public function storePetaniPump(Request $request): RedirectResponse
    {
        return $this->handleControl($request, adminOnly: false, allowedNodes: ['node2'], allowedActuators: ['pump']);
    }

    public function storePetaniClimate(Request $request): RedirectResponse
    {
        return $this->handleControl($request, adminOnly: false, allowedNodes: ['node1'], allowedActuators: ['fan1', 'fan2']);
    }

    /**
     * @param  list<string>|null  $allowedNodes
     * @param  list<string>|null  $allowedActuators
     */
    private function handleControl(
        Request $request,
        bool $adminOnly,
        ?array $allowedNodes = null,
        ?array $allowedActuators = null,
    ): RedirectResponse {
        $actor = $request->user();
        if ($adminOnly) {
            abort_unless($actor instanceof User && $actor->isAdmin(), 403);
        }

        $validated = $request->validate(
            DeviceControl::rules(
                $request->input('node'),
                $request->input('mode'),
                $allowedNodes,
                $allowedActuators,
            ),
        );

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

        if (! $published) {
            return back()->withErrors([
                'control' => 'Broker MQTT tidak terhubung. Perintah tidak terkirim ke perangkat.',
            ]);
        }

        AccountMutation::run($request, function (User $lockedActor) use ($validated, $plan, $adminOnly): void {
            if ($adminOnly) {
                abort_unless($lockedActor->isAdmin(), 403);
            }

            DeviceControl::commit(
                $lockedActor,
                $validated['node'],
                $validated['actuator'],
                $plan['state'],
                $plan['mode'],
            );
        });

        $label = match ($validated['actuator']) {
            'fan1' => 'Kipas 1',
            'fan2' => 'Kipas 2',
            default => 'Pompa',
        };

        $actionText = $plan['mode'] === 'auto'
            ? 'dikembalikan ke mode otomatis'
            : ($plan['state'] ? 'dinyalakan (mode manual)' : 'dimatikan (mode manual)');

        return back()->with('status', "{$label} {$actionText} dan dikirim ke broker MQTT.");
    }
}
