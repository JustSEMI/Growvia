import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { after, before, test } from 'node:test';
import { browser } from './browser.mjs';

let page;
before(async () => { page = await browser(); });
after(async () => { await page?.dispose(); });

const componentPath = new URL('../../resources/views/components/notifications.blade.php', import.meta.url);
const alertsPath = new URL('../../resources/views/components/alerts.blade.php', import.meta.url);
const flashMessage = 'Tautan reset dikirim ke akun@example.test. <script>window.injected=true</script>';
const flash = () => readFileSync(alertsPath, 'utf8').split("@if(session('status'))")[1].split('@endif')[0]
    .replace("{{ session('status') }}", flashMessage.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;'))
    .replace(/<x-icon[^>]+\/>/g, '<svg aria-hidden="true"></svg>');
const shell = '<main id="main"><button id="origin" type="button">Simpan</button><button id="other" type="button">Lanjut</button></main>';
const component = () => existsSync(componentPath)
    ? readFileSync(componentPath, 'utf8').replace(/<x-icon\s+name="([^"]+)"\s*\/>/g, '<svg data-icon="$1" aria-hidden="true" width="20" height="20"></svg>')
    : '';

const clock = `<script>
    let now = 0;
    let nextId = 0;
    const timers = new Map();
    Object.defineProperty(performance, 'now', { value: () => now });
    window.setTimeout = (callback, delay = 0) => {
        const id = ++nextId;
        timers.set(id, { at: now + Math.max(0, Number(delay) || 0), callback });
        return id;
    };
    window.clearTimeout = id => timers.delete(id);
    window.clock = {
        tick(milliseconds) {
            const end = now + milliseconds;
            let next;
            while ((next = [...timers].filter(([, timer]) => timer.at <= end).sort((a, b) => a[1].at - b[1].at)[0])) {
                now = next[1].at;
                timers.delete(next[0]);
                next[1].callback();
            }
            now = end;
        },
        get pending() { return timers.size; },
    };
</script>`;
const tick = milliseconds => page.evaluate(`window.clock.tick(${milliseconds})`);
const count = () => page.evaluate(`document.querySelectorAll('[data-toast-region] .toast').length`);
const hoverToast = async () => {
    const point = await page.evaluate(`(() => { const box = document.querySelector('.toast').getBoundingClientRect(); return { x: box.x + 5, y: box.y + 5 }; })()`);
    await page.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point });
};
const leaveToast = () => page.cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 1, y: 1 });

const send = (message, options = {}) => page.evaluate(`import('/notifications.js').then(module => {
    if (typeof module.notify !== 'function') return false;
    return !!module.notify(${JSON.stringify(message)}, ${JSON.stringify(options)});
})`);

test('only the escaped success flash exposes toast enhancement hooks', () => {
    const alerts = readFileSync(alertsPath, 'utf8');
    const status = alerts.split('@if($errors->any())')[0];
    const errors = alerts.split('@if($errors->any())')[1];
    assert.match(status, /data-flash-message/);
    assert.match(status, /data-flash-tone="success"/);
    assert.match(status, /<p data-flash-text>\{\{ session\('status'\) \}\}<\/p>/);
    assert.match(status, /role="status"/);
    assert.match(errors, /role="alert"/);
    assert.match(errors, /data-error-summary/);
    assert.doesNotMatch(errors, /data-flash-/);
});

test('success notifications use safe text in an accessible custom region', async () => {
    await page.load(clock + shell + component(), ['notifications.js']);
    await page.evaluate(`document.querySelector('#origin').focus(); window.alert = () => { throw Error('Native alert must not run'); }; window.Notification = class { static requestPermission() { throw Error('System notifications must not run'); } };`);
    const message = '<img src=x onerror="window.injected=true"> Profil & akun';
    const title = '<b>Berhasil</b>';
    assert.equal(await send(message, { tone: 'success', title }), true);
    await tick(100);
    assert.deepEqual(await page.evaluate(`(() => {
        const region = document.querySelector('[data-toast-region]');
        const toast = region.querySelector('.toast');
        const live = document.querySelector('[data-toast-live]');
        return {
            role: region.getAttribute('role'), label: region.getAttribute('aria-label'),
            message: toast.querySelector('[data-toast-message]').textContent,
            title: toast.querySelector('[data-toast-title]').textContent,
            unsafeNodes: toast.querySelectorAll('img, b, script').length,
            tone: toast.dataset.tone, focus: document.activeElement.id,
            closeType: toast.querySelector('[data-toast-close]').type,
            closeLabel: toast.querySelector('[data-toast-close]').getAttribute('aria-label'),
            liveRole: live.getAttribute('role'), liveMode: live.getAttribute('aria-live'),
            liveAtomic: live.getAttribute('aria-atomic'), announcement: live.textContent,
            toastLive: toast.hasAttribute('aria-live') || ['status', 'alert'].includes(toast.getAttribute('role')),
            injected: !!window.injected,
        };
    })()`), {
        role: 'region', label: 'Notifikasi', message, title, unsafeNodes: 0,
        tone: 'success', focus: 'origin', closeType: 'button', closeLabel: 'Tutup notifikasi',
        liveRole: 'status', liveMode: 'polite', liveAtomic: 'true', announcement: `${title}. ${message}`,
        toastLive: false, injected: false,
    });
    assert.deepEqual(page.errors, []);
});

test('tones have meaningful default titles and a matching decorative icon', async () => {
    for (const [tone, title, expectedTone] of [
        ['success', 'Berhasil', 'success'], ['error', 'Terjadi kesalahan', 'error'],
        ['info', 'Informasi', 'info'], ['warning', 'Perhatian', 'warning'],
        ['constructor', 'Informasi', 'info'],
    ]) {
        await page.load(shell + component(), ['notifications.js']);
        await send('Pesan', { tone });
        assert.deepEqual(await page.evaluate(`(() => {
            const toast = document.querySelector('.toast');
            return {
                title: toast.querySelector('[data-toast-title]').textContent,
                tone: toast.dataset.tone,
                icons: [...toast.querySelectorAll('.toast-icon svg')].filter(icon => getComputedStyle(icon).display !== 'none').map(icon => icon.dataset.icon),
            };
        })()`), { title, tone: expectedTone, icons: [expectedTone === 'success' ? 'check' : 'info'] });
    }
});

test('keyboard close removes the toast and safely returns focus to its origin', async () => {
    await page.load(shell + component(), ['notifications.js']);
    await page.click('#origin');
    await send('Profil disimpan.', { tone: 'success' });
    await page.evaluate(`document.querySelector('[data-toast-close]').focus()`);
    await page.cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r' });
    await page.cdp('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
    assert.deepEqual(await page.evaluate(`({toasts: document.querySelectorAll('.toast').length, focus: document.activeElement.id, submits: window.submissions.length})`), { toasts: 0, focus: 'origin', submits: 0 });
});

test('close falls back to main when its origin cannot safely receive focus', async () => {
    for (const change of ['origin.hidden = true', 'origin.disabled = true', 'origin.remove()', 'origin.style.opacity = "0"', 'origin.setAttribute("aria-hidden", "true")', 'origin.setAttribute("inert", "")']) {
        await page.load(shell + component(), ['notifications.js']);
        await page.evaluate(`document.querySelector('#origin').focus()`);
        await send('Profil disimpan.', { tone: 'success' });
        await page.evaluate(`const origin = document.querySelector('#origin'); ${change}`);
        await page.click('[data-toast-close]');
        assert.deepEqual(await page.evaluate(`({count: document.querySelectorAll('.toast').length, focus: document.activeElement.id, tabIndex: document.activeElement.tabIndex})`), { count: 0, focus: 'main', tabIndex: -1 }, change);
        await page.click('#other');
        assert.equal(await page.evaluate(`document.querySelector('main').hasAttribute('tabindex')`), false);
    }
});

test('success and info expire after seven seconds while explicit durations are respected', async () => {
    for (const options of [{ tone: 'success' }, { tone: 'info' }, { duration: 1200 }]) {
        await page.load(clock + shell + component(), ['notifications.js']);
        await send('Selesai.', options);
        const duration = options.duration ?? 7000;
        await tick(duration - 1);
        assert.equal(await count(), 1);
        await tick(1);
        assert.equal(await count(), 0);
        assert.equal(await page.evaluate('window.clock.pending'), 0);
    }
});

test('critical messages and explicitly persistent notices wait for manual dismissal', async () => {
    for (const options of [{ tone: 'warning' }, { tone: 'error' }, { tone: 'error', duration: 100 }, { tone: 'warning', duration: 100 }, { tone: 'info', duration: 0 }]) {
        await page.load(clock + shell + component(), ['notifications.js']);
        await send('Periksa koneksi perangkat.', options);
        await tick(600000);
        assert.equal(await count(), 1, JSON.stringify(options));
        await page.click('[data-toast-close]');
        assert.equal(await count(), 0);
        assert.equal(await page.evaluate('window.clock.pending'), 0);
    }
});

test('hover pauses expiration and resumes only the time still remaining', async () => {
    await page.load(clock + shell + component(), ['notifications.js']);
    await leaveToast();
    await send('Profil disimpan.', { tone: 'success', duration: 1000 });
    await tick(400);
    await hoverToast();
    await tick(10000);
    assert.equal(await count(), 1);
    await leaveToast();
    await tick(599);
    assert.equal(await count(), 1);
    await tick(1);
    assert.equal(await count(), 0);
});

test('focused toast UI never expires when hover ends and resumes after focus leaves', async () => {
    await page.load(clock + shell + component(), ['notifications.js']);
    await leaveToast();
    await send('Profil disimpan.', { tone: 'success', duration: 1000 });
    await tick(400);
    await page.evaluate(`document.querySelector('[data-toast-close]').focus()`);
    await hoverToast();
    await leaveToast();
    await tick(10000);
    assert.equal(await count(), 1);
    assert.equal(await page.evaluate(`document.activeElement.matches('[data-toast-close]')`), true);
    await page.evaluate(`document.querySelector('#other').focus()`);
    await tick(599);
    assert.equal(await count(), 1);
    await tick(1);
    assert.equal(await count(), 0);
    assert.equal(await page.evaluate('document.activeElement.id'), 'other');
});

test('hidden documents suspend toast time including notifications created in the background', async () => {
    for (const initiallyHidden of [false, true]) {
        await page.load(clock + shell + component(), ['notifications.js']);
        await leaveToast();
        await page.evaluate(`window.pageHidden = ${initiallyHidden}; Object.defineProperty(document, 'hidden', { get: () => window.pageHidden }); window.setPageHidden = value => { window.pageHidden = value; document.dispatchEvent(new Event('visibilitychange')); };`);
        await send('Profil disimpan.', { tone: 'success', duration: 1000 });
        if (!initiallyHidden) await tick(400);
        await page.evaluate('setPageHidden(true)');
        await tick(10000);
        assert.equal(await count(), 1);
        await page.evaluate(`document.querySelector('[data-toast-close]').focus(); setPageHidden(false)`);
        await tick(10000);
        assert.equal(await count(), 1);
        await page.evaluate(`document.querySelector('#other').focus()`);
        await tick(initiallyHidden ? 999 : 599);
        assert.equal(await count(), 1);
        await tick(1);
        assert.equal(await count(), 0);
    }
});

test('flash enhancement preserves a visible receipt without duplicate live announcements', async () => {
    await page.load(clock + shell + flash() + '<div role="alert" data-error-summary>Isian belum lengkap.</div>' + component(), ['app.js']);
    await tick(100);
    assert.equal(await count(), 1);
    assert.deepEqual(await page.evaluate(`(() => {
        const receipt = document.querySelector('[data-flash-message]');
        const live = document.querySelector('[data-toast-live]');
        return {
            text: document.querySelector('.toast [data-toast-message]').textContent,
            receiptText: receipt.querySelector('[data-flash-text]').textContent,
            compact: receipt.classList.contains('flash-receipt'),
            visible: !receipt.hidden && getComputedStyle(receipt).display !== 'none' && receipt.getClientRects().length > 0,
            receiptRole: receipt.getAttribute('role'), receiptLive: receipt.getAttribute('aria-live'),
            receiptHidden: receipt.getAttribute('aria-hidden'), liveText: live.textContent,
            errorRole: document.querySelector('[data-error-summary]').getAttribute('role'),
            injected: !!window.injected,
        };
    })()`), {
        text: flashMessage, receiptText: flashMessage, compact: true, visible: true,
        receiptRole: null, receiptLive: 'off', receiptHidden: null,
        liveText: `Berhasil. ${flashMessage}`, errorRole: 'alert', injected: false,
    });
    await leaveToast();
    await tick(7000);
    assert.equal(await count(), 0);
    assert.equal(await page.evaluate(`document.querySelector('.flash-receipt [data-flash-text]').textContent`), flashMessage);
    await page.evaluate(`window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }))`);
    assert.equal(await count(), 0);
});

test('missing or incomplete toast infrastructure leaves the flash fallback untouched', async () => {
    const variants = [
        '<div data-toast-region></div><template data-toast-template></template>',
        component().replace(/<div[^>]+data-toast-region[^>]*>[\s\S]*?<\/div>/, ''),
        component().replace(/<template[\s\S]*<\/template>/, ''),
        component().replace('data-toast-message', 'data-missing-message'),
        component().replace(/<div[^>]+data-toast-live[^>]*><\/div>/, ''),
    ];
    for (const markup of variants) {
        await page.load(shell + flash() + markup, ['notifications.js']);
        assert.equal(await count(), 0);
        assert.equal(await send('Pesan'), false);
        assert.deepEqual(await page.evaluate(`(() => {
            const receipt = document.querySelector('[data-flash-message]');
            return { role: receipt.getAttribute('role'), live: receipt.getAttribute('aria-live'), compact: receipt.classList.contains('flash-receipt'), visible: !receipt.hidden && receipt.getClientRects().length > 0, text: receipt.querySelector('[data-flash-text]').textContent };
        })()`), { role: 'status', live: null, compact: false, visible: true, text: flashMessage });
        assert.deepEqual(page.errors, []);
    }
});

test('a three-toast stack queues overflow without discarding critical or focused notices', async () => {
    await page.load(clock + shell + component(), ['notifications.js']);
    await leaveToast();
    await page.evaluate(`document.querySelector('#origin').focus()`);
    await send('Kritis 1', { tone: 'error' });
    await page.evaluate(`document.querySelector('[data-toast-close]').focus()`);
    for (const message of ['Kritis 2', 'Kritis 3', 'Kritis 4']) await send(message, { tone: 'warning' });
    await send('Informasi 5', { duration: 1000 });
    assert.equal(await count(), 3);
    assert.deepEqual(await page.evaluate(`({ messages: [...document.querySelectorAll('.toast [data-toast-message]')].map(node => node.textContent), focused: document.activeElement.closest('.toast').querySelector('[data-toast-message]').textContent, waiting: document.querySelector('[data-toast-pending]').textContent, waitingHidden: document.querySelector('[data-toast-pending]').hidden })`), { messages: ['Kritis 1', 'Kritis 2', 'Kritis 3'], focused: 'Kritis 1', waiting: '2 notifikasi menunggu. Tutup notifikasi untuk melihat berikutnya.', waitingHidden: false });
    assert.equal(await page.evaluate(`(() => {
        const boxes = [...document.querySelectorAll('.toast')].map(node => node.getBoundingClientRect()).sort((a, b) => a.top - b.top);
        return boxes.every((box, index) => index === 0 || box.top >= boxes[index - 1].bottom);
    })()`), true);
    await tick(10000);
    assert.equal(await count(), 3);
    await page.click('[data-toast-close]');
    assert.equal(await page.evaluate(`document.activeElement.id`), 'origin');
    assert.deepEqual(await page.evaluate(`[...document.querySelectorAll('.toast [data-toast-message]')].map(node => node.textContent)`), ['Kritis 2', 'Kritis 3', 'Kritis 4']);
    await page.click('[data-toast-close]');
    await leaveToast();
    assert.deepEqual(await page.evaluate(`[...document.querySelectorAll('.toast [data-toast-message]')].map(node => node.textContent)`), ['Kritis 3', 'Kritis 4', 'Informasi 5']);
    assert.equal(await page.evaluate(`document.querySelector('[data-toast-pending]').hidden`), true);
    await tick(999);
    assert.equal(await count(), 3);
    await tick(1);
    assert.equal(await count(), 2);
    await page.click('[data-toast-close]');
    await page.click('[data-toast-close]');
    assert.equal(await count(), 0);
    assert.equal(await page.evaluate('window.clock.pending'), 0);
});

test('a flash with no originating control returns keyboard focus to main on close', async () => {
    await page.load(shell + flash() + component(), ['notifications.js']);
    await page.click('[data-toast-close]');
    assert.equal(await count(), 0);
    assert.equal(await page.evaluate('document.activeElement.id'), 'main');
    assert.equal(await page.evaluate(`document.querySelector('[data-flash-text]').textContent`), flashMessage);
});

test('live announcements retain each message in a burst and announce repeated text again', async () => {
    await page.load(clock + shell + component(), ['notifications.js']);
    await page.evaluate(`window.announcements = []; new MutationObserver(() => announcements.push(document.querySelector('[data-toast-live]').textContent)).observe(document.querySelector('[data-toast-live]'), { childList: true, subtree: true });`);
    await page.evaluate(`import('/notifications.js').then(({ notify }) => { notify('Profil disimpan.', { tone: 'success' }); notify('Perangkat terputus.', { tone: 'error' }); })`);
    await tick(100);
    assert.deepEqual(await page.evaluate('announcements.filter(Boolean)'), ['Berhasil. Profil disimpan. Terjadi kesalahan. Perangkat terputus.']);
    await page.evaluate(`document.querySelectorAll('[data-toast-close]').forEach(button => button.click())`);
    await send('Profil disimpan.', { tone: 'success' });
    await tick(100);
    await page.evaluate(`document.querySelector('[data-toast-close]').click()`);
    await send('Profil disimpan.', { tone: 'success' });
    assert.equal(await page.evaluate(`document.querySelector('[data-toast-live]').textContent`), '');
    await tick(100);
    assert.deepEqual(await page.evaluate('announcements.filter(Boolean)'), ['Berhasil. Profil disimpan. Terjadi kesalahan. Perangkat terputus.', 'Berhasil. Profil disimpan.', 'Berhasil. Profil disimpan.']);
});

test('manual dismissal cancels pending announcements and timers without stealing outside focus', async () => {
    await page.load(clock + shell + component(), ['notifications.js']);
    await page.evaluate(`document.querySelector('#origin').focus()`);
    await send('Profil disimpan.', { tone: 'success' });
    await page.evaluate(`document.querySelector('#other').focus(); document.querySelector('[data-toast-close]').click()`);
    assert.equal(await count(), 0);
    assert.equal(await page.evaluate('document.activeElement.id'), 'other');
    assert.equal(await page.evaluate('window.clock.pending'), 0);
    await tick(10000);
    assert.equal(await page.evaluate(`document.querySelector('[data-toast-live]').textContent`), '');
});

test('unknown or blank messages never create empty dismissible UI', async () => {
    await page.load(shell + component(), ['notifications.js']);
    for (const message of ['', '  \n\t ', null, undefined]) assert.equal(await send(message), false);
    assert.equal(await count(), 0);
});

test('toast placement avoids an overlapping keyboard-focused control', async () => {
    await page.load(shell + '<button id="near-toast" style="position:fixed;right:30px;bottom:30px;width:180px;height:44px">Kontrol bawah</button>' + component(), ['notifications.js']);
    await send('Profil tersimpan.', { tone: 'success' });
    await page.evaluate(`document.querySelector('#near-toast').focus()`);
    assert.deepEqual(await page.evaluate(`({focus:document.activeElement.id,placement:document.querySelector('[data-toast-region]').dataset.placement,count:document.querySelectorAll('.toast').length})`), { focus: 'near-toast', placement: 'top', count: 1 });
    await page.evaluate(`document.querySelector('#origin').focus()`);
    assert.notEqual(await page.evaluate(`document.querySelector('[data-toast-region]').dataset.placement`), 'top');
});

test('invalid durations use the safe default instead of expiring immediately', async () => {
    for (const duration of ['NaN', 'Infinity', '-1', '"soon"', '2147483648']) {
        await page.load(clock + shell + component(), ['notifications.js']);
        await leaveToast();
        await page.evaluate(`import('/notifications.js').then(({ notify }) => notify('Pesan', { duration: ${duration} }))`);
        await tick(6999);
        assert.equal(await count(), 1, duration);
        await tick(1);
        assert.equal(await count(), 0, duration);
    }
});
