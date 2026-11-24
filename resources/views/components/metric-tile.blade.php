@props(['label', 'value' => null, 'unit' => '', 'icon' => 'chart', 'tone' => 'green', 'note' => 'Menunggu data sensor', 'state' => false, 'min' => 0, 'max' => null])

@php
    $scaleMax = $max ?? ($unit === '°C' ? 50 : 100);
    $numeric = ! $state && $value !== null && is_numeric($value) ? (float) $value : null;
    $percent = $numeric !== null
        ? max(0.0, min(100.0, ($numeric - (float) $min) / max(1.0, (float) $scaleMax - (float) $min) * 100.0))
        : 0.0;
    $dashOffset = number_format(100.0 - $percent, 1, '.', '');
    $scaleSuffix = $unit === '°C' ? '°' : $unit;
@endphp

<article {{ $attributes->class(['panel', 'metric-card']) }}>
    <div class="metric-top"><h2 class="metric-label">{{ $label }}</h2><span class="icon-tile icon-tile--{{ $tone }}"><x-icon :name="$icon" /></span></div>
    @if($state)
        @if($value === null)
            <p class="metric-value metric-value--empty">Belum ada</p>
        @else
            <div class="metric-state">
                <x-badge :tone="$value === 'Menyala' ? 'green' : 'neutral'" :dot="$value === 'Menyala'">{{ $value }}</x-badge>
            </div>
        @endif
    @else
        <div class="gauge-wrap">
            <svg class="gauge-svg" viewBox="0 0 180 100" aria-hidden="true" focusable="false">
                <path class="gauge-track" d="M 22 88 A 68 68 0 0 1 158 88" />
                @if($numeric !== null && $percent > 0)
                    <path class="gauge-fill gauge-fill--{{ $tone }}" d="M 22 88 A 68 68 0 0 1 158 88" pathLength="100" stroke-dasharray="100" stroke-dashoffset="{{ $dashOffset }}" />
                @endif
            </svg>
            <div class="gauge-center">
                @if($numeric !== null)
                    <p class="gauge-value">{{ $value }}@if($unit)<small>{{ $unit }}</small>@endif</p>
                @else
                    <p class="gauge-empty">Belum ada</p>
                @endif
            </div>
            <div class="gauge-scale" aria-hidden="true">
                <span>{{ $min }}{{ $scaleSuffix }}</span>
                <span>{{ $scaleMax }}{{ $scaleSuffix }}</span>
            </div>
        </div>
    @endif
    <p class="metric-foot"><x-icon name="clock" /><span>{{ $note }}</span></p>
</article>
