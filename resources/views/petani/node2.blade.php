@extends('layouts.petani')
@section('title', 'Tanah & air - Growvia')
@section('page', 'Tanah & air')

@php
    $tz = config('app.timezone');
    $val = fn (string $metric): ?string => isset($latest[$metric]) ? number_format((float) $latest[$metric]->value, 1) : null;
    $note = fn (string $metric): string => isset($latest[$metric])
        ? 'Terakhir '.$latest[$metric]->recorded_at->timezone($tz)->format('d/m H:i T')
        : 'Menunggu data sensor';
    $pumpState = isset($latest['pump'])
        ? ((float) $latest['pump']->value >= 0.5 ? 'Menyala' : 'Mati')
        : null;
@endphp

@section('content')
<x-page-header title="Tanah & air." description="Tanah, tandon, dan pompa: angka terakhir dari database.">
    <x-slot:eyebrow>Device 2 / Tanah &amp; air</x-slot:eyebrow>
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

<turbo-frame id="petani-node2-live" refresh="morph" data-turbo-refresh-interval="15000">
    @if(! $lastRecorded)
        <x-connection-notice />
    @endif
    <div class="stack">
        <section aria-label="Sensor kelembapan tanah">
            <div class="section-heading section-heading--ruled"><h2>Kelembapan tanah</h2></div>
            <div class="metrics-grid metrics-grid--3 metrics-grid--open">
                <x-metric-tile label="Tanah" :value="$val('soil_moisture')" unit="%" icon="leaf" :note="$note('soil_moisture')" />
                <x-metric-tile label="Tanah 2" :value="$val('soil_moisture2')" unit="%" icon="leaf" :note="$note('soil_moisture2')" />
                <x-metric-tile label="Tanah 3" :value="$val('soil_moisture3')" unit="%" icon="leaf" :note="$note('soil_moisture3')" />
            </div>
        </section>

        <div class="two-columns">
            <section class="panel" aria-labelledby="petani-pump-control-title">
                <div class="panel-header">
                    <div>
                        <h2 id="petani-pump-control-title" class="panel-title">Kontrol pompa</h2>
                        <p class="panel-subtitle">Irigasi / Device 2</p>
                    </div>
                    <div class="actuator-strip">
                        <div class="actuator-chip {{ $pumpState === 'Menyala' ? 'is-on' : '' }}" title="Pompa · {{ $note('pump') }}">
                            <span class="actuator-dot" aria-hidden="true"></span>
                            <span class="actuator-chip-name">Pompa</span>
                            <span class="actuator-chip-value">{{ $pumpState ?? 'Belum ada' }}</span>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <x-pump-control
                        :reading="$latest['pump'] ?? null"
                        :settings="$monitorSettings"
                        :action="route('petani.control')"
                        hint-id="petani-pump-hint" />
                </div>
            </section>

            <div class="stack">
                <section aria-label="Isi tandon air">
                    <div class="section-heading section-heading--ruled"><h2>Tandon air</h2></div>
                    <div class="metrics-grid metrics-grid--open">
                        <x-metric-tile label="Tandon" :value="$val('water_level')" unit="%" icon="tank" tone="blue" :note="$note('water_level')" />
                    </div>
                </section>

                <section class="panel status-panel" aria-label="Ambang tersimpan">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Ambang tersimpan</h2>
                            <p class="panel-subtitle">Aturan penyiraman dan batas tandon.</p>
                        </div>
                        <span class="icon-tile icon-tile--blue"><x-icon name="tank" /></span>
                    </div>
                    <div class="panel-body">
                        <dl class="definition-list">
                            <div><dt>Mode pompa</dt><dd>{{ $monitorSettings['pump_mode'] === 'manual' ? 'Manual' : 'Otomatis' }}</dd></div>
                            <div><dt>Mulai menyiram</dt><dd>&lt; {{ $monitorSettings['soil_on'] }}%</dd></div>
                            <div><dt>Berhenti menyiram</dt><dd>&gt; {{ $monitorSettings['soil_off'] }}%</dd></div>
                            <div><dt>Tandon minimum</dt><dd>{{ $monitorSettings['tank_min'] }}%</dd></div>
                            <div><dt>Durasi maks pompa</dt><dd>{{ $monitorSettings['pump_max_run'] }} menit</dd></div>
                        </dl>
                    </div>
                    <div class="panel-footer">
                        <span>Diatur oleh administrator</span>
                        <a class="text-link" href="{{ route('petani.edukasi') }}">Panduan penyiraman <x-icon name="arrow" /></a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</turbo-frame>
@endsection
