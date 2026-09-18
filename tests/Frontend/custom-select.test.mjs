import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { browser } from './browser.mjs';

let page;
before(async () => { page = await browser(); });
after(async () => { await page?.dispose(); });

test('keyboard exploration can be canceled or committed without trapping Tab', async () => {
    await page.load(field);
    await page.evaluate(`document.querySelector('[role="combobox"]').focus()`);
    await page.key('ArrowDown');
    await page.key('End');
    assert.deepEqual(await page.evaluate(`({value:document.querySelector('#role').value,active:document.getElementById(document.querySelector('[role="combobox"]').getAttribute('aria-activedescendant'))?.dataset.value})`), { value: 'user', active: 'admin' });
    await page.key('Escape');
    assert.equal(await page.evaluate(`document.querySelector('#role').value`), 'user');
    await page.key('a');
    await page.key('Enter');
    assert.equal(await page.evaluate(`document.querySelector('#role').value`), 'admin');
    await page.key('Home');
    await page.key('Tab');
    assert.deepEqual(await page.evaluate(`({value:document.querySelector('#role').value,focus:document.activeElement.id,expanded:document.querySelector('[role="combobox"]').getAttribute('aria-expanded')})`), { value: 'user', focus: 'save', expanded: 'false' });
});

test('outside click dismisses the list without stealing focus from another control', async () => {
    await page.load(field + '<button id="outside" type="button" style="position:fixed;right:24px;top:24px">Kontrol lain</button>');
    await page.click('[role="combobox"]');
    await page.click('#outside');
    assert.deepEqual(await page.evaluate(`({expanded:document.querySelector('[role="combobox"]').getAttribute('aria-expanded'),focus:document.activeElement.id})`), { expanded: 'false', focus: 'outside' });
    await page.click('label');
    assert.equal(await page.evaluate(`document.activeElement.getAttribute('role')`), 'combobox');
});

test('the menu stays inside a narrow viewport and closes when the page scrolls', async () => {
    await page.cdp('Emulation.setDeviceMetricsOverride', { width: 375, height: 400, deviceScaleFactor: 1, mobile: true });
    await page.load(`<style>body{min-height:1600px}.select-trigger{width:100%}#account{position:fixed;right:6px;bottom:20px;width:300px}</style>` + field);
    await page.click('[role="combobox"]');
    assert.equal(await page.evaluate(`(() => {const r=document.querySelector('[role="listbox"]').getBoundingClientRect();const t=document.querySelector('[role="combobox"]').getBoundingClientRect();return r.left>=8&&r.right<=document.documentElement.clientWidth-8&&r.top>=8&&r.bottom<=t.top;})()`), true);
    await page.evaluate(`scrollTo(0,200);new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))`);
    assert.equal(await page.evaluate(`document.querySelector('[role="combobox"]').getAttribute('aria-expanded')`), 'false');
    await page.cdp('Emulation.setDeviceMetricsOverride', { width: 1000, height: 800, deviceScaleFactor: 1, mobile: false });
});

test('form reset restores both the selected label and original submitted value', async () => {
    await page.load(field);
    await page.click('[role="combobox"]');
    await page.click('[data-value="admin"]');
    await page.evaluate(`document.querySelector('form').reset();new Promise(resolve=>setTimeout(resolve,0))`);
    assert.deepEqual(await page.evaluate(`({value:document.querySelector('#role').value,label:document.querySelector('[data-select-label]').textContent,selected:document.querySelector('[aria-selected="true"]').dataset.value})`), { value: 'user', label: 'Petani', selected: 'user' });
});

test('theme storage and restored pages synchronize the custom visible choice', async () => {
    const theme = `<div data-theme-control hidden><select data-theme-select data-custom-select aria-label="Tema tampilan"><option value="system">Sistem</option><option value="light">Terang</option><option value="dark">Gelap</option></select></div>`;
    await page.load(theme, ['theme.js', 'custom-select.js']);
    await page.evaluate(`localStorage.setItem('growvia.theme','dark');window.dispatchEvent(new StorageEvent('storage',{key:'growvia.theme',newValue:'dark',storageArea:localStorage}))`);
    assert.deepEqual(await page.evaluate(`({theme:document.documentElement.dataset.theme,label:document.querySelector('[data-select-label]').textContent})`), { theme: 'dark', label: 'Gelap' });
    await page.evaluate(`localStorage.setItem('growvia.theme','light');window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}))`);
    assert.equal(await page.evaluate(`document.querySelector('[data-select-label]').textContent`), 'Terang');
});

test('labels and server error links focus the custom control while preserving safe option text', async () => {
    await page.load(`<a id="error-link" href="#role">Periksa role</a><p id="role-error">Pilih role yang tersedia.</p>` + field.replace('data-custom-select required', 'data-custom-select required aria-invalid="true" aria-describedby="role-error"').replace('Administrator</option>', '&lt;img src=x onerror=alert(1)&gt;</option>'));
    await page.click('#error-link');
    assert.deepEqual(await page.evaluate(`({focus:document.activeElement.getAttribute('role'),invalid:document.querySelector('[role="combobox"]').getAttribute('aria-invalid'),description:document.querySelector('[role="combobox"]').getAttribute('aria-describedby'),images:document.querySelectorAll('[role="option"] img').length})`), { focus: 'combobox', invalid: 'true', description: 'role-error', images: 0 });
    await page.evaluate(`document.querySelector('#role').disabled=true;new Promise(resolve=>setTimeout(resolve,0))`);
    assert.equal(await page.evaluate(`document.querySelector('[role="combobox"]').disabled`), true);
});

test('disabled choices cannot be selected and plain HTML still works without the enhancement', async () => {
    const disabled = field.replace('<option value="admin">', '<option value="admin" disabled>');
    await page.load(disabled);
    await page.click('[role="combobox"]');
    await page.key('End');
    await page.key('Enter');
    assert.equal(await page.evaluate(`document.querySelector('#role').value`), 'user');
    await page.load(field, []);
    assert.deepEqual(await page.evaluate(`({hidden:document.querySelector('#role').hidden,value:new FormData(document.querySelector('form')).get('role'),custom:!!document.querySelector('[role="combobox"]')})`), { hidden: false, value: 'user', custom: false });
});

test('Space commits an option reached by typing its name', async () => {
    await page.load(field);
    await page.evaluate(`document.querySelector('[role="combobox"]').focus()`);
    await page.key('a');
    await page.key(' ');
    assert.deepEqual(await page.evaluate(`({value:document.querySelector('#role').value,expanded:document.querySelector('[role="combobox"]').getAttribute('aria-expanded')})`), { value: 'admin', expanded: 'false' });
});

test('the accessibility tree exposes the combobox name and selected value', async () => {
    await page.load(field);
    const tree = await page.cdp('Accessibility.getFullAXTree');
    const combo = tree.nodes.find(node => node.role?.value === 'combobox');
    assert.equal(combo?.name?.value, 'Role akun');
    assert.equal(combo?.value?.value, 'Petani');
    await page.click('[role="combobox"]');
    const expanded = await page.cdp('Accessibility.getFullAXTree');
    assert.equal(expanded.nodes.filter(node => node.role?.value === 'option').length, 2);
});

const field = `<form id="account"><label for="role">Role akun</label><select id="role" name="role" data-custom-select required><option value="user" selected>Petani</option><option value="admin">Administrator</option></select><button id="save" type="submit">Simpan</button></form>`;

test('a custom listbox selects the original form value without submitting', async () => {
    await page.load(field, ['app.js']);
    assert.equal(await page.evaluate(`!!document.querySelector('[role="combobox"]')`), true);
    assert.equal(await page.evaluate(`document.querySelector('#role').hidden`), true);
    await page.click('[role="combobox"]');
    assert.equal(await page.evaluate(`document.querySelector('[role="combobox"]').getAttribute('aria-expanded')`), 'true');
    await page.click('[role="option"][data-value="admin"]');
    assert.deepEqual(await page.evaluate(`({value:document.querySelector('#role').value,payload:new FormData(document.querySelector('form')).get('role'),label:document.querySelector('[data-select-label]').textContent,expanded:document.querySelector('[role="combobox"]').getAttribute('aria-expanded'),submits:window.submissions.length})`), { value: 'admin', payload: 'admin', label: 'Administrator', expanded: 'false', submits: 0 });
    await page.click('#save');
    assert.equal(await page.evaluate(`window.submissions.at(-1).payload.role`), 'admin');
});
