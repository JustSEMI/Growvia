@props(['series', 'title' => 'Tren suhu udara'])

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
        <div class="chart-stage">
            <div class="chart-y-axis" aria-hidden="true">
                <span>{{ number_format($series['max'], 1) }}°</span>
                <span>{{ number_format($series['mid'], 1) }}°</span>
                <span>{{ number_format($series['min'], 1) }}°</span>
            </div>
            <div class="chart-canvas">
                <svg class="trend-chart" viewBox="0 0 560 160" preserveAspectRatio="none" role="img" aria-label="Grafik suhu udara {{ $series['period'] }}">
                    <line x1="0" y1="14" x2="560" y2="14" class="trend-grid" />
                    <line x1="0" y1="80" x2="560" y2="80" class="trend-grid" />
                    <line x1="0" y1="146" x2="560" y2="146" class="trend-grid" />
                    <polygon class="trend-area" points="{{ $series['areaPoints'] }}" />
                    <polyline class="trend-line" points="{{ $series['points'] }}" />
                    <circle class="trend-dot" cx="{{ $series['lastX'] }}" cy="{{ $series['lastY'] }}" r="4" />
                </svg>
                <div class="chart-x-axis" aria-hidden="true">
                    <span>{{ $series['from'] }}</span>
                    <span>{{ $series['midTime'] }}</span>
                    <span>{{ $series['to'] }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <span>{{ $series['isRecent'] ? 'Disegarkan otomatis tiap 15 detik.' : 'Menampilkan jendela data terakhir yang tersimpan.' }}</span>
    </div>
</section>
