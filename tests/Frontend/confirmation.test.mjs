import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { browser } from './browser.mjs';

let page;
before(async () => { page = await browser(); });
after(async () => { await page?.dispose(); });

test('custom confirmation cancels safely then submits only after accepting', async () => {
    const dialog = `<dialog data-confirm-dialog><h2>Konfirmasi</h2><p data-confirm-text></p><button type="button" data-confirm-cancel autofocus>Batal</button><button type="button" data-confirm-accept>Hapus akun</button></dialog>`;
    await page.load(form + dialog, ['app.js']);
    await page.click('#delete-trigger');
    assert.equal(await page.evaluate(`document.querySelector('dialog').open && document.activeElement.hasAttribute('data-confirm-cancel')`), true);
    assert.equal(await page.evaluate(`window.submissions.filter(s=>!s.prevented).length`), 0);
    await page.key('Escape');
    assert.equal(await page.evaluate(`document.querySelector('dialog').open`), false);
    await page.click('#delete-trigger');
    await page.click('[data-confirm-cancel]');
    await page.click('#delete-trigger');
    await page.click('[data-confirm-accept]');
    assert.equal(await page.evaluate(`window.submissions.filter(s=>!s.prevented).length`), 1);
    assert.deepEqual(page.errors, []);
});

test('account status changes use their own safe confirmation text and original payload', async () => {
    const dialog = `<dialog data-confirm-dialog><h2 data-confirm-title>Konfirmasi</h2><p data-confirm-text></p><button type="button" data-confirm-cancel autofocus>Batal</button><button type="button" data-confirm-accept>Hapus akun</button></dialog>`;
    await page.load(`<form id="status" data-confirm-name="Petani Kebun" data-confirm-kind="deactivate"><input name="is_active" value="0" type="hidden"><button id="status-trigger" type="submit" data-confirm-trigger disabled>Nonaktifkan</button></form>` + dialog, ['app.js']);
    await page.click('#status-trigger');
    assert.equal(await page.evaluate(`document.querySelector('[data-confirm-accept]').textContent`), 'Nonaktifkan akun');
    assert.equal(await page.evaluate(`document.querySelector('[data-confirm-text]').textContent.includes('Sesi aktifnya akan diakhiri')`), true);
    await page.click('[data-confirm-accept]');
    assert.deepEqual(await page.evaluate(`window.submissions.filter(s=>!s.prevented).map(s=>s.payload)`), [{is_active:'0'}]);
});

const form = `<form id="delete" data-confirm-name="Petani Kebun"><button id="delete-trigger" type="submit" data-confirm-trigger disabled>Hapus</button></form><p data-confirm-required>Konfirmasi hapus memerlukan JavaScript dan dukungan dialog browser.</p>`;

test('unavailable custom confirmation keeps deletion disabled and never falls back to window.confirm', async () => {
    await page.load(form + `<script>window.confirmCalls=0;window.confirm=()=>{window.confirmCalls++;return true};</script>`, ['app.js']);
    assert.equal(await page.evaluate(`document.querySelector('#delete-trigger').disabled`), true);
    await page.evaluate(`document.querySelector('#delete').requestSubmit()`);
    assert.deepEqual(await page.evaluate(`({confirm:window.confirmCalls,prevented:window.submissions.at(-1).prevented,notice:!document.querySelector('[data-confirm-required]').hidden})`), { confirm: 0, prevented: true, notice: true });
});
