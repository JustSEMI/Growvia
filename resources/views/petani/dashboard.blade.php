@extends('layouts.petani')
@section('title', 'Kebun saya — Growvia')
@section('page', 'Kebun saya')

@section('content')
<x-page-header title="Kebun saya." description="Ringkasan sederhana. Detail grafik ada di mobile.">
    <x-slot:eyebrow>Panel petani / Ringkasan</x-slot:eyebrow>
</x-page-header>
<x-connection-notice />
<div class="two-columns">
    <a href="{{ route('petani.node1') }}" class="panel panel-body"><p class="eyebrow">Device 1</p><h2 class="panel-title">Iklim</h2><p class="panel-subtitle">Suhu, kelembapan, cahaya.</p><span class="text-link">Lihat <x-icon name="arrow" /></span></a>
    <a href="{{ route('petani.node2') }}" class="panel panel-body"><p class="eyebrow">Device 2</p><h2 class="panel-title">Tanah &amp; air</h2><p class="panel-subtitle">Tanah, tandon, pompa.</p><span class="text-link">Lihat <x-icon name="arrow" /></span></a>
</div>
@endsection
