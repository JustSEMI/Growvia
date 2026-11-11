@extends('layouts.petani')
@section('title', 'Tanah & air — Growvia')
@section('page', 'Tanah & air')

@section('content')
<x-page-header title="Tanah & air." description="Tanah, tandon, pompa — angka terakhir dari database."><x-slot:eyebrow>Device 2 / Tanah &amp; air</x-slot:eyebrow></x-page-header>
<x-connection-notice />
<div class="metrics-grid">
    <x-metric-tile label="Tanah" unit="%" icon="leaf" />
    <x-metric-tile label="Suhu tanah" unit="°C" icon="thermometer" tone="amber" />
    <x-metric-tile label="Tandon" unit="%" icon="tank" tone="blue" />
    <x-metric-tile label="Pompa" icon="power" tone="neutral" note="Status perangkat" />
</div>
<section class="panel"><div class="panel-header"><div><h2 class="panel-title">Ambang tersimpan</h2></div></div><div class="panel-body"><dl class="definition-list"><div><dt>Mode</dt><dd>{{ $monitorSettings['pump_mode'] === 'manual' ? 'Manual' : 'Otomatis' }}</dd></div><div><dt>Mulai</dt><dd>&lt; {{ $monitorSettings['soil_on'] }}%</dd></div><div><dt>Berhenti</dt><dd>&gt; {{ $monitorSettings['soil_off'] }}%</dd></div><div><dt>Tandon min</dt><dd>{{ $monitorSettings['tank_min'] }}%</dd></div></dl></div></section>
@endsection
