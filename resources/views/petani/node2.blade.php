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

<turbo-frame id="petani-node2-live" refresh="morph" data-turbo-refresh-interval="15000">
    @if(! $lastRecorded)
        <x-connection-notice />
    @endif
    <div class="stack">
        <div class="metrics-grid metrics-grid--3">
            <x-metric-tile label="Tanah" :value="$val('soil_moisture')" unit="%" icon="leaf" :note="$note('soil_moisture')" />
            <x-metric-tile label="Tanah 2" :value="$val('soil_moisture2')" unit="%" icon="leaf" :note="$note('soil_moisture2')" />
            <x-metric-tile label="Tanah 3" :value="$val('soil_moisture3')" unit="%" icon="leaf" :note="$note('soil_moisture3')" />
            <x-metric-tile label="Tandon" :value="$val('water_level')" unit="%" icon="tank" tone="blue" :note="$note('water_level')" />
            <x-metric-tile label="Pompa" :value="$pumpState" icon="power" :tone="$pumpState === 'Menyala' ? 'green' : 'neutral'" :state="true" :note="$note('pump')" />
        </div>
        <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Ambang tersimpan</h2></div></div><div class="panel-body"><dl class="definition-list"><div><dt>Mode</dt><dd>{{ $monitorSettings['pump_mode'] === 'manual' ? 'Manual' : 'Otomatis' }}</dd></div><div><dt>Mulai</dt><dd>&lt; {{ $monitorSettings['soil_on'] }}%</dd></div><div><dt>Berhenti</dt><dd>&gt; {{ $monitorSettings['soil_off'] }}%</dd></div><div><dt>Tandon min</dt><dd>{{ $monitorSettings['tank_min'] }}%</dd></div></dl></div></section>
    </div>
</turbo-frame>
@endsection
