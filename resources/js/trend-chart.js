const chartState = new WeakMap();
const activeTabByMount = new Map();
const boundTabHosts = new WeakSet();
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
        accent: dark ? '#96d99a' : '#1a5634',
        accentEnd: dark ? '#75b97b' : '#2c774c',
        accentBg: dark ? '#1c3123' : '#e4efe7',
        blue: dark ? '#84c5ec' : '#196394',
        blueEnd: dark ? '#5eaad9' : '#2a80b8',
        blueBg: dark ? '#182c3a' : '#e3f2fc',
        neutral: dark ? '#aebaa9' : '#4e5c53',
        neutralEnd: dark ? '#8d9b8f' : '#6c7a71',
        ink: dark ? '#ebf0ea' : '#17211b',
        muted: dark ? '#92a194' : '#5c6960',
        grid: dark ? '#2b382e' : '#e7ebe2',
        track: dark ? '#243026' : '#e4e7df',
        amber: dark ? '#ebca76' : '#8a5a08',
        amberEnd: dark ? '#cfa64e' : '#b27714',
        amberBg: dark ? '#312714' : '#fcf2d6',
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

const parseTabs = (host) => {
    if (host.dataset.chartTabs) {
        try {
            const parsed = JSON.parse(host.dataset.chartTabs);
            if (Array.isArray(parsed) && parsed.length) {
                return parsed;
            }
        } catch {
            // Fall through ke mode seri tunggal bila atribut tidak valid.
        }
    }

    const parsedMax = host.dataset.thresholdMax ? Number(host.dataset.thresholdMax) : Number.NaN;
    const parsedMin = host.dataset.thresholdMin ? Number(host.dataset.thresholdMin) : Number.NaN;

    return [{
        key: host.dataset.activeTab || 'primary',
        label: host.dataset.seriesName || 'Suhu udara',
        subtitle: '',
        unit: host.dataset.unit || '°C',
        tone: host.dataset.tone || 'green',
        isPercent: (host.dataset.unit || '') === '%',
        thresholdMin: Number.isFinite(parsedMin) ? parsedMin : null,
        thresholdMinLabel: null,
        thresholdMax: Number.isFinite(parsedMax) ? parsedMax : null,
        thresholdMaxLabel: null,
        emptyTitle: 'Menunggu data sensor',
        emptySubtitle: 'Grafik otomatis aktif begitu minimal dua titik tersedia.',
        points: parsePoints(host.dataset.points || '[]'),
        latest: null,
        min: null,
        avg: null,
        max: null,
        count: 0,
        period: '',
        isRecent: false,
    }];
};

const resolveToneColors = (tone, palette) => {
    if (tone === 'amber') return [palette.amber, palette.amberEnd, palette.amberBg];
    if (tone === 'blue') return [palette.blue, palette.blueEnd, palette.blueBg];
    if (tone === 'neutral') return [palette.neutral, palette.neutralEnd, palette.track];
    return [palette.accent, palette.accentEnd, palette.accentBg];
};

const buildThresholdLines = (meta, palette, toneColor, toneBg) => {
    const lines = [];
    if (Number.isFinite(meta.thresholdMax)) {
        lines.push({
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
                text: meta.thresholdMaxLabel || `Batas ${meta.thresholdMax} ${meta.unit}`,
            },
        });
    }
    if (Number.isFinite(meta.thresholdMin)) {
        lines.push({
            y: meta.thresholdMin,
            borderColor: toneColor,
            strokeDashArray: 4,
            label: {
                position: 'left',
                textAnchor: 'start',
                offsetX: 8,
                borderColor: toneColor,
                style: {
                    color: toneColor,
                    background: toneBg,
                    fontSize: '11px',
                    fontWeight: 600,
                    padding: { left: 6, right: 6, top: 2, bottom: 2 },
                },
                text: meta.thresholdMinLabel || `Min ${meta.thresholdMin} ${meta.unit}`,
            },
        });
    }
    return lines;
};

const buildTrendOptions = (points, meta, palette) => {
    const ys = points.map((point) => point.y);
    const dataMin = Math.min(...ys);
    const dataMax = Math.max(...ys);
    const bounds = [dataMin, dataMax];
    if (Number.isFinite(meta.thresholdMin)) bounds.push(meta.thresholdMin);
    if (Number.isFinite(meta.thresholdMax)) bounds.push(meta.thresholdMax);

    const low = Math.min(...bounds);
    const high = Math.max(...bounds);
    let min = Math.floor((low - (meta.isPercent ? 4 : 0.8)) * 2) / 2;
    let max = Math.ceil((high + (meta.isPercent ? 4 : 0.8)) * 2) / 2;
    if (meta.isPercent) {
        min = Math.max(0, min);
        max = Math.min(100, Math.max(min + 10, max));
    }

    const [toneStart, , toneBg] = resolveToneColors(meta.tone, palette);
    const thresholdLines = buildThresholdLines(meta, palette, toneStart, toneBg);

    return {
        chart: {
            type: 'area',
            height: 240,
            background: 'transparent',
            fontFamily: "'Montserrat', 'Helvetica Neue', Arial, sans-serif",
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: !reduceMotion.matches, speed: 320 },
        },
        series: [{
            name: meta.seriesName,
            data: points,
        }],
        colors: [toneStart],
        dataLabels: { enabled: false },
        stroke: {
            curve: 'smooth',
            width: 2.5,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 0.15,
                opacityFrom: palette.mode === 'dark' ? 0.30 : 0.22,
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
            padding: { top: 10, right: 12, bottom: 0, left: 6 },
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
                formatter: (value) => `${Number(value).toFixed(meta.isPercent ? 0 : 1)} ${meta.unit}`,
            },
        },
        annotations: thresholdLines.length ? { yaxis: thresholdLines } : undefined,
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
            height: 212,
            offsetY: -10,
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
                        offsetY: -6,
                        fontSize: meta.hasValue ? '25px' : '14px',
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

const updateTrendCardChrome = (host, tab) => {
    const hasCustomTabs = Boolean(host.dataset.chartTabs);
    if (!hasCustomTabs) return;

    const subtitle = host.querySelector('[data-trend-subtitle]');
    if (subtitle) {
        subtitle.textContent = `${tab.subtitle} · ${tab.count ?? 0} titik tercatat`;
    }

    const period = host.querySelector('[data-trend-period]');
    if (period) {
        period.textContent = tab.period || '6 jam';
    }

    const footerNote = host.querySelector('[data-trend-footer-note]');
    if (footerNote) {
        footerNote.textContent = tab.isRecent
            ? 'Disegarkan otomatis tiap 15 detik.'
            : 'Menampilkan jendela data terakhir yang tersimpan.';
    }

    const fmt = (val) => (typeof val === 'number' && Number.isFinite(val) ? `${val.toFixed(1)} ${tab.unit}` : '--');
    for (const key of ['latest', 'min', 'avg', 'max']) {
        const el = host.querySelector(`[data-trend-stat="${key}"]`);
        if (el) el.textContent = fmt(tab[key]);
    }

    const box = host.querySelector('[data-trend-panel]');
    if (box) {
        box.setAttribute('aria-label', `Grafik ${tab.label} ${tab.period || '6 jam'}`);
        const tabEl = host.querySelector(`[data-chart-tab="${CSS.escape(tab.key)}"]`);
        if (tabEl?.id) box.setAttribute('aria-labelledby', tabEl.id);
    }

    for (const button of host.querySelectorAll('[data-chart-tab]')) {
        const active = button.dataset.chartTab === tab.key;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
        button.tabIndex = active ? 0 : -1;
    }

    const emptyTitle = host.querySelector('[data-trend-empty-title]');
    const emptySubtitle = host.querySelector('[data-trend-empty-subtitle]');
    if (emptyTitle) emptyTitle.textContent = tab.emptyTitle || 'Menunggu data sensor';
    if (emptySubtitle) emptySubtitle.textContent = tab.emptySubtitle || '';
};

const bindHostTabs = (ApexCharts, host) => {
    if (boundTabHosts.has(host)) return;
    boundTabHosts.add(host);

    const switchTo = (key) => {
        const mount = host.querySelector('[data-trend-chart-mount]');
        if (mount?.id) activeTabByMount.set(mount.id, key);
        host.dataset.activeTab = key;
        syncTrendHost(ApexCharts, host, readPalette());
        const activeBtn = host.querySelector(`[data-chart-tab="${CSS.escape(key)}"]`);
        activeBtn?.scrollIntoView({ block: 'nearest', inline: 'nearest', container: 'nearest' });
    };

    host.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-chart-tab]');
        if (!button || !host.contains(button)) return;
        switchTo(button.dataset.chartTab);
    });

    host.addEventListener('keydown', (event) => {
        const current = event.target.closest?.('[data-chart-tab]');
        if (!current || !host.contains(current)) return;
        const buttons = [...host.querySelectorAll('[data-chart-tab]')];
        if (buttons.length < 2) return;
        const idx = buttons.indexOf(current);
        let next = -1;
        if (event.key === 'ArrowRight') next = (idx + 1) % buttons.length;
        else if (event.key === 'ArrowLeft') next = (idx - 1 + buttons.length) % buttons.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = buttons.length - 1;
        if (next < 0) return;

        event.preventDefault();
        buttons[next].focus();
        switchTo(buttons[next].dataset.chartTab);
    });
};

const syncTrendHost = (ApexCharts, host, palette) => {
    if (!host.isConnected) return;
    const mount = host.querySelector('[data-trend-chart-mount]');
    if (!(mount instanceof HTMLElement)) return;

    bindHostTabs(ApexCharts, host);

    const tabs = parseTabs(host);
    const savedKey = mount.id ? activeTabByMount.get(mount.id) : null;
    const chosenKey = savedKey || host.dataset.activeTab || tabs[0]?.key;
    const tab = tabs.find((item) => item.key === chosenKey) || tabs[0];
    if (!tab) return;

    if (mount.id) activeTabByMount.set(mount.id, tab.key);
    updateTrendCardChrome(host, tab);

    const points = Array.isArray(tab.points) ? tab.points : [];
    const emptyBox = host.querySelector('[data-trend-empty]');

    if (!points.length) {
        mount.hidden = true;
        if (emptyBox instanceof HTMLElement) emptyBox.hidden = false;
        return;
    }

    mount.hidden = false;
    if (emptyBox instanceof HTMLElement) emptyBox.hidden = true;

    const meta = {
        timezone: host.dataset.timezone || '',
        unit: tab.unit || '°C',
        tone: tab.tone || 'green',
        isPercent: Boolean(tab.isPercent),
        seriesName: tab.label || 'Suhu udara',
        thresholdMin: typeof tab.thresholdMin === 'number' && Number.isFinite(tab.thresholdMin) ? tab.thresholdMin : Number.NaN,
        thresholdMinLabel: tab.thresholdMinLabel || null,
        thresholdMax: typeof tab.thresholdMax === 'number' && Number.isFinite(tab.thresholdMax) ? tab.thresholdMax : Number.NaN,
        thresholdMaxLabel: tab.thresholdMaxLabel || null,
    };

    const signature = JSON.stringify({
        key: tab.key,
        points,
        thresholdMin: meta.thresholdMin,
        thresholdMax: meta.thresholdMax,
        tone: meta.tone,
        unit: meta.unit,
    });

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
