@props(['series', 'title' => 'Tren suhu udara', 'thresholdMax' => null])

@php
    $chartId = 'trend-chart-'.md5($title);
@endphp

<section {{ $attributes->class(['panel', 'trend-panel']) }} aria-label="{{ $title }}">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">{{ $title }}</h2>
            <p class="panel-subtitle">Rata-rata sensor Node 1 · {{ $series['count'] }} titik tercatat</p>
        </div>
        <span class="chart-period">{{ $series['period'] }}</span>
    </div>
    <div class="panel-body">
        <div class="chart-stats">
            <div class="chart-stat">
                <span>Terbaru</span>
                <strong>{{ number_format($series['latest'], 1) }} °C</strong>
            </div>
            <div class="chart-stat">
                <span>Minimum</span>
                <strong>{{ number_format($series['min'], 1) }} °C</strong>
            </div>
            <div class="chart-stat">
                <span>Rata-rata</span>
                <strong>{{ number_format($series['avg'], 1) }} °C</strong>
            </div>
            <div class="chart-stat">
                <span>Maksimum</span>
                <strong>{{ number_format($series['max'], 1) }} °C</strong>
            </div>
        </div>
        <div
            class="apex-chart-box"
            data-trend-chart
            data-points='@json($series['points'])'
            data-timezone="{{ config('app.timezone') }}"
            data-unit="°C"
            data-series-name="Suhu udara"
            @if($thresholdMax !== null && is_numeric($thresholdMax)) data-threshold-max="{{ $thresholdMax }}" @endif
            role="img"
            aria-label="Grafik suhu udara {{ $series['period'] }}">
            <div id="{{ $chartId }}" class="apex-chart-mount" data-trend-chart-mount data-turbo-permanent></div>
        </div>
    </div>
    <div class="panel-footer">
        <span>{{ $series['isRecent'] ? 'Disegarkan otomatis tiap 15 detik.' : 'Menampilkan jendela data terakhir yang tersimpan.' }}</span>
    </div>
</section>
