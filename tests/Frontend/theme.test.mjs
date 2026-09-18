import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const source = (relative) => {
    const file = new URL(relative, root);
    return existsSync(file) ? readFileSync(file, 'utf8') : '';
};

function browser({ stored = null, dark = false, blocked = false } = {}) {
    const rootElement = { dataset: {}, style: {} };
    const meta = { content: '#f7f6f3', setAttribute(name, value) { this[name] = value; } };
    const values = new Map(stored === null ? [] : [['growvia.theme', stored]]);
    const listeners = new Map();
    const mediaListeners = [];
    const media = { matches: dark, addEventListener: (type, handler) => mediaListeners.push(handler) };
    const storage = {
        getItem: (key) => { if (blocked) throw Error('Storage unavailable'); return values.get(key) ?? null; },
        setItem: (key, value) => { if (blocked) throw Error('Storage unavailable'); values.set(key, value); },
    };
    const controls = [];
    const document = {
        documentElement: rootElement,
        querySelector: (selector) => selector === 'meta[name="theme-color"]' ? meta : null,
        querySelectorAll: () => controls,
        addEventListener: (type, handler) => listeners.set('document:' + type, handler),
    };
    const window = {
        localStorage: storage,
        matchMedia: () => media,
        addEventListener: (type, handler) => listeners.set('window:' + type, handler),
    };
    const context = vm.createContext({ document, window, Event, localStorage: storage, matchMedia: window.matchMedia });
    const run = (code) => vm.runInContext(code, context);
    return {
        root: rootElement, meta, values, media, controls, listeners,
        addControl() {
            const wrapper = { hidden: true };
            const events = new Map();
            const control = {
                value: 'system', wrapper,
                closest: () => wrapper,
                addEventListener: (type, handler) => events.set(type, handler),
                dispatchEvent: (event) => events.get(event.type)?.(event),
                choose(value) { control.value = value; events.get('change')?.({ target: control, currentTarget: control }); },
            };
            controls.push(control);
            return control;
        },
        boot() { run(source('resources/views/components/theme-bootstrap.blade.php').match(/<script[^>]*>([\s\S]*?)<\/script>/)?.[1] ?? ''); },
        initialize() { run(source('resources/js/theme.js')); },
        system(value) { media.matches = value; mediaListeners.forEach(handler => handler({ matches: value })); },
        storage(value, key = 'growvia.theme') {
            value === null ? values.delete(key) : values.set(key, value);
            listeners.get('window:storage')?.({ key, newValue: value, storageArea: storage });
        },
        restore() { listeners.get('window:pageshow')?.({ persisted: true }); },
    };
}

test('stored dark preference is applied before the body or app bundle exists', () => {
    const page = browser({ stored: 'dark', dark: false });
    page.boot();
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.root.dataset.themePreference, 'dark');
    assert.equal(page.root.style.colorScheme, 'dark');
    assert.equal(page.meta.content, '#171b18');
    assert.equal(page.values.get('growvia.theme'), 'dark');
});

test('selecting a theme persists it and synchronizes controls without submitting a form', () => {
    const page = browser();
    page.boot();
    const first = page.addControl();
    const second = page.addControl();
    page.initialize();
    assert.equal(first.wrapper.hidden, false);
    first.choose('dark');
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.root.dataset.themePreference, 'dark');
    assert.equal(page.values.get('growvia.theme'), 'dark');
    assert.equal(second.value, 'dark');
    assert.equal(page.meta.content, '#171b18');
});

test('system preference follows OS changes while explicit choices take priority', () => {
    const page = browser({ dark: false });
    page.boot();
    const control = page.addControl();
    page.initialize();
    assert.equal(page.root.dataset.themePreference, 'system');
    page.system(true);
    assert.equal(page.root.dataset.theme, 'dark');
    control.choose('light');
    page.system(false);
    page.system(true);
    assert.equal(page.root.dataset.theme, 'light');
    control.choose('system');
    assert.equal(page.root.dataset.theme, 'dark');
    page.system(false);
    assert.equal(page.root.dataset.theme, 'light');
    assert.equal(page.meta.content, '#f7f6f3');
});

test('a preference changed in another tab or cleared from storage updates this page', () => {
    const page = browser({ stored: 'light', dark: true });
    page.boot();
    const control = page.addControl();
    page.initialize();
    page.storage('dark');
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(control.value, 'dark');
    page.storage('light', 'unrelated.key');
    assert.equal(control.value, 'dark');
    page.storage(null);
    assert.equal(control.value, 'system');
    assert.equal(page.root.dataset.theme, 'dark');
});

test('restoring a cached page re-reads the latest browser preference', () => {
    const page = browser({ stored: 'light' });
    page.boot();
    const control = page.addControl();
    page.initialize();
    page.values.set('growvia.theme', 'dark');
    page.restore();
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(control.value, 'dark');
});

test('invalid or unavailable storage falls back to the system and never breaks theme changes', () => {
    for (const options of [{ stored: 'unexpected', dark: true }, { dark: true, blocked: true }]) {
        const page = browser(options);
        page.boot();
        const control = page.addControl();
        page.initialize();
        assert.equal(page.root.dataset.theme, 'dark');
        assert.equal(control.value, 'system');
        control.choose('light');
        assert.equal(page.root.dataset.theme, 'light');
    }
});

test('an explicit saved light choice overrides a dark operating system on first load', () => {
    const page = browser({ stored: 'light', dark: true });
    page.boot();
    assert.equal(page.root.dataset.theme, 'light');
    assert.equal(page.root.style.colorScheme, 'light');
    assert.equal(page.meta.content, '#f7f6f3');
});
