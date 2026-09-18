@extends('layouts.petani')
@section('title', 'Kebun saya — Growvia')
@section('page', 'Kebun saya')

@section('content')
<x-page-header title="Selamat datang di kebun." description="Satu tempat untuk memahami kondisi lingkungan tumbuh.">
    <x-slot:eyebrow>Panel petani / Ringkasan</x-slot:eyebrow><x-slot:actions><a class="button button--secondary" href="{{ route('petani.dashboard') }}"><x-icon name="refresh" />Segarkan</a></x-slot:actions>
</x-page-header>
<x-connection-notice />
<div class="two-columns" data-reveal>
    <a href="{{ route('petani.node1') }}" class="panel node-overview"><div class="node-overview-head"><span class="icon-tile icon-tile--amber"><x-icon name="sun" /></span><div><p class="eyebrow">Node1</p><h2>Iklim rumah kaca</h2></div><x-badge>Belum aktif</x-badge></div><div class="node-overview-metrics"><div><span>Suhu udara</span><strong>— <small class="text-sm text-muted">°C</small></strong></div><div><span>Kelembapan</span><strong>— <small class="text-sm text-muted">%</small></strong></div></div><p>Cahaya, suhu, kelembapan, dan CO2.</p><span class="text-link mt-3">Lihat kondisi iklim <x-icon name="arrow" /></span></a>
    <a href="{{ route('petani.node2') }}" class="panel node-overview"><div class="node-overview-head"><span class="icon-tile icon-tile--blue"><x-icon name="drop" /></span><div><p class="eyebrow">Node2</p><h2>Tanah &amp; air</h2></div><x-badge>Belum aktif</x-badge></div><div class="node-overview-metrics"><div><span>Kelembapan tanah</span><strong>— <small class="text-sm text-muted">%</small></strong></div><div><span>Isi tandon</span><strong>— <small class="text-sm text-muted">%</small></strong></div></div><p>Tanah, persediaan air, dan pompa.</p><span class="text-link mt-3">Lihat tanah &amp; air <x-icon name="arrow" /></span></a>
</div>
<div class="dashboard-grid section-space" data-reveal><x-empty-chart title="Tren lingkungan kebun" subtitle="Ringkasan pembacaan suhu udara" /><x-node-status /></div>
<aside class="guide-callout"><x-icon name="book" class="text-forest" /><div><h3>Belum terbiasa membaca angka sensor?</h3><p>Mulai dari panduan singkat tentang suhu, tanah, dan penyiraman.</p></div><a class="text-link" href="{{ route('petani.edukasi') }}">Pelajari <x-icon name="arrow" /></a></aside>
@endsection
