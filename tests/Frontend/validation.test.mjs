import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { browser } from './browser.mjs';

let page;
before(async () => { page = await browser(); });
after(async () => { await page?.dispose(); });

test('email and numeric constraints use inline messages without changing server validation', async () => {
    await page.load(field.replace('</form>', '<div class="field"><label for="level">Level air</label><input id="level" name="level" type="number" min="0" max="100" step="1" value="101"></div></form>'), ['validation.js']);
    await page.evaluate(`document.querySelector('#email').value='not-email'`);
    await page.click('#save');
    assert.equal(await page.evaluate(`window.submissions.at(-1).prevented`), true);
    assert.equal(await page.evaluate(`document.querySelector('#email-client-error')?.textContent`), 'Masukkan alamat email yang valid.');
    assert.equal(await page.evaluate(`document.querySelector('#level-client-error')?.textContent`), 'Level air maksimal 100.');
    await page.evaluate(`document.querySelector('#email').value='petani@example.test';document.querySelector('#level').value='-1'`);
    await page.click('#save');
    assert.equal(await page.evaluate(`document.querySelector('#level-client-error')?.textContent`), 'Level air minimal 0.');
    await page.evaluate(`document.querySelector('#level').value='1.5'`);
    await page.click('#save');
    assert.equal(await page.evaluate(`document.querySelector('#level-client-error')?.textContent`), 'Level air harus sesuai kelipatan 1.');
});

test('client validation resets cleanly and leaves other forms alone', async () => {
    await page.load(field + '<form id="logout"><button type="submit">Keluar</button></form>', ['app.js']);
    await page.click('#save');
    assert.equal(await page.evaluate(`document.querySelector('#profile').hasAttribute('aria-busy')`), false);
    await page.click('#logout button');
    assert.equal(await page.evaluate(`window.submissions.at(-1).prevented`), false);
    await page.evaluate(`document.querySelector('#profile').reset();new Promise(resolve=>setTimeout(resolve,0))`);
    assert.equal(await page.evaluate(`document.querySelectorAll('[data-client-error],[data-client-summary]').length`), 0);
});

test('errors stay linked when a hidden native select is enhanced and clear on correction', async () => {
    await page.load(`<form><div class="field"><label for="mode">Mode</label><select id="mode" name="mode" data-custom-select required><option value="">Pilih mode</option><option value="auto">Otomatis</option></select><p id="mode-hint">Konfigurasi saja.</p></div><button id="save" type="submit">Simpan</button></form>`, ['app.js']);
    await page.click('#save');
    assert.equal(await page.evaluate(`document.activeElement.getAttribute('role')`), 'combobox');
    assert.equal(await page.evaluate(`document.querySelector('[role="combobox"]').getAttribute('aria-describedby')`), 'mode-client-error');
    await page.key('End');
    await page.key('Enter');
    assert.equal(await page.evaluate(`document.querySelector('[data-client-error]')`), null);
    await page.click('#save');
    assert.equal(await page.evaluate(`window.submissions.at(-1).prevented`), false);
});

test('multiple client errors focus a linked summary rather than hiding the overall problem', async () => {
    await page.load(field.replace('</form>', '<div class="field"><label for="name">Nama</label><input id="name" name="name" required></div></form>'), ['validation.js']);
    await page.click('#save');
    assert.equal(await page.evaluate(`document.activeElement.hasAttribute('data-client-summary')`), true);
    await page.click('[data-client-summary] a[href="#name"]');
    assert.equal(await page.evaluate(`document.activeElement.id`), 'name');
});

const field = `<form novalidate id="profile"><div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" required></div><button id="save" type="submit">Simpan</button></form>`;

test('invalid form data stays on the page with custom inline errors instead of browser popups', async () => {
    await page.load(field, ['validation.js']);
    await page.click('#save');
    assert.deepEqual(await page.evaluate(`({blocked:window.submissions.at(-1)?.prevented,invalid:document.querySelector('#email').getAttribute('aria-invalid'),message:document.querySelector('[data-client-error]')?.textContent,summary:document.querySelector('[data-client-summary]')?.textContent,focus:document.activeElement.id})`), { blocked: true, invalid: 'true', message: 'Alamat email wajib diisi.', summary: 'Periksa kembali isian berikut.Alamat email wajib diisi.', focus: 'email' });
    await page.evaluate(`document.querySelector('#email').value='petani@example.test';document.querySelector('#email').dispatchEvent(new Event('input',{bubbles:true}))`);
    await page.click('#save');
    assert.equal(await page.evaluate(`window.submissions.at(-1).prevented`), false);
    assert.equal(await page.evaluate(`document.querySelector('[data-client-error]')`), null);
});
