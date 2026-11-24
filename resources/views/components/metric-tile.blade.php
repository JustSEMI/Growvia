@props(['label', 'value' => null, 'unit' => '', 'icon' => 'chart', 'tone' => 'green', 'note' => 'Menunggu data sensor', 'state' => false, 'min' => 0, 'max' => null])

@php
    static $gaugeSeq = 0;
    $scaleMax = $max ?? ($unit === '°C' ? 50 : 100);
    $numeric = ! $state && $value !== null && is_numeric($value) ? (float) $value : null;
    $percent = $numeric !== null
        ? max(0.0, min(100.0, ($numeric - (float) $min) / max(1.0, (float) $scaleMax - (float) $min) * 100.0))
        : 0.0;
    $scaleSuffix = $unit === '°C' ? '°' : $unit;
    $displayText = $numeric !== null ? trim($value.($unit ? ' '.$unit : '')) : 'Belum ada';
    $gaugeId = ! $state ? 'gauge-chart-'.(++$gaugeSeq) : null;
@endphp

<article {{ $attributes->class($state ? ['panel', 'metric-card', 'metric-card--state'] : ['metric-card', 'metric-card--gauge']) }}>
    @if($state)
        <div class="metric-top"><h2 class="metric-label">{{ $label }}</h2><span class="icon-tile icon-tile--{{ $tone }}"><x-icon :name="$icon" /></span></div>
        @if($value === null)
            <p class="metric-value metric-value--empty">Belum ada</p>
        @else
            <div class="metric-state">
                <x-badge :tone="$value === 'Menyala' ? 'green' : 'neutral'" :dot="$value === 'Menyala'">{{ $value }}</x-badge>
            </div>
        @endif
        <p class="metric-foot"><x-icon name="clock" /><span>{{ $note }}</span></p>
    @else
        <div class="gauge-head">
            <span class="gauge-icon icon-tile icon-tile--{{ $tone }}"><x-icon :name="$icon" /></span>
            <h2 class="metric-label">{{ $label }}</h2>
        </div>
        <div
            class="gauge-wrap"
            data-gauge-chart
            data-percent="{{ number_format($percent, 1, '.', '') }}"
            data-display="{{ $displayText }}"
            data-has-value="{{ $numeric !== null ? '1' : '0' }}"
            data-tone="{{ $tone }}"
            role="img"
            aria-label="{{ $label }}: {{ $displayText }}">
            <div id="{{ $gaugeId }}" class="gauge-mount" data-gauge-chart-mount data-turbo-permanent></div>
            <div class="gauge-scale" aria-hidden="true">
                <span>{{ $min }}{{ $scaleSuffix }}</span>
                <span>{{ $scaleMax }}{{ $scaleSuffix }}</span>
            </div>
        </div>
        <p class="gauge-note"><x-icon name="clock" /><span>{{ $note }}</span></p>
    @endif
</article>
