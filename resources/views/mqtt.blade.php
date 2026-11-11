@extends('layouts.admin')
@section('title', 'MQTT — Growvia')
@section('page', 'MQTT')

@section('content')
<x-page-header title="Sumber data." description="ESP32 kirim ke broker, Laravel consume, mobile baca via API.">
    <x-slot:eyebrow>Ruang kerja / MQTT</x-slot:eyebrow>
</x-page-header>

<div class="stack">
    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Konfigurasi broker</h2><p class="panel-subtitle">Dari .env, bukan dari UI.</p></div></div>
        <div class="panel-body">
            <dl class="definition-list">
                <div><dt>Host</dt><dd>{{ $mqtt['host'] }}:{{ $mqtt['port'] }}</dd></div>
                <div><dt>Client ID</dt><dd>{{ $mqtt['clientId'] }}</dd></div>
                <div><dt>Auth</dt><dd>{{ $mqtt['hasAuth'] ? 'Username/password' : 'Tanpa auth' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Topik yang dilanggan</h2></div></div>
        <div class="panel-body">
            @foreach($topics as $topic)
                <p><code>{{ $topic }}</code></p>
            @endforeach
            <p class="panel-subtitle">Perintah: <code>php artisan mqtt:consume</code></p>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Hasil ingest</h2></div></div>
        <div class="panel-body">
            <dl class="definition-list">
                <div><dt>Node 1</dt><dd>{{ $counts['node1'] }} baris</dd></div>
                <div><dt>Node 2</dt><dd>{{ $counts['node2'] }} baris</dd></div>
                <div><dt>Terakhir</dt><dd>{{ $lastRecorded ?: 'Belum ada' }}</dd></div>
            </dl>
        </div>
    </section>
</div>
@endsection
