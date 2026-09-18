const region = document.querySelector('[data-toast-region]');
const template = document.querySelector('[data-toast-template]');
const live = document.querySelector('[data-toast-live]');
const titles = { success: 'Berhasil', error: 'Terjadi kesalahan', info: 'Informasi', warning: 'Perhatian' };
const active = new Set();
const waiting = [];
const pendingLabel = region?.querySelector('[data-toast-pending]');
const announcements = new Map();
let announcementTimer = null;

const announce = (toast, text) => {
    announcements.set(toast, text);
    live.textContent = '';
    if (announcementTimer !== null) return;
    announcementTimer = setTimeout(() => {
        live.textContent = [...announcements.values()].join(' ');
        announcements.clear();
        announcementTimer = null;
    }, 100);
};

const placeRegion = () => {
    if (!region?.isConnected || region.contains(document.activeElement)) return;
    region.removeAttribute('data-placement');
    const focused = document.activeElement;
    if (!active.size || !(focused instanceof HTMLElement) || !focused.matches('input, select, textarea, button, a[href], [contenteditable="true"], [role="combobox"]')) return;
    const target = focused.getBoundingClientRect();
    const bounds = region.getBoundingClientRect();
    if (target.left < bounds.right && target.right > bounds.left && target.top < bounds.bottom && target.bottom > bounds.top) region.dataset.placement = 'top';
};

document.addEventListener('focusin', placeRegion);
window.addEventListener('resize', placeRegion);
let placementFrame = null;
document.addEventListener('scroll', (event) => {
    if (region?.contains(event.target) || placementFrame !== null) return;
    placementFrame = requestAnimationFrame(() => { placementFrame = null; placeRegion(); });
}, true);

const flush = () => {
    while (active.size < 3 && waiting.length) waiting.shift()();
    placeRegion();
    if (pendingLabel) {
        pendingLabel.hidden = waiting.length === 0;
        pendingLabel.textContent = waiting.length ? `${waiting.length} notifikasi menunggu. Tutup notifikasi untuk melihat berikutnya.` : '';
    }
};

const isVisible = (element) => {
    if (!(element instanceof HTMLElement) || !element.isConnected || element.matches(':disabled') || element.closest('[hidden], [inert], [aria-hidden="true"]')) return false;
    if (!element.getClientRects().length) return false;
    for (let node = element; node instanceof HTMLElement; node = node.parentElement) {
        const style = getComputedStyle(node);
        if (style.visibility !== 'visible' || style.opacity === '0') return false;
    }
    return true;
};

const returnFocus = (origin) => {
    if (origin !== document.body && origin !== document.documentElement && isVisible(origin)) {
        origin.focus({ preventScroll: true });
        if (document.activeElement === origin) return;
    }
    const main = [...document.querySelectorAll('main, [role="main"]')].find(isVisible);
    if (!main) return;
    if (!main.hasAttribute('tabindex')) {
        main.tabIndex = -1;
        main.addEventListener('blur', () => main.removeAttribute('tabindex'), { once: true });
    }
    main.focus({ preventScroll: true });
};

export function notify(message, { tone = 'info', title, duration = 7000 } = {}) {
    if (typeof message !== 'string' || !message.trim()) return null;
    const prototype = template instanceof HTMLTemplateElement ? template.content.querySelector('.toast') : null;
    if (!region?.isConnected || !live?.isConnected || !prototype) return null;
    if (!['[data-toast-title]', '[data-toast-message]', 'button[type="button"][data-toast-close]'].every(selector => prototype.querySelector(selector))) return null;

    tone = Object.hasOwn(titles, tone) ? tone : 'info';
    duration = Number.isFinite(duration) && duration >= 0 && duration <= 2147483647 ? duration : 7000;
    title = title ?? titles[tone];
    const toast = prototype.cloneNode(true);
    toast.dataset.tone = tone;
    toast.querySelector('.toast-icon')?.children[tone === 'success' ? 1 : 0]?.remove();
    toast.querySelector('[data-toast-title]').textContent = title;
    toast.querySelector('[data-toast-message]').textContent = message;
    const origin = document.activeElement;
    let timer = null;
    let remaining = duration;
    let started;
    const persistent = tone === 'error' || tone === 'warning' || duration <= 0;
    const pause = () => {
        if (timer === null) return;
        clearTimeout(timer);
        timer = null;
        remaining = Math.max(0, remaining - (performance.now() - started));
    };
    const dismiss = () => {
        const restoreFocus = toast.contains(document.activeElement);
        pause();
        document.removeEventListener('visibilitychange', onVisibilityChange);
        active.delete(toast);
        announcements.delete(toast);
        if (!announcements.size) {
            clearTimeout(announcementTimer);
            announcementTimer = null;
        }
        if (!active.size) live.textContent = '';
        toast.remove();
        if (restoreFocus) returnFocus(origin);
        flush();
    };
    const resume = () => {
        if (persistent || timer !== null || !toast.isConnected || document.hidden || toast.matches(':hover') || toast.contains(document.activeElement)) return;
        started = performance.now();
        timer = setTimeout(dismiss, remaining);
    };
    const onVisibilityChange = () => document.hidden ? pause() : resume();
    toast.addEventListener('pointerenter', pause);
    toast.addEventListener('pointerleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', () => queueMicrotask(resume));
    toast.querySelector('[data-toast-close]').addEventListener('click', dismiss);
    waiting.push(() => {
        active.add(toast);
        region.insertBefore(toast, pendingLabel ?? null);
        document.addEventListener('visibilitychange', onVisibilityChange);
        resume();
        announce(toast, `${title}. ${message}`);
    });
    flush();

    return toast;
}

for (const flash of document.querySelectorAll('[data-flash-message]')) {
    const text = flash.querySelector('[data-flash-text]')?.textContent;
    if (!text || !notify(text, { tone: flash.dataset.flashTone })) continue;
    flash.classList.add('flash-receipt');
    flash.removeAttribute('role');
    flash.setAttribute('aria-live', 'off');
}
