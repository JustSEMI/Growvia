import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { browser } from './browser.mjs';

let page;
before(async () => { page = await browser(); });
after(async () => { await page?.dispose(); });

const shell = `<nav aria-label="Navigasi utama"><a class="nav-link is-active" aria-current="page" href="#top" data-nav-section="top">Ringkasan</a><a class="nav-link" href="#node1" data-nav-section="node1">Iklim rumah kaca</a><a class="nav-link" href="#node2" data-nav-section="node2">Tanah &amp; air</a></nav><div style="height:1100px"></div><section id="node1" style="height:1000px"><h2>Iklim rumah kaca</h2></section><section id="node2" style="height:1000px"><h2>Tanah &amp; air</h2></section>`;

const active = () => page.evaluate(`document.querySelector('.nav-link.is-active')?.dataset.navSection ?? 'none'`);
const settle = () => page.evaluate(`new Promise(resolve => setTimeout(resolve, 200))`);

test('the overview link stays active when no node section is in view', async () => {
    await page.load(shell, ['app.js']);
    assert.equal(await active(), 'top');
    assert.deepEqual(page.errors, []);
});

test('scrolling to a node section moves the highlight and aria-current', async () => {
    await page.load(shell, ['app.js']);
    await page.evaluate(`document.getElementById('node1').scrollIntoView()`);
    await settle();
    assert.equal(await active(), 'node1');
    assert.deepEqual(await page.evaluate(`({current: document.querySelector('[data-nav-section="node1"]').getAttribute('aria-current'), top: document.querySelector('[data-nav-section="top"]').getAttribute('aria-current')})`), { current: 'page', top: null });
    await page.evaluate(`document.getElementById('node2').scrollIntoView()`);
    await settle();
    assert.equal(await active(), 'node2');
    await page.evaluate(`window.scrollTo(0, 0)`);
    await settle();
    assert.equal(await active(), 'top');
    assert.deepEqual(page.errors, []);
});

test('following a node anchor highlights its link without a reload', async () => {
    await page.load(shell, ['app.js']);
    await page.click('[data-nav-section="node2"]');
    assert.equal(await page.evaluate(`window.location.hash`), '#node2');
    assert.equal(await active(), 'node2');
    assert.deepEqual(page.errors, []);
});

test('without javascript the server-rendered overview highlight is kept', async () => {
    await page.load(shell, []);
    await page.evaluate(`document.getElementById('node2').scrollIntoView()`);
    await settle();
    assert.equal(await active(), 'top');
});
