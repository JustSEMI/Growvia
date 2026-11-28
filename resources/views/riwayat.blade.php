@extends('layouts.admin')
@section('title', 'Riwayat - Growvia')
@section('page', 'Riwayat')

@section('content')
<x-page-header title="Data mentah." description="Tabel pembacaan langsung dari database. Geser ke samping untuk melihat semua metrik.">
    <x-slot:eyebrow>Ruang kerja / Riwayat</x-slot:eyebrow>
    <x-slot:actions>
        <a class="button button--secondary" href="{{ route('dashboard.riwayat.export', array_filter(['node' => $node, 'from' => $from, 'to' => $to])) }}">Export CSV</a>
    </x-slot:actions>
</x-page-header>
<x-alerts />

<div class="ledger-page">
<form class="panel form-section form-section--neutral section-space" method="GET" action="{{ route('dashboard.riwayat') }}" aria-label="Filter riwayat sensor">
    <div class="panel-body form-grid">
        <div class="field">
            <label class="field-label" for="node-filter">Node</label>
            <select class="field-input" id="node-filter" name="node" data-custom-select @if($errors->has('node')) aria-invalid="true" aria-describedby="node-error" @endif>
                <option value="">Semua node</option>
                <option value="node1" @selected($node === 'node1')>Device 1</option>
                <option value="node2" @selected($node === 'node2')>Device 2</option>
            </select>
            @error('node')<p class="field-error" id="node-error">{{ $message }}</p>@enderror
        </div>
        <x-field name="from" type="date" label="Dari tanggal" :value="$from" />
        <x-field name="to" type="date" label="Sampai tanggal" :value="$to" />
    </div>
    <div class="form-actions">
        @if($filtered)
            <a class="button button--secondary" href="{{ route('dashboard.riwayat') }}">Hapus filter</a>
        @endif
        <button class="button" type="submit">Terapkan filter</button>
    </div>
</form>

<section class="panel history-table-panel mt-6">
    <div class="table-toolbar">
        <div><h2 class="panel-title">Pembacaan tercatat</h2><p class="panel-subtitle">{{ $paginator->total() }} waktu · Zona waktu {{ $timezone }}</p></div>
    </div>
    <div class="history-list">
        @forelse($rows as $row)
            <article class="history-card">
                <header><strong>{{ $row['time'] }}</strong><span>{{ $row['node'] ?? '' }}</span></header>
                <dl>
                    <div><dt>Suhu</dt><dd>{{ $row['temperature'] ?? '--' }} °C</dd></div>
                    <div><dt>Lembap</dt><dd>{{ $row['humidity'] ?? '--' }}%</dd></div>
                    <div><dt>Suhu 2</dt><dd>{{ $row['temperature2'] ?? '--' }} °C</dd></div>
                    <div><dt>Lembap 2</dt><dd>{{ $row['humidity2'] ?? '--' }}%</dd></div>
                    <div><dt>Tanah</dt><dd>{{ $row['soil_moisture'] ?? '--' }}%</dd></div>
                    <div><dt>Tanah 2</dt><dd>{{ $row['soil_moisture2'] ?? '--' }}%</dd></div>
                    <div><dt>Tanah 3</dt><dd>{{ $row['soil_moisture3'] ?? '--' }}%</dd></div>
                    <div><dt>Tandon</dt><dd>{{ $row['water_level'] ?? '--' }}%</dd></div>
                    <div><dt>Pompa</dt><dd>{{ $row['pump'] ?? '--' }}</dd></div>
                    <div><dt>Kipas</dt><dd>{{ $row['fan1'] ?? '--' }}</dd></div>
                </dl>
            </article>
        @empty
            <p class="table-empty">Belum ada pembacaan.</p>
        @endforelse
    </div>
    <div class="table-wrap history-table-wrap" tabindex="0" role="region" aria-label="Tabel riwayat pembacaan">
        <table class="data-table data-table--scroll">
            <thead><tr>
                <th scope="col">Waktu</th><th scope="col">Suhu</th><th scope="col">Lembap</th><th scope="col">Suhu 2</th><th scope="col">Lembap 2</th><th scope="col">Tanah</th><th scope="col">Tanah 2</th><th scope="col">Tanah 3</th><th scope="col">Tandon</th><th scope="col">Pompa</th><th scope="col">Kipas</th>
            </tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr><td>{{ $row['time'] }}</td><td>{{ $row['temperature'] ?? '--' }}</td><td>{{ $row['humidity'] ?? '--' }}</td><td>{{ $row['temperature2'] ?? '--' }}</td><td>{{ $row['humidity2'] ?? '--' }}</td><td>{{ $row['soil_moisture'] ?? '--' }}</td><td>{{ $row['soil_moisture2'] ?? '--' }}</td><td>{{ $row['soil_moisture3'] ?? '--' }}</td><td>{{ $row['water_level'] ?? '--' }}</td><td>{{ $row['pump'] ?? '--' }}</td><td>{{ $row['fan1'] ?? '--' }}</td></tr>
                @empty
                    <tr><td colspan="11" class="table-empty">Belum ada pembacaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $paginator->links('vendor.pagination.growvia', ['noun' => 'data']) }}
</section>
</div>
@endsection
