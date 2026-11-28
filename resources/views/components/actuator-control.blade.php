@props([
    'node' => 'node2',
    'actuator' => 'pump',
    'actuatorLabel' => 'Pompa',
    'deviceLabel' => 'Device 2',
    'reading' => null,
    'settings' => [],
    'action',
    'hintId' => 'actuator-control-hint',
    'showAutoSwitch' => false,
    'autoNote' => 'Mode otomatis mengikuti ambang sistem.',
    'manualNote' => 'Mode manual aktif · dikendalikan langsung oleh operator.',
])

@php
    $manualKey = $actuator === 'fan1' ? 'fan1_manual_state' : 'pump_manual_state';

    $isAuto = $showAutoSwitch && (($settings['pump_mode'] ?? 'auto') !== 'manual');
    $telemetryKnown = $reading !== null;
    $telemetryOn = $reading !== null && (float) $reading->value >= 0.5;
    $rawManual = $settings[$manualKey] ?? null;
    $manualTarget = ! $isAuto && in_array((string) $rawManual, ['0', '1'], true)
        ? (string) $rawManual === '1'
        : null;
    $effectiveOn = $manualTarget ?? $telemetryOn;
    $pendingSync = $manualTarget !== null && (! $telemetryKnown || $manualTarget !== $telemetryOn);

    $ringClass = $telemetryOn
        ? 'is-on'
        : ($pendingSync && $manualTarget ? 'is-pending' : '');

    $statusTitle = match (true) {
        $telemetryOn && $pendingSync && ! $effectiveOn => 'Menyala · perintah mati terkirim',
        $telemetryOn => 'Menyala',
        $pendingSync && $manualTarget => 'Perintah nyala terkirim',
        $telemetryKnown => 'Mati',
        default => 'Menunggu data perangkat',
    };

    $lowerLabel = mb_strtolower($actuatorLabel);
    $hintText = match (true) {
        $pendingSync && $manualTarget => "Menunggu konfirmasi sensor {$lowerLabel} menyala dari {$deviceLabel}.",
        $pendingSync && ! $manualTarget => "Menunggu konfirmasi sensor {$lowerLabel} mati dari {$deviceLabel}.",
        $isAuto => $autoNote,
        default => $manualNote,
    };

    $nextState = $effectiveOn ? 0 : 1;
    $buttonLabel = $effectiveOn ? 'Matikan manual' : 'Nyalakan manual';
    $buttonClass = $effectiveOn ? 'button button--wide button--pump button--danger' : 'button button--wide button--pump';
@endphp

<div class="pump-control-stack">
    <div class="pump-visual" aria-hidden="true">
        <span class="pump-ring {{ $ringClass }}">
            <x-icon name="power" />
        </span>
    </div>

    <div class="pump-status-group">
        <p class="pump-status">{{ $statusTitle }}</p>
        <p class="pump-status-note" id="{{ $hintId }}">{{ $hintText }}</p>
    </div>

    <div class="pump-control-actions">
        <form method="POST" action="{{ $action }}" class="pump-control-form" data-turbo-frame="_top">
            @csrf
            <input type="hidden" name="node" value="{{ $node }}">
            <input type="hidden" name="actuator" value="{{ $actuator }}">
            <input type="hidden" name="mode" value="manual">
            <input type="hidden" name="state" value="{{ $nextState }}">
            <button type="submit" class="{{ $buttonClass }}" aria-describedby="{{ $hintId }}">
                <x-icon name="power" />
                <span>{{ $buttonLabel }}</span>
                <span class="sr-only"> {{ $actuatorLabel }}</span>
            </button>
        </form>

        @if($showAutoSwitch)
            <x-device-switch
                :action="$action"
                label="Mode otomatis"
                aria-label="Sakelar mode otomatis pompa air"
                :state-text="$isAuto ? 'Aktif' : 'Manual'"
                :checked="$isAuto"
                node="node2"
                actuator="pump"
                :mode="$isAuto ? 'manual' : 'auto'"
                :next-state="$isAuto ? ($effectiveOn ? 1 : 0) : null" />
        @endif
    </div>
</div>
