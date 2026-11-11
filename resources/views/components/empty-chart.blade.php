@props(['title' => 'Tren suhu udara', 'subtitle' => 'Riwayat pembacaan sensor', 'node' => 'Device 1', 'period' => '24 jam', 'href' => null])

<section {{ $attributes->class(['panel']) }} aria-label="{{ $title }}">
    <div class="panel-header"><div><h2 class="panel-title">{{ $title }}</h2><p class="panel-subtitle">{{ $subtitle }}</p></div><span class="chart-period">{{ $period }}</span></div>
    <div class="panel-body"><p class="panel-subtitle">Detail grafik ada di aplikasi mobile.</p></div>
</section>
