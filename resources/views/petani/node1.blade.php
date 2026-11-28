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
<x-alerts />

<turbo-frame id="petani-node1-live" refresh="morph" data-turbo-refresh-interval="15000">
    @if(! $lastRecorded)
        <x-connection-notice />
    @endif
    <div class="stack">
        <div class="actuator-strip actuator-strip--bar" aria-label="Status kipas">
            @php($fanVal = $state('fan1'))
            <div class="actuator-chip {{ $fanVal === 'Menyala' ? 'is-on' : '' }}">
                <span class="actuator-dot" aria-hidden="true"></span>
                <span class="actuator-chip-name">Kipas</span>
                <span class="actuator-chip-value">{{ $fanVal ?? 'Belum ada' }}</span>
                <span class="actuator-chip-time">{{ $note('fan1') }}</span>
            </div>
        </div>

        <div class="metrics-grid metrics-grid--2 metrics-grid--open">
            <x-metric-tile label="Suhu" :value="$val('temperature')" unit="°C" icon="thermometer" tone="amber" :note="$note('temperature')" />
            <x-metric-tile label="Lembap" :value="$val('humidity')" unit="%" icon="drop" tone="blue" :note="$note('humidity')" />
            <x-metric-tile label="Suhu 2" :value="$val('temperature2')" unit="°C" icon="thermometer" tone="amber" :note="$note('temperature2')" />
            <x-metric-tile label="Lembap 2" :value="$val('humidity2')" unit="%" icon="drop" tone="blue" :note="$note('humidity2')" />
        </div>

        @if(count($series))
            <x-trend-chart :series="$series" :threshold-max="$monitorSettings['th_suhu_max'] ?? null" />
        @else
            <x-empty-chart title="Tren suhu udara" subtitle="Belum ada cukup data suhu di 6 jam terakhir." node="Device 1" period="6 jam" />
        @endif

        <div class="two-columns">
            <section class="panel" aria-labelledby="petani-fan-control-title">
                <div class="panel-header">
                    <div>
                        <h2 id="petani-fan-control-title" class="panel-title">Kontrol kipas</h2>
                        <p class="panel-subtitle">Pendingin / Device 1</p>
                    </div>
                    <div class="actuator-strip">
                        <div class="actuator-chip {{ $fanVal === 'Menyala' ? 'is-on' : '' }}" title="Kipas · {{ $note('fan1') }}">
                            <span class="actuator-dot" aria-hidden="true"></span>
                            <span class="actuator-chip-name">Kipas</span>
                            <span class="actuator-chip-value">{{ $fanVal ?? 'Belum ada' }}</span>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <x-actuator-control
                        node="node1"
                        actuator="fan1"
                        actuator-label="Kipas"
                        device-label="Device 1"
                        :reading="$latest['fan1'] ?? null"
                        :settings="$monitorSettings"
                        :action="route('petani.node1.control')"
                        hint-id="petani-fan1-hint"
                        manual-note="Kipas Node 1 · pin GPIO 5 &amp; GPIO 17." />
                </div>
            </section>

            <section class="panel status-panel" aria-label="Target tersimpan">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Target tersimpan</h2>
                        <p class="panel-subtitle">Ambang konfigurasi untuk iklim rumah kaca.</p>
                    </div>
                    <span class="icon-tile icon-tile--amber"><x-icon name="thermometer" /></span>
                </div>
                <div class="panel-body">
                    <dl class="definition-list">
                        <div><dt>Rentang suhu</dt><dd>{{ $monitorSettings['th_suhu_min'] }} - {{ $monitorSettings['th_suhu_max'] }} °C</dd></div>
                        <div><dt>Rentang kelembapan</dt><dd>{{ $monitorSettings['th_hum_min'] }} - {{ $monitorSettings['th_hum_max'] }}%</dd></div>
                        <div><dt>Cahaya minimum</dt><dd>{{ $monitorSettings['th_light_min'] }} klux</dd></div>
                        <div><dt>Interval pembaruan</dt><dd>{{ $monitorSettings['update_interval'] }} detik</dd></div>
                    </dl>
                </div>
                <div class="panel-footer">
                    <span>Diatur oleh administrator</span>
                    <a class="text-link" href="{{ route('petani.node2') }}">Lihat Device 2 (Tanah &amp; air) <x-icon name="arrow" /></a>
                </div>
            </section>
        </div>
    </div>
</turbo-frame>
@endsection
