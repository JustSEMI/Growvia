@extends('layouts.admin')
@section('title', 'Ringkasan — Growvia')
@section('page', 'Ringkasan')

@section('content')
<x-page-header title="Gambaran kebun hari ini." description="Pantau lingkungan tumbuh, perangkat, dan rencana perawatan kebun.">
    <x-slot:eyebrow>Ruang kerja / Ringkasan</x-slot:eyebrow>
    <x-slot:actions><x-badge :dot="true">Menunggu data</x-badge><a href="{{ route('dashboard') }}" class="button button--secondary"><x-icon name="refresh" />Segarkan</a></x-slot:actions>
</x-page-header>

<x-connection-notice :href="route('settings.index').'#mqtt'" />
<div class="metrics-grid" data-reveal>
    <x-metric-tile label="Suhu udara" unit="°C" icon="thermometer" tone="amber" note="Node1 · Belum ada data" />
    <x-metric-tile label="Kelembapan tanah" unit="%" icon="leaf" note="Node2 · Belum ada data" />
    <x-metric-tile label="Isi tandon" unit="%" icon="tank" tone="blue" note="Node2 · Belum ada data" />
    <x-metric-tile label="Status pompa" icon="power" tone="neutral" note="Status perangkat belum tersedia" />
</div>

<div class="dashboard-grid">
    <div class="stack">
        <x-empty-chart :href="route('settings.index').'#mqtt'" data-reveal />
        <section class="panel" aria-labelledby="activity-title" data-reveal>
            <div class="panel-header"><div><h2 class="panel-title" id="activity-title">Aktivitas terbaru</h2><p class="panel-subtitle">Catatan akun, sesi, dan pengaturan aplikasi.</p></div><a class="text-link" href="{{ route('activity.index') }}">Lihat semua <x-icon name="arrow" /></a></div>
            <x-activity-list :activities="$recentActivities" />
        </section>
    </div>
    <div class="stack">
        <x-node-status data-reveal />
        <section class="panel" aria-labelledby="pump-title" data-reveal>
            <div class="panel-header"><div><h2 id="pump-title" class="panel-title">Kontrol pompa</h2><p class="panel-subtitle">Irigasi / Node2</p></div><x-badge>Belum aktif</x-badge></div>
            <div class="panel-body">
                <div class="pump-visual"><span class="pump-ring"><x-icon name="power" /></span></div><p class="pump-status">Menunggu integrasi</p>
                <button type="button" disabled class="button button--wide" aria-describedby="pump-disabled-note"><x-icon name="power" />Nyalakan manual</button>
                <p class="field-hint mt-3" id="pump-disabled-note">Kontrol perangkat belum tersedia. Menyimpan konfigurasi tidak menyalakan pompa.</p>
            </div>
        </section>
    </div>
</div>

<section id="node1" class="section-space" aria-labelledby="climate-title" data-reveal>
    <div class="section-heading"><div><p class="eyebrow">Node1 / Lingkungan tumbuh</p><h2 id="climate-title" class="mt-1">Iklim rumah kaca</h2></div><x-badge>Menunggu data</x-badge></div>
    <div class="metrics-grid">
        <x-metric-tile label="Suhu udara" unit="°C" icon="thermometer" tone="amber" note="Belum ada pembacaan" />
        <x-metric-tile label="Kelembapan udara" unit="%" icon="drop" tone="blue" note="Belum ada pembacaan" />
        <x-metric-tile label="Intensitas cahaya" unit="klux" icon="sun" tone="amber" note="Belum ada pembacaan" />
        <x-metric-tile label="Kadar CO2" unit="ppm" icon="leaf" note="Belum ada pembacaan" />
    </div>
    <section class="panel" aria-labelledby="history-title">
        <div class="table-toolbar"><div><h3 class="panel-title" id="history-title">Riwayat iklim</h3><p class="panel-subtitle">Data historis Node1.</p></div><span class="chart-period">6 jam terakhir</span></div>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Tabel riwayat iklim"><table class="data-table"><thead><tr><th scope="col">Waktu</th><th scope="col">Suhu</th><th scope="col">Kelembapan</th><th scope="col">Cahaya</th><th scope="col">CO2</th></tr></thead><tbody><tr><td colspan="5" class="table-empty"><x-icon name="chart" />Belum ada riwayat sensor.</td></tr></tbody></table></div>
    </section>
</section>

<section id="node2" class="section-space" aria-labelledby="soil-title" data-reveal>
    <div class="section-heading"><div><p class="eyebrow">Node2 / Perawatan kebun</p><h2 id="soil-title" class="mt-1">Tanah &amp; air</h2></div><a class="text-link" href="{{ route('settings.index') }}#soil">Atur ambang <x-icon name="arrow" /></a></div>
    <div class="two-columns">
        <section class="panel" aria-labelledby="configuration-title">
            <div class="panel-header"><div><h3 class="panel-title" id="configuration-title">Konfigurasi tersimpan</h3><p class="panel-subtitle">Belum dikirim atau diterapkan ke perangkat.</p></div><x-icon name="settings" class="text-muted" /></div>
            <div class="panel-body"><dl class="definition-list">
                <div><dt>Mode pompa</dt><dd>{{ $monitorSettings['pump_mode'] === 'manual' ? 'Manual' : 'Otomatis' }}</dd></div>
                <div><dt>Ambang mulai menyiram</dt><dd>&lt; {{ $monitorSettings['soil_on'] }}%</dd></div>
                <div><dt>Ambang berhenti menyiram</dt><dd>&gt; {{ $monitorSettings['soil_off'] }}%</dd></div>
                <div><dt>Minimum air tandon</dt><dd>{{ $monitorSettings['tank_min'] }}%</dd></div>
                <div><dt>Batas durasi pompa</dt><dd>{{ $monitorSettings['pump_max_run'] }} menit</dd></div>
            </dl></div>
        </section>
        <section class="panel" aria-labelledby="pump-history-title"><div class="panel-header"><div><h3 class="panel-title" id="pump-history-title">Riwayat pompa</h3><p class="panel-subtitle">Aksi dan durasi dari perangkat.</p></div><x-icon name="clock" class="text-muted" /></div><div class="empty-state mx-auto"><span class="empty-icon"><x-icon name="power" /></span><h3>Belum ada aktivitas pompa.</h3><p>Status nyala dan mati belum dilaporkan oleh perangkat.</p></div></section>
    </div>
</section>
@endsection
