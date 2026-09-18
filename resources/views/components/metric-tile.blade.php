@props(['label', 'value' => '—', 'unit' => '', 'icon' => 'chart', 'tone' => 'green', 'note' => 'Menunggu data sensor'])

<article {{ $attributes->class(['panel', 'metric-card']) }}>
    <div class="metric-top"><h2 class="metric-label">{{ $label }}</h2><span class="icon-tile icon-tile--{{ $tone }}"><x-icon :name="$icon" /></span></div>
    <p class="metric-value">{{ $value }}@if($unit)<small>{{ $unit }}</small>@endif</p>
    <p class="metric-foot"><x-icon name="clock" />{{ $note }}</p>
</article>
