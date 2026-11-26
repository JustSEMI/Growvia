@props([
    'reading' => null,
    'settings' => [],
    'action',
    'hintId' => 'pump-control-hint',
])

@php
    $pumpAuto = ($settings['pump_mode'] ?? 'auto') !== 'manual';
    $telemetryKnown = $reading !== null;
    $telemetryOn = $reading !== null && (float) $reading->value >= 0.5;
    $rawManual = $settings['pump_manual_state'] ?? null;
    $manualTarget = ! $pumpAuto && in_array((string) $rawManual, ['0', '1'], true)
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

    $hintText = match (true) {
        $pendingSync && $manualTarget => 'Menunggu konfirmasi sensor pompa menyala dari Device 2.',
        $pendingSync && ! $manualTarget => 'Menunggu konfirmasi sensor pompa mati dari Device 2.',
        $pumpAuto => 'Mode otomatis mengikuti ambang tanah dan tandon.',
        default => 'Mode manual aktif · pompa dikendalikan langsung.',
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
            <input type="hidden" name="node" value="node2">
            <input type="hidden" name="actuator" value="pump">
            <input type="hidden" name="mode" value="manual">
            <input type="hidden" name="state" value="{{ $nextState }}">
            <button type="submit" class="{{ $buttonClass }}" aria-describedby="{{ $hintId }}">
                <x-icon name="power" />
                <span>{{ $buttonLabel }}</span>
            </button>
        </form>

        <x-device-switch
            :action="$action"
            label="Mode otomatis"
            aria-label="Sakelar mode otomatis pompa air"
            :state-text="$pumpAuto ? 'Aktif' : 'Manual'"
            :checked="$pumpAuto"
            node="node2"
            actuator="pump"
            :mode="$pumpAuto ? 'manual' : 'auto'"
            :next-state="$pumpAuto ? ($effectiveOn ? 1 : 0) : null" />
    </div>
</div>
