@extends('layouts.admin')
@section('title', 'Ringkasan — Growvia')
@section('page', 'Ringkasan')

@php
    $formatState = fn ($reading) => (float) $reading->value >= 0.5 ? 'Menyala' : 'Mati';
@endphp

@section('content')
<x-page-header title="Kebun hari ini." description="Nilai terbaru dari perangkat, disegarkan otomatis tiap 15 detik.">
    <x-slot:eyebrow>Ruang kerja / Ringkasan</x-slot:eyebrow>
    <x-slot:actions>
        @if($live)
            <x-badge tone="green" :dot="true">Live</x-badge>
        @elseif($lastRecorded)
            <x-badge :dot="true">Terakhir {{ \Illuminate\Support\Carbon::parse($lastRecorded)->timezone('UTC')->format('d/m H:i') }} UTC</x-badge>
        @else
            <x-badge>Menunggu data</x-badge>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="stack">
    <turbo-frame id="dashboard-live" refresh="morph" data-turbo-refresh-interval="15000">
        <div class="stack">
            @foreach(['node1' => 'Iklim · Device 1', 'node2' => 'Tanah &amp; air · Device 2'] as $node => $sectionLabel)
                <section aria-label="{{ $sectionLabel }}">
                    <div class="section-heading"><h2>{!! $sectionLabel !!}</h2></div>
                    <div class="metrics-grid">
                        @foreach($tiles[$node] as $tile)
                            <x-metric-tile
                                :label="$tile['label']"
                                :value="$tile['value']"
                                :unit="$tile['unit']"
                                :icon="$tile['icon']"
                                :tone="$tile['tone']"
                                :note="$tile['note']" />
                        @endforeach
                        @foreach($states[$node] as $state)
                            @php($reading = $latest[$node][$state['metric']] ?? null)
                            <x-metric-tile
                                :label="$state['label']"
                                :value="$reading ? $formatState($reading) : null"
                                icon="power"
                                tone="neutral"
                                :note="$reading ? 'Status perangkat' : 'Menunggu data sensor'" />
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if(count($series))
                <section class="panel" aria-label="Tren suhu udara">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Tren suhu udara</h2>
                            <p class="panel-subtitle">6 jam terakhir · {{ $series['count'] }} titik · minimum {{ number_format($series['min'], 1) }} °C · maksimum {{ number_format($series['max'], 1) }} °C</p>
                        </div>
                        <span class="chart-period">{{ $series['from'] }}–{{ $series['to'] }}</span>
                    </div>
                    <div class="panel-body">
                        <svg class="trend-chart" viewBox="0 0 560 140" preserveAspectRatio="none" role="img" aria-label="Grafik suhu udara 6 jam terakhir">
                            <line x1="0" y1="24" x2="560" y2="24" class="trend-grid" />
                            <line x1="0" y1="70" x2="560" y2="70" class="trend-grid" />
                            <line x1="0" y1="116" x2="560" y2="116" class="trend-grid" />
                            <polyline class="trend-line" points="{{ $series['points'] }}" />
                        </svg>
                    </div>
                    <div class="panel-footer">Rata-rata sensor suhu Node 1 · sumber data MQTT · disegarkan otomatis tiap 15 detik.</div>
                </section>
            @else
                <x-empty-chart title="Tren suhu udara" subtitle="Belum ada cukup data suhu di 6 jam terakhir." node="Device 1" period="6 jam" />
            @endif

            <section class="panel">
                <div class="panel-header"><div><h2 class="panel-title">Status data</h2><p class="panel-subtitle">Baris tersimpan di tabel readings.</p></div></div>
                <div class="panel-body">
                    <dl class="definition-list">
                        <div><dt>Node 1 (iklim)</dt><dd>{{ $counts['node1'] }} baris</dd></div>
                        <div><dt>Node 2 (tanah &amp; air)</dt><dd>{{ $counts['node2'] }} baris</dd></div>
                        <div><dt>Pembacaan terakhir</dt><dd>{{ $lastRecorded ? \Illuminate\Support\Carbon::parse($lastRecorded)->timezone('UTC')->format('d/m H:i:s').' UTC' : 'Belum ada' }}</dd></div>
                    </dl>
                </div>
            </section>
        </div>
    </turbo-frame>

    <section class="panel" aria-labelledby="activity-title">
        <div class="panel-header"><div><h2 id="activity-title" class="panel-title">Aktivitas terbaru</h2><p class="panel-subtitle">Catatan akun, sesi, dan pengaturan aplikasi.</p></div><a class="text-link" href="{{ route('activity.index') }}">Lihat semua <x-icon name="arrow" /></a></div>
        <x-activity-list :activities="$recentActivities" />
    </section>
</div>
@endsection
