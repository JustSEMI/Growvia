import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { browser } from './browser.mjs';

let page;
before(async () => { page = await browser(); });
after(async () => { await page?.dispose(); });

const shell = `<button type="button" data-sidebar-toggle aria-label="Ciutkan navigasi" aria-expanded="true" hidden>Alihkan</button><aside class="sidebar"><nav aria-label="Navigasi utama"><a href="#top">Ringkasan</a></nav></aside>`;

const state = () => page.evaluate(`({hidden: document.querySelector('[data-sidebar-toggle]').hidden, sidebar: document.documentElement.dataset.sidebar, label: document.querySelector('[data-sidebar-toggle]').getAttribute('aria-label'), expanded: document.querySelector('[data-sidebar-toggle]').getAttribute('aria-expanded'), stored: localStorage.getItem('growvia.sidebar')})`);

test('the toggle appears and keeps the sidebar expanded by default', async () => {
    await page.load(shell, ['app.js']);
    assert.deepEqual(await state(), { hidden: false, sidebar: 'expanded', label: 'Ciutkan navigasi', expanded: 'true', stored: null });
    assert.deepEqual(page.errors, []);
});

test('toggling collapses to icons and expands back with a persisted preference', async () => {
    await page.load(shell, ['app.js']);
    await page.click('[data-sidebar-toggle]');
    assert.deepEqual(await state(), { hidden: false, sidebar: 'collapsed', label: 'Bentangkan navigasi', expanded: 'false', stored: 'collapsed' });
    await page.click('[data-sidebar-toggle]');
    assert.deepEqual(await state(), { hidden: false, sidebar: 'expanded', label: 'Ciutkan navigasi', expanded: 'true', stored: 'expanded' });
    await page.evaluate(`localStorage.setItem('growvia.sidebar', 'collapsed')`);
    await page.load(shell, ['app.js']);
    assert.deepEqual(await state(), { hidden: false, sidebar: 'collapsed', label: 'Bentangkan navigasi', expanded: 'false', stored: 'collapsed' });
    assert.deepEqual(page.errors, []);
});

test('without javascript the toggle stays hidden and the sidebar stays expanded', async () => {
    await page.load(shell, []);
    assert.deepEqual(await page.evaluate(`({hidden: document.querySelector('[data-sidebar-toggle]').hidden, sidebar: document.documentElement.dataset.sidebar ?? 'unset'})`), { hidden: true, sidebar: 'unset' });
});
