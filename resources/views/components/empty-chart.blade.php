@props(['title' => 'Tren suhu udara', 'subtitle' => 'Belum ada cukup data suhu untuk digambar.', 'node' => 'Device 1', 'period' => '6 jam', 'href' => null, 'linkLabel' => 'Cek koneksi MQTT'])

<section {{ $attributes->class(['panel', 'trend-panel']) }} aria-label="{{ $title }}">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">{{ $title }}</h2>
            <p class="panel-subtitle">{{ $subtitle }}</p>
        </div>
        <span class="chart-period">{{ $period }}</span>
    </div>
    <div class="panel-body">
        <div class="empty-chart-box">
            <span class="icon-tile"><x-icon name="chart" /></span>
            <div>
                <strong>Menunggu minimal 2 titik suhu ({{ $node }})</strong>
                <p>Grafik muncul otomatis begitu sensor mengirim minimal dua pembacaan.</p>
            </div>
        </div>
    </div>
    @if($href)
        <div class="panel-footer">
            <span>Sumber data: MQTT</span>
            <a class="text-link" href="{{ $href }}">{{ $linkLabel }} <x-icon name="arrow" /></a>
        </div>
    @endif
</section>
