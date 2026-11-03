@props(['title' => 'Tren suhu udara', 'subtitle' => 'Riwayat pembacaan sensor', 'node' => 'Node1', 'period' => '24 jam', 'href' => null])

<section {{ $attributes->class(['panel']) }} aria-label="{{ $title }}">
    <div class="panel-header"><div><h2 class="panel-title">{{ $title }}</h2><p class="panel-subtitle">{{ $subtitle }}</p></div><span class="chart-period">{{ $period }}</span></div>
    <div class="chart-placeholder">
        <svg class="chart-grid" viewBox="0 0 600 200" preserveAspectRatio="none" aria-hidden="true"><path d="M0 1H600M0 50H600M0 100H600M0 150H600M0 199H600" fill="none" stroke="currentColor" stroke-dasharray="4 5" /></svg>
        <div class="empty-state"><span class="empty-icon"><x-icon name="chart" /></span><h3>Sensornya belum cerita apa-apa.</h3><p>Grafik {{ $node }} belum ada.<br>Sensornya belum nyambung.</p>@if($href)<a class="text-link" href="{{ $href }}">Lihat pengaturan <x-icon name="arrow" /></a>@endif</div>
    </div>
    <div class="panel-footer"><span class="legend"><span class="legend-mark" aria-hidden="true"></span>{{ $node }} / Belum ada pembacaan</span><span>Pembaruan terakhir —</span></div>
</section>
