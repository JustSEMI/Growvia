@extends('layouts.petani')
@section('title', 'Iklim - Growvia')
@section('page', 'Iklim')

@php
    $tz = config('app.timezone');
    $val = fn (string $metric): ?string => isset($latest[$metric]) ? number_format((float) $latest[$metric]->value, 1) : null;
    $note = fn (string $metric): string => isset($latest[$metric])
        ? 'Terakhir '.$latest[$metric]->recorded_at->timezone($tz)->format('d/m H:i T')
        : 'Menunggu data sensor';
    $state = fn (string $metric): ?string => isset($latest[$metric])
        ? ((float) $latest[$metric]->value >= 0.5 ? 'Menyala' : 'Mati')
        : null;
@endphp

@section('content')
<x-page-header title="Iklim." description="Suhu, kelembapan, dan kipas: angka terakhir dari database.">
    <x-slot:eyebrow>Device 1 / Iklim</x-slot:eyebrow>
    <x-slot:actions>
        @if($live)
            <x-badge tone="green" :dot="true">Live</x-badge>
        @elseif($lastRecorded)
            <x-badge :dot="true">Terakhir {{ \Illuminate\Support\Carbon::parse($lastRecorded)->timezone($tz)->format('d/m H:i T') }}</x-badge>
        @else
            <x-badge>Menunggu data</x-badge>
        @endif
    </x-slot:actions>
</x-page-header>

<turbo-frame id="petani-node1-live" refresh="morph" data-turbo-refresh-interval="15000">
    @if(! $lastRecorded)
        <x-connection-notice />
    @endif
    <div class="stack">
        <div class="metrics-grid">
            <x-metric-tile label="Suhu" :value="$val('temperature')" unit="°C" icon="thermometer" tone="amber" :note="$note('temperature')" />
            <x-metric-tile label="Lembap" :value="$val('humidity')" unit="%" icon="drop" tone="blue" :note="$note('humidity')" />
            <x-metric-tile label="Suhu 2" :value="$val('temperature2')" unit="°C" icon="thermometer" tone="amber" :note="$note('temperature2')" />
            <x-metric-tile label="Lembap 2" :value="$val('humidity2')" unit="%" icon="drop" tone="blue" :note="$note('humidity2')" />
            <x-metric-tile label="Kipas 1" :value="$state('fan1')" icon="power" tone="neutral" :note="isset($latest['fan1']) ? 'Status perangkat' : 'Menunggu data sensor'" />
            <x-metric-tile label="Kipas 2" :value="$state('fan2')" icon="power" tone="neutral" :note="isset($latest['fan2']) ? 'Status perangkat' : 'Menunggu data sensor'" />
        </div>

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
                <div class="panel-footer">Rata-rata sensor suhu Node 1 · disegarkan otomatis tiap 15 detik.</div>
            </section>
        @else
            <x-empty-chart title="Tren suhu udara" subtitle="Belum ada cukup data suhu di 6 jam terakhir." node="Device 1" period="6 jam" />
        @endif

        <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Target tersimpan</h2></div></div><div class="panel-body"><dl class="definition-list"><div><dt>Suhu</dt><dd>{{ $monitorSettings['th_suhu_min'] }}–{{ $monitorSettings['th_suhu_max'] }} °C</dd></div><div><dt>Lembap</dt><dd>{{ $monitorSettings['th_hum_min'] }}–{{ $monitorSettings['th_hum_max'] }}%</dd></div></dl></div></section>
    </div>
</turbo-frame>
@endsection
