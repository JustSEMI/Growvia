@extends('layouts.admin')
@section('title', 'Ringkasan - Growvia')
@section('page', 'Ringkasan')

@php
    $tz = config('app.timezone');
    $formatState = fn ($reading) => (float) $reading->value >= 0.5 ? 'Menyala' : 'Mati';
@endphp

@section('content')
<x-page-header title="Kebun hari ini." description="Nilai terbaru dari perangkat, disegarkan otomatis tiap 15 detik.">
    <x-slot:eyebrow>Ruang kerja / Ringkasan</x-slot:eyebrow>
    <x-slot:actions>
        @if($live)
            <x-badge tone="green" :dot="true">Live</x-badge>
        @elseif($lastRecorded)
            <x-badge :dot="true">Terakhir {{ \Illuminate\Support\Carbon::parse($lastRecorded)->timezone($tz)->format('d/m H:i T') }}</x-badge>
        @else
            <x-badge>Menunggu data</x-badge>
        @endif
    </x-slot:actions>
</x-page-header>
<x-alerts />

<div class="stack">
    <turbo-frame id="dashboard-live" refresh="morph" data-turbo-refresh-interval="15000">
        <div class="stack">
            @foreach(['node1' => 'Iklim · Device 1', 'node2' => 'Tanah &amp; air · Device 2'] as $node => $sectionLabel)
                <section aria-label="{{ $sectionLabel }}">
                    <div class="section-heading"><h2>{!! $sectionLabel !!}</h2></div>
                    <div class="metrics-grid {{ $node === 'node2' ? 'metrics-grid--3' : '' }}">
                        @foreach($tiles[$node] as $tile)
                            <x-metric-tile
                                :label="$tile['label']"
                                :value="$tile['value']"
                                :unit="$tile['unit']"
                                :icon="$tile['icon']"
                                :tone="$tile['tone']"
                                :note="$tile['note']" />
                        @endforeach
                        @foreach($states[$node] as $state)
                            @php
                                $reading = $latest[$node][$state['metric']] ?? null;
                                $stateValue = $reading ? $formatState($reading) : null;
                                $stateNote = $reading ? 'Terakhir '.$reading->recorded_at->timezone($tz)->format('d/m H:i T') : 'Menunggu data sensor';
                            @endphp
                            <x-metric-tile
                                :label="$state['label']"
                                :value="$stateValue"
                                icon="power"
                                :tone="$stateValue === 'Menyala' ? 'green' : 'neutral'"
                                :state="true"
                                :note="$stateNote" />
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="overview-split">
                @if(count($series))
                    <x-trend-chart :series="$series" />
                @else
                    <x-empty-chart title="Tren suhu udara" subtitle="Belum ada cukup data suhu di 6 jam terakhir." node="Device 1" period="6 jam" :href="route('dashboard.mqtt')" />
                @endif

                <section class="panel status-panel" aria-label="Status data">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Status data</h2>
                            <p class="panel-subtitle">Ringkasan baris di tabel readings.</p>
                        </div>
                        @if($live)
                            <x-badge tone="green" :dot="true">Aktif</x-badge>
                        @elseif($lastRecorded)
                            <x-badge tone="amber">Historis</x-badge>
                        @else
                            <x-badge>Kosong</x-badge>
                        @endif
                    </div>
                    <div class="panel-body">
                        <dl class="definition-list">
                            <div>
                                <dt>Node 1 (iklim)</dt>
                                <dd><span class="status-num">{{ number_format($counts['node1']) }}</span> baris</dd>
                            </div>
                            <div>
                                <dt>Node 2 (tanah &amp; air)</dt>
                                <dd><span class="status-num">{{ number_format($counts['node2']) }}</span> baris</dd>
                            </div>
                            <div>
                                <dt>Total tersimpan</dt>
                                <dd><span class="status-num">{{ number_format($counts['node1'] + $counts['node2']) }}</span> baris</dd>
                            </div>
                            <div>
                                <dt>Pembacaan terakhir</dt>
                                <dd>{{ $lastRecorded ? \Illuminate\Support\Carbon::parse($lastRecorded)->timezone(config('app.timezone'))->format('d/m H:i:s T') : 'Belum ada' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="panel-footer">
                        <a class="text-link" href="{{ route('dashboard.riwayat') }}">Data mentah <x-icon name="arrow" /></a>
                        <a class="text-link" href="{{ route('dashboard.mqtt') }}">Status MQTT <x-icon name="arrow" /></a>
                    </div>
                </section>
            </div>
        </div>
    </turbo-frame>

    <section class="panel" aria-labelledby="device-control-title">
        <div class="panel-header">
            <div>
                <h2 id="device-control-title" class="panel-title">Kontrol perangkat</h2>
                <p class="panel-subtitle">Kirim perintah langsung ke topik MQTT kontrol perangkat.</p>
            </div>
            <x-badge :tone="($monitorSettings['pump_mode'] ?? 'auto') === 'manual' ? 'amber' : 'green'">Mode pompa: {{ ($monitorSettings['pump_mode'] ?? 'auto') === 'manual' ? 'Manual' : 'Otomatis' }}</x-badge>
        </div>
        <div class="panel-body">
            <div class="device-control-grid">
                <div class="device-control-item">
                    <div>
                        <strong class="device-control-name">Pompa air (Node 2)</strong>
                        <p class="device-control-meta">Ambang otomatis {{ $monitorSettings['soil_on'] ?? 30 }}% - {{ $monitorSettings['soil_off'] ?? 60 }}%, tandon min {{ $monitorSettings['tank_min'] ?? 20 }}%.</p>
                    </div>
                    <div class="device-control-actions">
                        <form method="POST" action="{{ route('dashboard.control') }}">
                            @csrf
                            <input type="hidden" name="node" value="node2">
                            <input type="hidden" name="actuator" value="pump">
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="state" value="1">
                            <button type="submit" class="button">Nyalakan</button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.control') }}">
                            @csrf
                            <input type="hidden" name="node" value="node2">
                            <input type="hidden" name="actuator" value="pump">
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="state" value="0">
                            <button type="submit" class="button button--secondary">Matikan</button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.control') }}">
                            @csrf
                            <input type="hidden" name="node" value="node2">
                            <input type="hidden" name="actuator" value="pump">
                            <input type="hidden" name="mode" value="auto">
                            <button type="submit" class="button button--secondary">Otomatis</button>
                        </form>
                    </div>
                </div>

                <div class="device-control-item">
                    <div>
                        <strong class="device-control-name">Kipas 1 (Node 1)</strong>
                        <p class="device-control-meta">Ambang suhu maksimal {{ $monitorSettings['th_suhu_max'] ?? 30 }} °C.</p>
                    </div>
                    <div class="device-control-actions">
                        <form method="POST" action="{{ route('dashboard.control') }}">
                            @csrf
                            <input type="hidden" name="node" value="node1">
                            <input type="hidden" name="actuator" value="fan1">
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="state" value="1">
                            <button type="submit" class="button">Nyalakan</button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.control') }}">
                            @csrf
                            <input type="hidden" name="node" value="node1">
                            <input type="hidden" name="actuator" value="fan1">
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="state" value="0">
                            <button type="submit" class="button button--secondary">Matikan</button>
                        </form>
                    </div>
                </div>

                <div class="device-control-item">
                    <div>
                        <strong class="device-control-name">Kipas 2 (Node 1)</strong>
                        <p class="device-control-meta">Pendingin tambahan Node 1 (override manual).</p>
                    </div>
                    <div class="device-control-actions">
                        <form method="POST" action="{{ route('dashboard.control') }}">
                            @csrf
                            <input type="hidden" name="node" value="node1">
                            <input type="hidden" name="actuator" value="fan2">
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="state" value="1">
                            <button type="submit" class="button">Nyalakan</button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.control') }}">
                            @csrf
                            <input type="hidden" name="node" value="node1">
                            <input type="hidden" name="actuator" value="fan2">
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="state" value="0">
                            <button type="submit" class="button button--secondary">Matikan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <span>Perintah manual juga tersedia lewat endpoint API mobile <code>POST /api/v1/devices/control</code>.</span>
            <a class="text-link" href="{{ route('settings.index') }}">Atur ambang <x-icon name="arrow" /></a>
        </div>
    </section>

    <section class="panel" aria-labelledby="activity-title">
        <div class="panel-header"><div><h2 id="activity-title" class="panel-title">Aktivitas terbaru</h2><p class="panel-subtitle">Catatan akun, sesi, dan pengaturan aplikasi.</p></div><a class="text-link" href="{{ route('activity.index') }}">Lihat semua <x-icon name="arrow" /></a></div>
        <x-activity-list :activities="$recentActivities" />
    </section>
</div>
@endsection
