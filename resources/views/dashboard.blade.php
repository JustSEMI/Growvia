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
        <div class="header-button-group">
            <a class="button button--secondary button--sm" href="{{ route('dashboard.riwayat') }}"><x-icon name="clock" /><span>Riwayat</span></a>
            <a class="button button--sm" href="{{ route('settings.index') }}"><x-icon name="settings" /><span>Atur ambang</span></a>
        </div>
    </x-slot:actions>
</x-page-header>
<x-alerts />

<div class="stack">
    <turbo-frame id="dashboard-live" refresh="morph" data-turbo-refresh-interval="15000">
        <div class="stack">
            <div class="two-columns device-overview-grid">
                @foreach([
                    'node1' => ['title' => 'Iklim · Device 1', 'meta' => '2x SHT (udara) · 1 kipas'],
                    'node2' => ['title' => 'Tanah &amp; air · Device 2', 'meta' => '3x sensor tanah · tandon'],
                ] as $node => $nodeMeta)
                    <section class="panel device-panel" aria-label="{{ strip_tags($nodeMeta['title']) }}">
                        <div class="panel-header device-panel-head">
                            <div>
                                <h2 class="panel-title">{!! $nodeMeta['title'] !!}</h2>
                                <p class="panel-subtitle">{{ $nodeMeta['meta'] }}</p>
                            </div>
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
                        <div class="panel-body device-panel-body">
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
                        </div>
                    </section>
                @endforeach
            </div>

            @php
                $node1Tabs = [
                    [
                        'key' => 'temperature',
                        'label' => 'Suhu udara',
                        'subtitle' => 'Rata-rata sensor Node 1',
                        'unit' => '°C',
                        'tone' => 'amber',
                        'series' => $series,
                        'thresholdMax' => $monitorSettings['th_suhu_max'] ?? null,
                        'thresholdMaxLabel' => isset($monitorSettings['th_suhu_max']) ? 'Batas '.$monitorSettings['th_suhu_max'].' °C' : null,
                        'emptyTitle' => 'Menunggu minimal 2 titik suhu (Device 1)',
                        'emptySubtitle' => 'Grafik ApexCharts otomatis aktif begitu sensor mengirim minimal dua pembacaan suhu.',
                    ],
                    [
                        'key' => 'humidity',
                        'label' => 'Kelembapan udara',
                        'subtitle' => 'Rata-rata sensor Node 1',
                        'unit' => '%',
                        'tone' => 'blue',
                        'series' => $humiditySeries,
                        'thresholdMin' => $monitorSettings['th_hum_min'] ?? null,
                        'thresholdMinLabel' => isset($monitorSettings['th_hum_min']) ? 'Min '.$monitorSettings['th_hum_min'].'%' : null,
                        'thresholdMax' => $monitorSettings['th_hum_max'] ?? null,
                        'thresholdMaxLabel' => isset($monitorSettings['th_hum_max']) ? 'Maks '.$monitorSettings['th_hum_max'].'%' : null,
                        'emptyTitle' => 'Menunggu minimal 2 titik kelembapan udara (Device 1)',
                        'emptySubtitle' => 'Grafik ApexCharts otomatis aktif begitu sensor SHT mengirim minimal dua pembacaan.',
                    ],
                ];

                $node2Tabs = [
                    [
                        'key' => 'soil_moisture',
                        'label' => 'Kelembapan tanah',
                        'subtitle' => 'Rata-rata tanah Node 2',
                        'unit' => '%',
                        'tone' => 'green',
                        'series' => $soilSeries,
                        'thresholdMin' => $monitorSettings['soil_on'] ?? null,
                        'thresholdMinLabel' => isset($monitorSettings['soil_on']) ? 'Siram < '.$monitorSettings['soil_on'].'%' : null,
                        'thresholdMax' => $monitorSettings['soil_off'] ?? null,
                        'thresholdMaxLabel' => isset($monitorSettings['soil_off']) ? 'Stop > '.$monitorSettings['soil_off'].'%' : null,
                        'emptyTitle' => 'Menunggu minimal 2 titik kelembapan tanah (Device 2)',
                        'emptySubtitle' => 'Grafik ApexCharts otomatis aktif begitu sensor soil Node 2 mengirim minimal dua pembacaan.',
                    ],
                    [
                        'key' => 'water_level',
                        'label' => 'Tandon air',
                        'subtitle' => 'Sensor ultrasonik Node 2',
                        'unit' => '%',
                        'tone' => 'blue',
                        'series' => $waterSeries,
                        'thresholdMin' => $monitorSettings['tank_min'] ?? null,
                        'thresholdMinLabel' => isset($monitorSettings['tank_min']) ? 'Min tandon '.$monitorSettings['tank_min'].'%' : null,
                        'emptyTitle' => 'Menunggu minimal 2 titik level tandon (Device 2)',
                        'emptySubtitle' => 'Grafik ApexCharts otomatis aktif begitu sensor tandon Node 2 mengirim minimal dua pembacaan.',
                    ],
                ];

                $hasNode1Chart = count($series) > 0 || count($humiditySeries) > 0;
                $hasNode2Chart = count($soilSeries) > 0 || count($waterSeries) > 0;
            @endphp

            <div class="charts-split">
                @if($hasNode1Chart)
                    <x-trend-chart title="Tren iklim · Device 1" node="Device 1" :tabs="$node1Tabs" :href="route('dashboard.riwayat', ['node' => 'node1'])" link-label="Tabel Device 1" />
                @else
                    <x-empty-chart title="Tren iklim · Device 1" subtitle="Belum ada cukup data suhu dan kelembapan udara di 6 jam terakhir." metric-label="suhu" node="Device 1" period="6 jam" :href="route('dashboard.mqtt')" />
                @endif

                @if($hasNode2Chart)
                    <x-trend-chart title="Tren tanah & air · Device 2" node="Device 2" :tabs="$node2Tabs" :href="route('dashboard.riwayat', ['node' => 'node2'])" link-label="Tabel Device 2" />
                @else
                    <x-empty-chart title="Tren tanah & air · Device 2" subtitle="Belum ada cukup data kelembapan tanah dan tandon di 6 jam terakhir." metric-label="kelembapan tanah" node="Device 2" period="6 jam" :href="route('dashboard.mqtt')" />
                @endif
            </div>

            @php
                $pumpReading = $latest['node2']['pump'] ?? null;
                $fan1Reading = $latest['node1']['fan1'] ?? null;
                $pumpAuto = ($monitorSettings['pump_mode'] ?? 'auto') !== 'manual';
            @endphp

            <section class="panel" aria-labelledby="device-control-title">
                <div class="panel-header">
                    <div>
                        <h2 id="device-control-title" class="panel-title">Kontrol perangkat</h2>
                        <p class="panel-subtitle">Tombol langsung mengirim perintah ke broker MQTT.</p>
                    </div>
                    <x-badge :tone="$pumpAuto ? 'green' : 'amber'">Mode pompa: {{ $pumpAuto ? 'Otomatis' : 'Manual' }}</x-badge>
                </div>
                <div class="panel-body">
                    <div class="device-control-grid">
                        <article class="device-control-item">
                            <div class="device-control-head">
                                <span class="icon-tile icon-tile--blue"><x-icon name="drop" /></span>
                                <div>
                                    <h3 class="device-control-name">Pompa air (Node 2)</h3>
                                    <p class="device-control-meta">Ambang {{ $monitorSettings['soil_on'] ?? 30 }}% - {{ $monitorSettings['soil_off'] ?? 60 }}%, tandon min {{ $monitorSettings['tank_min'] ?? 20 }}%.</p>
                                </div>
                            </div>
                            <x-pump-control
                                :reading="$pumpReading"
                                :settings="$monitorSettings"
                                :action="route('dashboard.control')"
                                hint-id="dashboard-pump-hint" />
                        </article>

                        <article class="device-control-item">
                            <div class="device-control-head">
                                <span class="icon-tile icon-tile--amber"><x-icon name="thermometer" /></span>
                                <div>
                                    <h3 class="device-control-name">Kipas (Node 1)</h3>
                                    <p class="device-control-meta">Ambang suhu maksimal {{ $monitorSettings['th_suhu_max'] ?? 30 }} °C.</p>
                                </div>
                            </div>
                            <x-actuator-control
                                node="node1"
                                actuator="fan1"
                                actuator-label="Kipas"
                                device-label="Device 1"
                                :reading="$fan1Reading"
                                :settings="$monitorSettings"
                                :action="route('dashboard.control')"
                                hint-id="dashboard-fan1-hint"
                                manual-note="Kipas Node 1 · pin GPIO 5 &amp; GPIO 17." />
                        </article>
                    </div>
                </div>
                <div class="panel-footer">
                    <span>Perintah manual juga tersedia lewat API mobile <code>POST /api/v1/devices/control</code>.</span>
                    <a class="text-link" href="{{ route('settings.index') }}">Atur ambang <x-icon name="arrow" /></a>
                </div>
            </section>
        </div>
    </turbo-frame>

    <div class="ops-split">
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
                <div class="status-kpis">
                    <div class="status-kpi">
                        <span>Node 1 (iklim)</span>
                        <strong><span class="status-num">{{ number_format($counts['node1']) }}</span> <small>baris</small></strong>
                    </div>
                    <div class="status-kpi">
                        <span>Node 2 (tanah &amp; air)</span>
                        <strong><span class="status-num">{{ number_format($counts['node2']) }}</span> <small>baris</small></strong>
                    </div>
                </div>
                <dl class="definition-list mt-6">
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

        <section class="panel activity-panel" aria-labelledby="activity-title">
            <div class="panel-header">
                <div>
                    <h2 id="activity-title" class="panel-title">Aktivitas terbaru</h2>
                    <p class="panel-subtitle">Catatan akun, sesi, dan pengaturan aplikasi.</p>
                </div>
                <a class="text-link" href="{{ route('activity.index') }}">Lihat semua <x-icon name="arrow" /></a>
            </div>
            <x-activity-list :activities="$recentActivities" />
        </section>
    </div>
</div>
@endsection
