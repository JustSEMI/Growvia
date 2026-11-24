@extends('layouts.admin')
@section('title', 'MQTT - Growvia')
@section('page', 'MQTT')

@section('content')
<x-page-header title="Sumber data MQTT." description="ESP32 menerbitkan payload ke broker, lalu Laravel menyimpannya ke tabel readings.">
    <x-slot:eyebrow>Ruang kerja / MQTT</x-slot:eyebrow>
    <x-slot:actions>
        @if($live)
            <x-badge tone="green" :dot="true">Aktif</x-badge>
        @elseif($lastRecorded)
            <x-badge tone="amber">Historis</x-badge>
        @else
            <x-badge>Menunggu data</x-badge>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="stack">
    <div class="two-columns">
        <section class="panel status-panel" aria-labelledby="mqtt-broker-title">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title" id="mqtt-broker-title">Konfigurasi broker</h2>
                    <p class="panel-subtitle">Mengikuti Pengaturan admin (fallback ke .env bila kosong).</p>
                </div>
                <span class="icon-tile"><x-icon name="connection" /></span>
            </div>
            <div class="panel-body">
                <dl class="definition-list">
                    <div><dt>Host &amp; port</dt><dd><code>{{ $mqtt['host'] }}:{{ $mqtt['port'] }}</code></dd></div>
                    <div><dt>Client ID</dt><dd><code>{{ $mqtt['clientId'] }}</code></dd></div>
                    <div><dt>Autentikasi</dt><dd>{{ $mqtt['hasAuth'] ? 'Username & kata sandi' : 'Tanpa autentikasi' }}</dd></div>
                </dl>
            </div>
            <div class="panel-footer">
                <span>Kredensial kata sandi dibaca dari .env</span>
                <a class="text-link" href="{{ route('settings.index') }}">Ubah di Pengaturan <x-icon name="arrow" /></a>
            </div>
        </section>

        <section class="panel status-panel" aria-labelledby="mqtt-topics-title">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title" id="mqtt-topics-title">Topik &amp; worker</h2>
                    <p class="panel-subtitle">Topik yang dilanggan oleh proses consumer.</p>
                </div>
                <span class="icon-tile icon-tile--blue"><x-icon name="shield" /></span>
            </div>
            <div class="panel-body">
                <div class="api-topics-list">
                    @foreach($topics as $topic)
                        <code class="api-code">{{ $topic }}</code>
                    @endforeach
                </div>
                <div class="api-url-row mt-6">
                    <code class="api-code">php artisan mqtt:consume</code>
                    <button type="button" class="button button--secondary" data-copy-text="php artisan mqtt:consume" hidden>Salin perintah</button>
                </div>
            </div>
            <div class="panel-footer">
                <span>QoS 0 · payload JSON tervalidasi per metrik</span>
            </div>
        </section>
    </div>

    <section class="panel" aria-labelledby="mqtt-ingest-title">
        <div class="panel-header">
            <div>
                <h2 class="panel-title" id="mqtt-ingest-title">Hasil ingest</h2>
                <p class="panel-subtitle">Jumlah baris yang sudah tersimpan di tabel readings.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="metrics-grid metrics-grid--3">
                <div class="chart-stat">
                    <span>Node 1 (iklim)</span>
                    <strong>{{ number_format($counts['node1']) }} baris</strong>
                </div>
                <div class="chart-stat">
                    <span>Node 2 (tanah &amp; air)</span>
                    <strong>{{ number_format($counts['node2']) }} baris</strong>
                </div>
                <div class="chart-stat">
                    <span>Total tersimpan</span>
                    <strong>{{ number_format($counts['node1'] + $counts['node2']) }} baris</strong>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <span>Pembacaan terakhir: {{ $lastRecorded ? \Illuminate\Support\Carbon::parse($lastRecorded)->timezone(config('app.timezone'))->format('d/m H:i:s T') : 'Belum ada' }}</span>
            <a class="text-link" href="{{ route('dashboard.riwayat') }}">Lihat data mentah <x-icon name="arrow" /></a>
        </div>
    </section>
</div>
@endsection
