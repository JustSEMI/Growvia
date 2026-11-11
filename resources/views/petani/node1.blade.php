@extends('layouts.petani')
@section('title', 'Iklim — Growvia')
@section('page', 'Iklim')

@section('content')
<x-page-header title="Iklim." description="Suhu, kelembapan, cahaya — angka terakhir dari database."><x-slot:eyebrow>Device 1 / Iklim</x-slot:eyebrow></x-page-header>
<x-connection-notice />
<div class="metrics-grid">
    <x-metric-tile label="Suhu" unit="°C" icon="thermometer" tone="amber" />
    <x-metric-tile label="Lembap" unit="%" icon="drop" tone="blue" />
    <x-metric-tile label="Cahaya" unit="klux" icon="sun" tone="amber" />
</div>
<section class="panel"><div class="panel-header"><div><h2 class="panel-title">Target tersimpan</h2></div></div><div class="panel-body"><dl class="definition-list"><div><dt>Suhu</dt><dd>{{ $monitorSettings['th_suhu_min'] }}–{{ $monitorSettings['th_suhu_max'] }} °C</dd></div><div><dt>Lembap</dt><dd>{{ $monitorSettings['th_hum_min'] }}–{{ $monitorSettings['th_hum_max'] }}%</dd></div><div><dt>Cahaya min</dt><dd>{{ $monitorSettings['th_light_min'] }} klux</dd></div></dl></div></section>
@endsection
