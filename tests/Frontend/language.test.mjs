import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { browser } from './browser.mjs';

let page;
before(async () => { page = await browser(); });
after(async () => { await page?.dispose(); });

const shell = `<div data-language-control hidden><select data-language-select data-custom-select aria-label="Bahasa tampilan"><option value="id" selected>Indonesia</option><option value="en" disabled>English (segera hadir)</option></select></div>`;

const state = () => page.evaluate(`({hidden: document.querySelector('[data-language-control]').hidden, lang: document.documentElement.lang, value: document.querySelector('[data-language-select]').value, stored: localStorage.getItem('growvia.language')})`);

test('the language control appears with Indonesian selected', async () => {
    await page.load(shell, ['language.js']);
    assert.deepEqual(await state(), { hidden: false, lang: 'id', value: 'id', stored: null });
    assert.deepEqual(page.errors, []);
});

test('choosing English falls back to Indonesian and never changes the document', async () => {
    await page.load(shell, ['language.js']);
    await page.evaluate(`(() => { const select = document.querySelector('[data-language-select]'); select.value = 'en'; select.dispatchEvent(new Event('change', { bubbles: true })); })()`);
    assert.deepEqual(await state(), { hidden: false, lang: 'id', value: 'id', stored: null });
    assert.deepEqual(page.errors, []);
});

test('without javascript the control stays hidden', async () => {
    await page.load(shell, []);
    assert.equal(await page.evaluate(`document.querySelector('[data-language-control]').hidden`), true);
});
