@extends('layouts.admin')
@section('title', 'Riwayat — Growvia')
@section('page', 'Riwayat')

@section('content')
<x-page-header title="Data mentah." description="Tabel vacuum langsung dari database. Scroll ke samping buat lihat semua metrik.">
    <x-slot:eyebrow>Ruang kerja / Riwayat</x-slot:eyebrow>
    <x-slot:actions>
        <form method="GET" action="{{ route('dashboard.riwayat') }}" class="inline-form">
            <label class="sr-only" for="node-filter">Filter node</label>
            <select id="node-filter" name="node" onchange="this.form.submit()">
                <option value="">Semua node</option>
                <option value="node1" @selected($node === 'node1')>Device 1</option>
                <option value="node2" @selected($node === 'node2')>Device 2</option>
            </select>
        </form>
    </x-slot:actions>
</x-page-header>

<section class="panel history-table-panel">
    <div class="history-list">
        @forelse($rows as $row)
            <article class="history-card">
                <header><strong>{{ $row['time'] }}</strong><span>{{ $row['node'] ?? '' }}</span></header>
                <dl>
                    <div><dt>Suhu</dt><dd>{{ $row['temperature'] ?? '—' }} °C</dd></div>
                    <div><dt>Lembap</dt><dd>{{ $row['humidity'] ?? '—' }}%</dd></div>
                    <div><dt>Suhu 2</dt><dd>{{ $row['temperature2'] ?? '—' }} °C</dd></div>
                    <div><dt>Lembap 2</dt><dd>{{ $row['humidity2'] ?? '—' }}%</dd></div>
                    <div><dt>Tanah</dt><dd>{{ $row['soil_moisture'] ?? '—' }}%</dd></div>
                    <div><dt>Tanah 2</dt><dd>{{ $row['soil_moisture2'] ?? '—' }}%</dd></div>
                    <div><dt>Tanah 3</dt><dd>{{ $row['soil_moisture3'] ?? '—' }}%</dd></div>
                    <div><dt>Tandon</dt><dd>{{ $row['water_level'] ?? '—' }}%</dd></div>
                    <div><dt>Pompa</dt><dd>{{ $row['pump'] ?? '—' }}</dd></div>
                    <div><dt>Kipas 1</dt><dd>{{ $row['fan1'] ?? '—' }}</dd></div>
                    <div><dt>Kipas 2</dt><dd>{{ $row['fan2'] ?? '—' }}</dd></div>
                </dl>
            </article>
        @empty
            <p class="table-empty">Belum ada pembacaan.</p>
        @endforelse
    </div>
    <div class="table-wrap history-table-wrap" tabindex="0" role="region" aria-label="Tabel riwayat pembacaan">
        <table class="data-table data-table--scroll">
            <thead><tr>
                <th scope="col">Waktu</th><th scope="col">Suhu</th><th scope="col">Lembap</th><th scope="col">Suhu 2</th><th scope="col">Lembap 2</th><th scope="col">Tanah</th><th scope="col">Tanah 2</th><th scope="col">Tanah 3</th><th scope="col">Tandon</th><th scope="col">Pompa</th><th scope="col">Kipas 1</th><th scope="col">Kipas 2</th>
            </tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr><td>{{ $row['time'] }}</td><td>{{ $row['temperature'] ?? '—' }}</td><td>{{ $row['humidity'] ?? '—' }}</td><td>{{ $row['temperature2'] ?? '—' }}</td><td>{{ $row['humidity2'] ?? '—' }}</td><td>{{ $row['soil_moisture'] ?? '—' }}</td><td>{{ $row['soil_moisture2'] ?? '—' }}</td><td>{{ $row['soil_moisture3'] ?? '—' }}</td><td>{{ $row['water_level'] ?? '—' }}</td><td>{{ $row['pump'] ?? '—' }}</td><td>{{ $row['fan1'] ?? '—' }}</td><td>{{ $row['fan2'] ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="12" class="table-empty">Belum ada pembacaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $paginator->links('vendor.pagination.growvia', ['noun' => 'data']) }}
</section>
@endsection
