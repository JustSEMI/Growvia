@props([
    'series' => [],
    'tabs' => null,
    'title' => 'Tren suhu udara',
    'subtitle' => 'Rata-rata sensor Node 1',
    'node' => 'Device 1',
    'thresholdMax' => null,
    'href' => null,
    'linkLabel' => 'Data mentah',
])

@php
    $normalizedTabs = collect($tabs ?? [[
        'key' => 'temperature',
        'label' => 'Suhu udara',
        'subtitle' => $subtitle,
        'unit' => '°C',
        'tone' => 'amber',
        'series' => $series,
        'thresholdMax' => $thresholdMax,
        'thresholdMaxLabel' => $thresholdMax !== null ? 'Batas '.$thresholdMax.' °C' : null,
        'emptyTitle' => 'Menunggu minimal 2 titik suhu ('.$node.')',
        'emptySubtitle' => 'Grafik ApexCharts otomatis aktif begitu sensor mengirim minimal dua pembacaan berurutan.',
    ]])->values();

    $activeIndex = $normalizedTabs->search(fn (array $tab): bool => count($tab['series'] ?? []) > 0);
    if ($activeIndex === false) {
        $activeIndex = 0;
    }

    $activeTab = $normalizedTabs->get($activeIndex);
    $activeSeries = $activeTab['series'] ?? [];
    $chartId = 'trend-chart-'.md5($title);
    $activeUnit = $activeTab['unit'] ?? '°C';
    $hasData = count($activeSeries) > 0;
    $hasMultipleTabs = $normalizedTabs->count() > 1;

    $clientTabs = $normalizedTabs->map(function (array $tab) use ($node): array {
        $tabSeries = $tab['series'] ?? [];

        return [
            'key' => $tab['key'],
            'label' => $tab['label'],
            'subtitle' => $tab['subtitle'] ?? 'Rata-rata sensor',
            'unit' => $tab['unit'] ?? '°C',
            'tone' => $tab['tone'] ?? 'green',
            'isPercent' => ($tab['unit'] ?? '') === '%',
            'thresholdMin' => isset($tab['thresholdMin']) && is_numeric($tab['thresholdMin']) ? (float) $tab['thresholdMin'] : null,
            'thresholdMinLabel' => $tab['thresholdMinLabel'] ?? null,
            'thresholdMax' => isset($tab['thresholdMax']) && is_numeric($tab['thresholdMax']) ? (float) $tab['thresholdMax'] : null,
            'thresholdMaxLabel' => $tab['thresholdMaxLabel'] ?? null,
            'emptyTitle' => $tab['emptyTitle'] ?? ('Menunggu minimal 2 titik data ('.$node.')'),
            'emptySubtitle' => $tab['emptySubtitle'] ?? 'Grafik ApexCharts otomatis aktif begitu sensor mengirim minimal dua pembacaan berurutan.',
            'points' => $tabSeries['points'] ?? [],
            'latest' => $tabSeries['latest'] ?? null,
            'min' => $tabSeries['min'] ?? null,
            'avg' => $tabSeries['avg'] ?? null,
            'max' => $tabSeries['max'] ?? null,
            'count' => $tabSeries['count'] ?? 0,
            'period' => $tabSeries['period'] ?? '6 jam',
            'isRecent' => (bool) ($tabSeries['isRecent'] ?? false),
        ];
    })->all();

    $fmtStat = fn (?float $val): string => $val === null ? '--' : number_format($val, 1).' '.$activeUnit;
@endphp

<section
    {{ $attributes->class(['panel', 'trend-panel']) }}
    aria-label="{{ $title }}"
    data-trend-chart
    data-chart-tabs="{{ json_encode($clientTabs) }}"
    data-active-tab="{{ $activeTab['key'] }}"
    data-points='@json($activeSeries['points'] ?? [])'
    data-timezone="{{ config('app.timezone') }}"
    data-unit="{{ $activeUnit }}"
    data-series-name="{{ $activeTab['label'] }}"
    data-tone="{{ $activeTab['tone'] ?? 'green' }}"
    @if(($activeTab['thresholdMin'] ?? null) !== null && is_numeric($activeTab['thresholdMin'])) data-threshold-min="{{ $activeTab['thresholdMin'] }}" @endif
    @if(($activeTab['thresholdMax'] ?? null) !== null && is_numeric($activeTab['thresholdMax'])) data-threshold-max="{{ $activeTab['thresholdMax'] }}" @endif>
    <div class="panel-header">
        <div>
            <h2 class="panel-title">{{ $title }}</h2>
            <p class="panel-subtitle" data-trend-subtitle>{{ $activeTab['subtitle'] ?? $subtitle }} · {{ $activeSeries['count'] ?? 0 }} titik tercatat</p>
        </div>
        <span class="chart-period" data-trend-period>{{ $activeSeries['period'] ?? '6 jam' }}</span>
    </div>
    <div class="panel-body">
        @if($hasMultipleTabs)
            <div class="chart-tabs" role="tablist" aria-label="Pilih metrik {{ $title }}">
                @foreach($normalizedTabs as $tab)
                    @php
                        $isActive = $tab['key'] === $activeTab['key'];
                        $tabHasData = count($tab['series'] ?? []) > 0;
                    @endphp
                    <button
                        type="button"
                        role="tab"
                        id="{{ $chartId }}-tab-{{ $tab['key'] }}"
                        class="chart-tab {{ $isActive ? 'is-active' : '' }}"
                        aria-selected="{{ $isActive ? 'true' : 'false' }}"
                        tabindex="{{ $isActive ? '0' : '-1' }}"
                        data-chart-tab="{{ $tab['key'] }}">
                        <span>{{ $tab['label'] }}</span>
                        <span class="chart-tab-unit">{{ $tab['unit'] }}{{ $tabHasData ? '' : ' · kosong' }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div
            data-trend-panel
            @if($hasMultipleTabs)
                role="tabpanel"
                aria-labelledby="{{ $chartId }}-tab-{{ $activeTab['key'] }}"
            @endif>
            <div class="chart-stats">
                <div class="chart-stat">
                    <span>Terbaru</span>
                    <strong data-trend-stat="latest">{{ $fmtStat($activeSeries['latest'] ?? null) }}</strong>
                </div>
                <div class="chart-stat">
                    <span>Minimum</span>
                    <strong data-trend-stat="min">{{ $fmtStat($activeSeries['min'] ?? null) }}</strong>
                </div>
                <div class="chart-stat">
                    <span>Rata-rata</span>
                    <strong data-trend-stat="avg">{{ $fmtStat($activeSeries['avg'] ?? null) }}</strong>
                </div>
                <div class="chart-stat">
                    <span>Maksimum</span>
                    <strong data-trend-stat="max">{{ $fmtStat($activeSeries['max'] ?? null) }}</strong>
                </div>
            </div>

            <div class="apex-chart-box" @unless($hasMultipleTabs) role="img" aria-label="Grafik {{ $activeTab['label'] }} {{ $activeSeries['period'] ?? '6 jam' }}" @endunless>
                <div id="{{ $chartId }}" class="apex-chart-mount" data-trend-chart-mount data-turbo-permanent @unless($hasData) hidden @endunless></div>
                <div class="empty-chart-box empty-chart-box--inline" data-trend-empty @if($hasData) hidden @endif>
                    <span class="icon-tile"><x-icon name="chart" /></span>
                    <div class="empty-chart-copy">
                        <strong data-trend-empty-title>{{ $activeTab['emptyTitle'] }}</strong>
                        <p data-trend-empty-subtitle>{{ $activeTab['emptySubtitle'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <span data-trend-footer-note>{{ ($activeSeries['isRecent'] ?? false) ? 'Disegarkan otomatis tiap 15 detik.' : 'Menampilkan jendela data terakhir yang tersimpan.' }}</span>
        @if($href)
            <a class="text-link" href="{{ $href }}">{{ $linkLabel }} <x-icon name="arrow" /></a>
        @endif
    </div>
</section>
