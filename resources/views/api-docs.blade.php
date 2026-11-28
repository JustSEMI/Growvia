@extends('layouts.admin')
@section('title', 'API Mobile - Growvia')
@section('page', 'API Mobile')

@section('content')
<x-page-header title="Kontrak API Mobile." description="Endpoint REST v1 dan autentikasi Sanctum untuk klien mobile.">
    <x-slot:eyebrow>Ruang kerja / API Mobile</x-slot:eyebrow>
    <x-slot:actions>
        <x-badge tone="green">API v1</x-badge>
        <x-badge>Sanctum Bearer</x-badge>
    </x-slot:actions>
</x-page-header>

<div class="stack">
    <div class="two-columns">
        <section class="panel" aria-labelledby="api-base-title">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title" id="api-base-title">Base URL &amp; autentikasi</h2>
                    <p class="panel-subtitle">Semua endpoint terproteksi memakai bearer token dari <code>POST /auth/login</code>.</p>
                </div>
                <span class="icon-tile"><x-icon name="shield" /></span>
            </div>
            <div class="panel-body">
                <div class="api-url-row">
                    <code class="api-code">{{ $base }}/api/v1</code>
                    <button type="button" class="button button--secondary" data-copy-text="{{ $base }}/api/v1" hidden>Salin URL</button>
                </div>
                <dl class="definition-list mt-6">
                    <div><dt>Header wajib</dt><dd><code>Authorization: Bearer &lt;token&gt;</code></dd></div>
                    <div><dt>Format respons</dt><dd><code>Accept: application/json</code></dd></div>
                    <div><dt>Transport</dt><dd>Wajib HTTPS di production</dd></div>
                </dl>
            </div>
        </section>

        <section class="panel" aria-labelledby="api-sample-title">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title" id="api-sample-title">Contoh respons</h2>
                    <p class="panel-subtitle"><code>GET /api/v1/readings?node=node1</code></p>
                </div>
                <span class="chart-period">200 OK</span>
            </div>
            <div class="panel-body">
                <pre class="api-example"><code>{
  "data": [
    {
      "id": 1,
      "node": "node1",
      "metric": "temperature",
      "value": 27.41,
      "unit": "°C",
      "recorded_at": "2026-11-06T01:00:00+00:00"
    }
  ],
  "meta": {
    "available": true,
    "count": 1,
    "source": "rest_api"
  }
}</code></pre>
            </div>
        </section>
    </div>

    <section class="panel" aria-labelledby="api-endpoints-title">
        <div class="panel-header">
            <div>
                <h2 class="panel-title" id="api-endpoints-title">Daftar endpoint ({{ count($endpoints) }})</h2>
                <p class="panel-subtitle">Seluruh rute aktif di bawah prefix <code>/api/v1</code>.</p>
            </div>
        </div>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Daftar endpoint API Mobile">
            <table class="data-table api-table">
                <thead>
                    <tr>
                        <th scope="col">Method</th>
                        <th scope="col">Path</th>
                        <th scope="col">Akses</th>
                        <th scope="col">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($endpoints as $endpoint)
                        <tr>
                            <td><code class="api-method api-method--{{ strtolower($endpoint['method']) }}">{{ $endpoint['method'] }}</code></td>
                            <td><code class="api-path">{{ $endpoint['path'] }}</code></td>
                            <td><x-badge :tone="$endpoint['tone']">{{ $endpoint['access'] }}</x-badge></td>
                            <td>{{ $endpoint['summary'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" aria-labelledby="api-control-sample-title">
        <div class="panel-header">
            <div>
                <h2 class="panel-title" id="api-control-sample-title">Kontrol perangkat mobile</h2>
                <p class="panel-subtitle"><code>POST /api/v1/devices/control</code> untuk manual pompa/kipas atau kembali ke mode otomatis pompa.</p>
            </div>
            <span class="chart-period">200 / 503</span>
        </div>
        <div class="panel-body">
            <pre class="api-example"><code>// Nyalakan pompa secara manual (node2)
{ "node": "node2", "actuator": "pump", "mode": "manual", "state": 1 }

// Kembalikan pompa ke mode otomatis (mengikuti soil_on, soil_off, tank_min)
{ "node": "node2", "actuator": "pump", "mode": "auto" }

// Kontrol kipas (node1: fan1)
{ "node": "node1", "actuator": "fan1", "mode": "manual", "state": 1 }</code></pre>
        </div>
    </section>

    <section class="panel" aria-labelledby="api-metrics-title">
        <div class="panel-header">
            <div>
                <h2 class="panel-title" id="api-metrics-title">Metrik valid ({{ count($metrics) }})</h2>
                <p class="panel-subtitle">Node yang didukung: <code>{{ implode('</code>, <code>', $nodes) }}</code></p>
            </div>
        </div>
        <div class="panel-body">
            <div class="api-metrics-grid">
                @foreach($metrics as $key => $unit)
                    <div class="api-metric-item">
                        <code>{{ $key }}</code>
                        <span>{{ $unit }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
