@extends('layouts.admin')
@section('title', 'Ringkasan — Growvia')
@section('page', 'Ringkasan')

@section('content')
<x-page-header title="Backend Growvia." description="Laravel ini cuma pengolahan data. Fitur lengkapnya ada di aplikasi mobile.">
    <x-slot:eyebrow>Ruang kerja / Ringkasan</x-slot:eyebrow>
    <x-slot:actions><x-badge :dot="true">{{ $lastRecorded ? 'Ada data' : 'Menunggu data' }}</x-badge></x-slot:actions>
</x-page-header>

<div class="stack">
    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Status data</h2><p class="panel-subtitle">Baris tersimpan di tabel readings.</p></div></div>
        <div class="panel-body">
            <dl class="definition-list">
                <div><dt>Node 1 (iklim)</dt><dd>{{ $counts['node1'] }} baris</dd></div>
                <div><dt>Node 2 (tanah &amp; air)</dt><dd>{{ $counts['node2'] }} baris</dd></div>
                <div><dt>Pembacaan terakhir</dt><dd>{{ $lastRecorded ?: 'Belum ada' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="panel" aria-labelledby="activity-title">
        <div class="panel-header"><div><h2 id="activity-title" class="panel-title">Aktivitas terbaru</h2><p class="panel-subtitle">Catatan akun, sesi, dan pengaturan aplikasi.</p></div><a class="text-link" href="{{ route('activity.index') }}">Lihat semua <x-icon name="arrow" /></a></div>
        <x-activity-list :activities="$recentActivities" />
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Nilai terakhir per metrik</h2><p class="panel-subtitle">Angka mentah dari sensor, tanpa grafik. Detail di mobile.</p></div><a class="text-link" href="{{ route('dashboard.riwayat') }}">Riwayat lengkap <x-icon name="arrow" /></a></div>
        <div class="panel-body">
            @if(empty($latest))
                <p class="panel-subtitle">Belum ada pembacaan. Jalankan <code>php artisan mqtt:consume</code> dan kirim data dari ESP32.</p>
            @else
                <dl class="definition-list">
                    @foreach(['node1' => 'Device 1', 'node2' => 'Device 2'] as $node => $label)
                        @if(!empty($latest[$node]))
                            @foreach($latest[$node] as $metric => $reading)
                                <div><dt>{{ $label }} · {{ $metric }}</dt><dd>{{ $reading->value }} {{ $reading->unit }} <span class="text-muted text-sm">({{ $reading->recorded_at }})</span></dd></div>
                            @endforeach
                        @endif
                    @endforeach
                </dl>
            @endif
        </div>
    </section>
</div>
@endsection
