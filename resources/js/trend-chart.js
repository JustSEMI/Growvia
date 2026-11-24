const chartState = new WeakMap();
const activeHosts = new Set();
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

let apexPromise = null;
let observersBound = false;

const loadApexCharts = async () => {
    if (!apexPromise) {
        apexPromise = import('apexcharts').then((mod) => mod.default || mod);
    }
    return apexPromise;
};

const isDarkTheme = () => {
    const choice = document.documentElement.getAttribute('data-theme');
    if (choice === 'dark') return true;
    if (choice === 'light') return false;
    return systemDark.matches;
};

// Selaras dengan token warna di :root (resources/css/app.css).
const readPalette = () => {
    const dark = isDarkTheme();

    return {
        mode: dark ? 'dark' : 'light',
        accent: dark ? '#9ecf94' : '#1e4d2f',
        accentEnd: dark ? '#7ab96f' : '#2f7044',
        blue: dark ? '#a7cde5' : '#1f6c9f',
        blueEnd: dark ? '#7db3d6' : '#328bc4',
        neutral: dark ? '#b5beb4' : '#626a64',
        neutralEnd: dark ? '#98a397' : '#7b847d',
        ink: dark ? '#e8ece4' : '#202723',
        muted: dark ? '#a3aea1' : '#737773',
        grid: dark ? '#364139' : '#eceee8',
        track: dark ? '#29322b' : '#e4e7df',
        amber: dark ? '#e2c78f' : '#785411',
        amberEnd: dark ? '#cfa85c' : '#a8771b',
        amberBg: dark ? '#3d3524' : '#fbf3db',
    };
};

const formatDateTime = (timestamp, timeZone, withDate = false) => {
    const options = withDate
        ? { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }
        : { hour: '2-digit', minute: '2-digit', hour12: false };

    if (timeZone) {
        options.timeZone = timeZone;
    }

    return new Intl.DateTimeFormat('id-ID', options).format(new Date(timestamp));
};

const parsePoints = (raw) => {
    if (!raw) return [];
    try {
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        return parsed
            .filter((item) => item && typeof item.x === 'number' && typeof item.y === 'number')
            .map((item) => ({ x: item.x, y: item.y }));
    } catch {
        return [];
    }
};

const resolveToneColors = (tone, palette) => {
    if (tone === 'amber') return [palette.amber, palette.amberEnd];
    if (tone === 'blue') return [palette.blue, palette.blueEnd];
    if (tone === 'neutral') return [palette.neutral, palette.neutralEnd];
    return [palette.accent, palette.accentEnd];
};

const buildTrendOptions = (points, meta, palette) => {
    const ys = points.map((point) => point.y);
    const dataMin = Math.min(...ys);
    const dataMax = Math.max(...ys);
    const hasThreshold = Number.isFinite(meta.thresholdMax);
    const upperBound = hasThreshold ? Math.max(dataMax, meta.thresholdMax) : dataMax;
    const min = Math.floor((dataMin - 0.5) * 2) / 2;
    const max = Math.ceil((upperBound + 0.5) * 2) / 2;

    return {
        chart: {
            type: 'area',
            height: 240,
            background: 'transparent',
            fontFamily: "'Montserrat', 'Helvetica Neue', Arial, sans-serif",
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: !reduceMotion.matches, speed: 350 },
        },
        series: [{
            name: meta.seriesName,
            data: points,
        }],
        colors: [palette.accent],
        dataLabels: { enabled: false },
        stroke: {
            curve: 'smooth',
            width: 2.5,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 0.15,
                opacityFrom: palette.mode === 'dark' ? 0.32 : 0.24,
                opacityTo: 0.02,
                stops: [0, 95, 100],
            },
        },
        markers: {
            size: 0,
            hover: { size: 5, sizeOffset: 2 },
        },
        grid: {
            borderColor: palette.grid,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } },
            padding: { top: 8, right: 12, bottom: 0, left: 6 },
        },
        xaxis: {
            type: 'datetime',
            tooltip: { enabled: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: { colors: palette.muted, fontSize: '12px' },
                formatter: (_value, timestamp) => (
                    Number.isFinite(timestamp) ? formatDateTime(timestamp, meta.timezone, false) : ''
                ),
            },
        },
        yaxis: {
            min,
            max,
            tickAmount: 4,
            labels: {
                style: { colors: palette.muted, fontSize: '12px' },
                formatter: (value) => `${Number(value).toFixed(1)} ${meta.unit}`,
            },
        },
        annotations: hasThreshold ? {
            yaxis: [{
                y: meta.thresholdMax,
                borderColor: palette.amber,
                strokeDashArray: 5,
                label: {
                    borderColor: palette.amber,
                    style: {
                        color: palette.amber,
                        background: palette.amberBg,
                        fontSize: '11px',
                        fontWeight: 600,
                        padding: { left: 6, right: 6, top: 2, bottom: 2 },
                    },
                    text: `Batas ${meta.thresholdMax} ${meta.unit}`,
                },
            }],
        } : undefined,
        tooltip: {
            theme: palette.mode,
            x: {
                formatter: (timestamp) => (
                    Number.isFinite(timestamp) ? formatDateTime(timestamp, meta.timezone, true) : ''
                ),
            },
            y: {
                formatter: (value) => `${Number(value).toFixed(1)} ${meta.unit}`,
            },
        },
        theme: { mode: palette.mode },
    };
};

const buildGaugeOptions = (meta, palette) => {
    const [toneStart, toneEnd] = resolveToneColors(meta.tone, palette);

    return {
        chart: {
            type: 'radialBar',
            height: 235,
            offsetY: -12,
            sparkline: { enabled: true },
            background: 'transparent',
            fontFamily: "'Montserrat', 'Helvetica Neue', Arial, sans-serif",
            animations: { enabled: !reduceMotion.matches, speed: 320 },
        },
        series: [meta.hasValue ? meta.percent : 0],
        colors: [toneStart],
        plotOptions: {
            radialBar: {
                startAngle: -90,
                endAngle: 90,
                hollow: { size: '65%' },
                track: {
                    background: palette.track,
                    strokeWidth: '100%',
                    margin: 0,
                },
                dataLabels: {
                    name: { show: false },
                    value: {
                        show: true,
                        offsetY: -8,
                        fontSize: meta.hasValue ? '26px' : '14px',
                        fontWeight: meta.hasValue ? 600 : 500,
                        color: meta.hasValue ? palette.ink : palette.muted,
                        formatter: () => meta.display,
                    },
                },
            },
        },
        fill: meta.hasValue ? {
            type: 'gradient',
            gradient: {
                shade: palette.mode,
                type: 'horizontal',
                shadeIntensity: 0.2,
                gradientToColors: [toneEnd],
                stops: [0, 100],
            },
        } : {
            colors: [palette.track],
        },
        stroke: { lineCap: 'round' },
        theme: { mode: palette.mode },
    };
};

const cleanupDetached = () => {
    for (const host of activeHosts) {
        if (host.isConnected) continue;
        const state = chartState.get(host);
        if (state) {
            state.chart.destroy();
            chartState.delete(host);
        }
        activeHosts.delete(host);
    }
};

const refreshTheme = () => {
    cleanupDetached();
    const palette = readPalette();

    for (const host of activeHosts) {
        const state = chartState.get(host);
        if (!state) continue;
        const options = state.kind === 'gauge'
            ? buildGaugeOptions(state.meta, palette)
            : buildTrendOptions(state.points, state.meta, palette);
        state.chart.updateOptions(options, false, false);
    }
};

const bindGlobalObservers = () => {
    if (observersBound) return;
    observersBound = true;

    const themeObserver = new MutationObserver(() => refreshTheme());
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    systemDark.addEventListener('change', refreshTheme);

    document.addEventListener('turbo:frame-render', initTrendCharts);
    document.addEventListener('turbo:morph', initTrendCharts);
};

const syncTrendHost = (ApexCharts, host, palette) => {
    if (!host.isConnected) return;
    const mount = host.querySelector('[data-trend-chart-mount]');
    if (!(mount instanceof HTMLElement)) return;

    const rawPoints = host.dataset.points || '[]';
    const rawThreshold = host.dataset.thresholdMax || '';
    const signature = `${rawPoints}|${rawThreshold}`;
    const points = parsePoints(rawPoints);
    if (!points.length) return;

    const parsedThreshold = rawThreshold !== '' ? Number(rawThreshold) : Number.NaN;
    const meta = {
        timezone: host.dataset.timezone || '',
        unit: host.dataset.unit || '°C',
        seriesName: host.dataset.seriesName || 'Suhu udara',
        thresholdMax: Number.isFinite(parsedThreshold) ? parsedThreshold : Number.NaN,
    };

    const existing = chartState.get(host);
    const canvasAlive = mount.querySelector('.apexcharts-canvas') !== null;

    if (existing && existing.mount === mount && (canvasAlive || existing.rendering)) {
        if (existing.signature !== signature) {
            existing.points = points;
            existing.meta = meta;
            existing.signature = signature;
            existing.chart.updateOptions(buildTrendOptions(points, meta, palette), true, false);
        }
        return;
    }

    if (existing) existing.chart.destroy();

    mount.innerHTML = '';
    const chart = new ApexCharts(mount, buildTrendOptions(points, meta, palette));
    const state = { kind: 'trend', chart, mount, points, meta, signature, rendering: true };
    chartState.set(host, state);
    activeHosts.add(host);
    chart.render().finally(() => { state.rendering = false; });
};

const syncGaugeHost = (ApexCharts, host, palette) => {
    if (!host.isConnected) return;
    const mount = host.querySelector('[data-gauge-chart-mount]');
    if (!(mount instanceof HTMLElement)) return;

    const percent = Number(host.dataset.percent || '0');
    const display = host.dataset.display || 'Belum ada';
    const hasValue = host.dataset.hasValue === '1';
    const tone = host.dataset.tone || 'green';
    const signature = `${percent}|${display}|${hasValue ? '1' : '0'}|${tone}`;
    const meta = {
        percent: Number.isFinite(percent) ? percent : 0,
        display,
        hasValue,
        tone,
    };

    const existing = chartState.get(host);
    const canvasAlive = mount.querySelector('.apexcharts-canvas') !== null;

    if (existing && existing.mount === mount && (canvasAlive || existing.rendering)) {
        if (existing.signature !== signature) {
            existing.meta = meta;
            existing.signature = signature;
            existing.chart.updateOptions(buildGaugeOptions(meta, palette), true, false);
        }
        return;
    }

    if (existing) existing.chart.destroy();

    mount.innerHTML = '';
    const chart = new ApexCharts(mount, buildGaugeOptions(meta, palette));
    const state = { kind: 'gauge', chart, mount, meta, signature, rendering: true };
    chartState.set(host, state);
    activeHosts.add(host);
    chart.render().finally(() => { state.rendering = false; });
};

export const initTrendCharts = async () => {
    bindGlobalObservers();
    cleanupDetached();

    const trendHosts = [...document.querySelectorAll('[data-trend-chart]')];
    const gaugeHosts = [...document.querySelectorAll('[data-gauge-chart]')];
    if (!trendHosts.length && !gaugeHosts.length) return;

    const ApexCharts = await loadApexCharts();
    const palette = readPalette();

    for (const host of trendHosts) syncTrendHost(ApexCharts, host, palette);
    for (const host of gaugeHosts) syncGaugeHost(ApexCharts, host, palette);
};
