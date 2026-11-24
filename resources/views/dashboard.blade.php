@extends('layouts.admin')
@section('title', 'Ringkasan - Growvia')
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
            <x-badge :dot="true">Terakhir {{ \Illuminate\Support\Carbon::parse($lastRecorded)->timezone(config('app.timezone'))->format('d/m H:i T') }}</x-badge>
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

            <div class="overview-split">
                @if(count($series))
                    <x-trend-chart :series="$series" />
                @else
                    <x-empty-chart title="Tren suhu udara" subtitle="Belum ada cukup data suhu di 6 jam terakhir." node="Device 1" period="6 jam" :href="route('dashboard.mqtt')" />
                @endif

                <section class="panel status-panel" aria-label="Status data">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Status data</h2>
                            <p class="panel-subtitle">Ringkasan baris di tabel readings.</p>
                        </div>
                        @if($live)
                            <x-badge tone="green" :dot="true">Aktif</x-badge>
                        @elseif($lastRecorded)
                            <x-badge tone="amber">Historis</x-badge>
                        @else
                            <x-badge>Kosong</x-badge>
                        @endif
                    </div>
                    <div class="panel-body">
                        <dl class="definition-list">
                            <div>
                                <dt>Node 1 (iklim)</dt>
                                <dd><span class="status-num">{{ number_format($counts['node1']) }}</span> baris</dd>
                            </div>
                            <div>
                                <dt>Node 2 (tanah &amp; air)</dt>
                                <dd><span class="status-num">{{ number_format($counts['node2']) }}</span> baris</dd>
                            </div>
                            <div>
                                <dt>Total tersimpan</dt>
                                <dd><span class="status-num">{{ number_format($counts['node1'] + $counts['node2']) }}</span> baris</dd>
                            </div>
                            <div>
                                <dt>Pembacaan terakhir</dt>
                                <dd>{{ $lastRecorded ? \Illuminate\Support\Carbon::parse($lastRecorded)->timezone(config('app.timezone'))->format('d/m H:i:s T') : 'Belum ada' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="panel-footer">
                        <a class="text-link" href="{{ route('dashboard.riwayat') }}">Data mentah <x-icon name="arrow" /></a>
                        <a class="text-link" href="{{ route('dashboard.mqtt') }}">Status MQTT <x-icon name="arrow" /></a>
                    </div>
                </section>
            </div>
        </div>
    </turbo-frame>

    <section class="panel" aria-labelledby="activity-title">
        <div class="panel-header"><div><h2 id="activity-title" class="panel-title">Aktivitas terbaru</h2><p class="panel-subtitle">Catatan akun, sesi, dan pengaturan aplikasi.</p></div><a class="text-link" href="{{ route('activity.index') }}">Lihat semua <x-icon name="arrow" /></a></div>
        <x-activity-list :activities="$recentActivities" />
    </section>
</div>
@endsection
