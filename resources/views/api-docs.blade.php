@extends('layouts.admin')
@section('title', 'API Mobile — Growvia')
@section('page', 'API Mobile')

@section('content')
<x-page-header title="Kontrak mobile." description="Flutter ambil data lewat API ini. Web cuma penampung data.">
    <x-slot:eyebrow>Ruang kerja / API Mobile</x-slot:eyebrow>
</x-page-header>

<div class="stack">
    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Base URL</h2><p class="panel-subtitle">Butuh token Sanctum dari POST /api/v1/auth/login.</p></div></div>
        <div class="panel-body"><code class="api-code">{{ $base }}/api/v1</code></div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Endpoint utama</h2></div></div>
        <div class="panel-body">
            <dl class="definition-list">
                <div><dt>GET /readings</dt><dd>List pembacaan. Query: node, metric, from, to, limit (maks 1000).</dd></div>
                <div><dt>GET /workspace</dt><dd>Status workspace + telemetry_available.</dd></div>
                <div><dt>GET /health</dt><dd>Public, tanpa auth.</dd></div>
            </dl>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Metrik valid ({{ count($metrics) }})</h2></div></div>
        <div class="panel-body"><p class="panel-subtitle">Node: {{ implode(', ', $nodes) }}</p>
        <dl class="definition-list">
            @foreach($metrics as $key => $unit)
                <div><dt>{{ $key }}</dt><dd>{{ $unit }}</dd></div>
            @endforeach
        </dl></div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Contoh respons</h2></div></div>
        <div class="panel-body"><pre class="api-example"><code>{ "data": [{ "id": 1, "node": "node1", "metric": "temperature", "value": 27.41, "unit": "°C", "recorded_at": "2026-11-06T01:00:00+00:00" }], "meta": { "available": true, "count": 1, "source": "rest_api" } }</code></pre></div>
    </section>
</div>
@endsection
