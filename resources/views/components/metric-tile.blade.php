@props(['label', 'value' => null, 'unit' => '', 'icon' => 'chart', 'tone' => 'green', 'note' => 'Menunggu data sensor', 'state' => false])

<article {{ $attributes->class(['panel', 'metric-card']) }}>
    <div class="metric-top"><h2 class="metric-label">{{ $label }}</h2><span class="icon-tile icon-tile--{{ $tone }}"><x-icon :name="$icon" /></span></div>
    @if($value === null)
        <p class="metric-value metric-value--empty">Belum ada</p>
    @elseif($state)
        <div class="metric-state">
            <x-badge :tone="$value === 'Menyala' ? 'green' : 'neutral'" :dot="$value === 'Menyala'">{{ $value }}</x-badge>
        </div>
    @else
        <p class="metric-value">{{ $value }}@if($unit)<small>{{ $unit }}</small>@endif</p>
    @endif
    <p class="metric-foot"><x-icon name="clock" /><span>{{ $note }}</span></p>
</article>
