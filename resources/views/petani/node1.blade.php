@extends('layouts.petani')
@section('title', 'Iklim rumah kaca — Growvia')
@section('page', 'Iklim rumah kaca')

@section('content')
<x-page-header title="Lingkungan tumbuh yang tepat." description="Kenali suhu, kelembapan, cahaya, dan CO2 di rumah kaca."><x-slot:eyebrow>Node1 / Iklim rumah kaca</x-slot:eyebrow><x-slot:actions><x-badge :dot="true">Menunggu data</x-badge></x-slot:actions></x-page-header>
<x-connection-notice />
<div class="metrics-grid" data-reveal>
    <x-metric-tile label="Suhu udara" unit="°C" icon="thermometer" tone="amber" />
    <x-metric-tile label="Kelembapan udara" unit="%" icon="drop" tone="blue" />
    <x-metric-tile label="Intensitas cahaya" unit="klux" icon="sun" tone="amber" />
    <x-metric-tile label="Kadar CO2" unit="ppm" icon="leaf" />
</div>
<div class="dashboard-grid">
    <div class="stack"><x-empty-chart data-reveal /><section class="panel" data-reveal><div class="table-toolbar"><div><h2 class="panel-title">Riwayat iklim</h2><p class="panel-subtitle">Belum ada pembacaan yang tersimpan.</p></div><span class="chart-period">6 jam terakhir</span></div><div class="table-wrap" tabindex="0" role="region" aria-label="Tabel riwayat iklim"><table class="data-table"><thead><tr><th scope="col">Waktu</th><th scope="col">Suhu</th><th scope="col">Lembap</th><th scope="col">Cahaya</th><th scope="col">CO2</th></tr></thead><tbody><tr><td colspan="5" class="table-empty"><x-icon name="chart" />Riwayat sensor belum tersedia.</td></tr></tbody></table></div></section></div>
    <div class="stack">
        <section class="panel" data-reveal><div class="panel-header"><div><h2 class="panel-title">Target lingkungan</h2><p class="panel-subtitle">Konfigurasi tersimpan oleh admin.</p></div><span class="icon-tile"><x-icon name="leaf" /></span></div><div class="panel-body"><dl class="definition-list"><div><dt>Suhu udara</dt><dd>{{ $monitorSettings['th_suhu_min'] }}–{{ $monitorSettings['th_suhu_max'] }} °C</dd></div><div><dt>Kelembapan</dt><dd>{{ $monitorSettings['th_hum_min'] }}–{{ $monitorSettings['th_hum_max'] }}%</dd></div><div><dt>Cahaya minimum</dt><dd>{{ $monitorSettings['th_light_min'] }} klux</dd></div><div><dt>Kadar CO2</dt><dd>{{ $monitorSettings['th_co2_min'] }}–{{ $monitorSettings['th_co2_max'] }} ppm</dd></div></dl><p class="subtle-note">Target konfigurasi, bukan hasil pengukuran atau rekomendasi untuk semua jenis tanaman.</p></div></section>
        <aside class="panel" data-reveal><div class="panel-body"><p class="eyebrow">Catatan kebun</p><h2 class="panel-title mt-3">Udara juga perlu diperhatikan.</h2><p class="panel-subtitle mt-3">Ventilasi, naungan, dan jarak tanam ikut memengaruhi lingkungan tumbuh. Sesuaikan perawatan dengan jenis tanaman.</p><a class="text-link mt-4" href="{{ route('petani.edukasi') }}#guide-climate">Baca panduan <x-icon name="arrow" /></a></div></aside>
    </div>
</div>
@endsection
