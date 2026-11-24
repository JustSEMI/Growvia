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
            <div class="two-columns device-overview-grid">
                @foreach(['node1' => 'Iklim · Device 1', 'node2' => 'Tanah &amp; air · Device 2'] as $node => $sectionLabel)
                    <section class="device-group" aria-label="{{ strip_tags($sectionLabel) }}">
                        <div class="section-heading section-heading--ruled">
                            <h2>{!! $sectionLabel !!}</h2>
                            <div class="actuator-strip">
                                @foreach($states[$node] as $state)
                                    @php
                                        $reading = $latest[$node][$state['metric']] ?? null;
                                        $stateValue = $reading ? $formatState($reading) : null;
                                        $stateNote = $reading ? $reading->recorded_at->timezone($tz)->format('d/m H:i T') : 'Menunggu';
                                    @endphp
                                    <div class="actuator-chip {{ $stateValue === 'Menyala' ? 'is-on' : '' }}" title="{{ $state['label'] }} · {{ $stateNote }}">
                                        <span class="actuator-dot" aria-hidden="true"></span>
                                        <span class="actuator-chip-name">{{ $state['label'] }}</span>
                                        <span class="actuator-chip-value">{{ $stateValue ?? 'Belum ada' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="metrics-grid metrics-grid--2 metrics-grid--open">
                            @foreach($tiles[$node] as $tile)
                                <x-metric-tile
                                    :label="$tile['label']"
                                    :value="$tile['value']"
                                    :unit="$tile['unit']"
                                    :icon="$tile['icon']"
                                    :tone="$tile['tone']"
                                    :note="$tile['note']" />
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="overview-split">
                @if(count($series))
                    <x-trend-chart :series="$series" :threshold-max="$monitorSettings['th_suhu_max'] ?? null" />
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

            @php
                $pumpReading = $latest['node2']['pump'] ?? null;
                $pumpOn = $pumpReading !== null && (float) $pumpReading->value >= 0.5;
                $pumpAuto = ($monitorSettings['pump_mode'] ?? 'auto') !== 'manual';

                $fan1Reading = $latest['node1']['fan1'] ?? null;
                $fan1On = $fan1Reading !== null && (float) $fan1Reading->value >= 0.5;

                $fan2Reading = $latest['node1']['fan2'] ?? null;
                $fan2On = $fan2Reading !== null && (float) $fan2Reading->value >= 0.5;
            @endphp

            <section class="panel" aria-labelledby="device-control-title">
                <div class="panel-header">
                    <div>
                        <h2 id="device-control-title" class="panel-title">Kontrol perangkat</h2>
                        <p class="panel-subtitle">Sakelar langsung mengirim perintah ke broker MQTT.</p>
                    </div>
                    <x-badge :tone="$pumpAuto ? 'green' : 'amber'">Mode pompa: {{ $pumpAuto ? 'Otomatis' : 'Manual' }}</x-badge>
                </div>
                <div class="panel-body">
                    <div class="device-control-grid">
                        <div class="device-control-item">
                            <div>
                                <strong class="device-control-name">Pompa air (Node 2)</strong>
                                <p class="device-control-meta">Ambang {{ $monitorSettings['soil_on'] ?? 30 }}% - {{ $monitorSettings['soil_off'] ?? 60 }}%, tandon min {{ $monitorSettings['tank_min'] ?? 20 }}%.</p>
                            </div>
                            <div class="device-switch-list">
                                <x-device-switch
                                    label="Daya pompa"
                                    aria-label="Sakelar daya pompa air"
                                    :state-text="$pumpReading ? ($pumpOn ? 'Menyala' : 'Mati') : 'Belum ada'"
                                    :checked="$pumpOn"
                                    node="node2"
                                    actuator="pump"
                                    mode="manual"
                                    :next-state="$pumpOn ? 0 : 1" />
                                <x-device-switch
                                    label="Mode otomatis"
                                    aria-label="Sakelar mode otomatis pompa air"
                                    :state-text="$pumpAuto ? 'Aktif' : 'Manual'"
                                    :checked="$pumpAuto"
                                    node="node2"
                                    actuator="pump"
                                    :mode="$pumpAuto ? 'manual' : 'auto'"
                                    :next-state="$pumpAuto ? ($pumpOn ? 1 : 0) : null" />
                            </div>
                        </div>

                        <div class="device-control-item">
                            <div>
                                <strong class="device-control-name">Kipas 1 (Node 1)</strong>
                                <p class="device-control-meta">Ambang suhu maksimal {{ $monitorSettings['th_suhu_max'] ?? 30 }} °C.</p>
                            </div>
                            <div class="device-switch-list">
                                <x-device-switch
                                    label="Daya kipas"
                                    aria-label="Sakelar Kipas 1"
                                    :state-text="$fan1Reading ? ($fan1On ? 'Menyala' : 'Mati') : 'Belum ada'"
                                    :checked="$fan1On"
                                    node="node1"
                                    actuator="fan1"
                                    mode="manual"
                                    :next-state="$fan1On ? 0 : 1" />
                            </div>
                        </div>

                        <div class="device-control-item">
                            <div>
                                <strong class="device-control-name">Kipas 2 (Node 1)</strong>
                                <p class="device-control-meta">Pendingin tambahan Node 1 (override manual).</p>
                            </div>
                            <div class="device-switch-list">
                                <x-device-switch
                                    label="Daya kipas"
                                    aria-label="Sakelar Kipas 2"
                                    :state-text="$fan2Reading ? ($fan2On ? 'Menyala' : 'Mati') : 'Belum ada'"
                                    :checked="$fan2On"
                                    node="node1"
                                    actuator="fan2"
                                    mode="manual"
                                    :next-state="$fan2On ? 0 : 1" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <span>Perintah manual juga tersedia lewat API mobile <code>POST /api/v1/devices/control</code>.</span>
                    <a class="text-link" href="{{ route('settings.index') }}">Atur ambang <x-icon name="arrow" /></a>
                </div>
            </section>
        </div>
    </turbo-frame>

    <section class="panel" aria-labelledby="activity-title">
        <div class="panel-header"><div><h2 id="activity-title" class="panel-title">Aktivitas terbaru</h2><p class="panel-subtitle">Catatan akun, sesi, dan pengaturan aplikasi.</p></div><a class="text-link" href="{{ route('activity.index') }}">Lihat semua <x-icon name="arrow" /></a></div>
        <x-activity-list :activities="$recentActivities" />
    </section>
</div>
@endsection
