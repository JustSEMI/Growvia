import './theme.js';
import './language.js';
import { initCustomSelects } from './custom-select.js';
import { initValidation } from './validation.js';
import './notifications.js';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

// === Penghubungan halaman ===
// Turbo Drive menukar <body> saat berpindah halaman tanpa reload penuh, jadi
// semua ikatan berbasis elemen harus dijalankan ulang di setiap kunjungan.
// Pendengar di level document/window dipasang sekali di module scope (di bawah),
// sedangkan bindPage() berjalan pada turbo:load, termasuk saat memuat pertama.

// === Dialog konfirmasi ===
const approved = new WeakSet();
const submissions = new WeakSet();
const submissionTimers = new WeakMap();
let confirmation = null;
let confirmationReady = false;
let pending = null;

const resetSubmission = (form) => {
    clearTimeout(submissionTimers.get(form));
    submissionTimers.delete(form);
    submissions.delete(form);
    approved.delete(form);
    form.removeAttribute('aria-busy');
    form.querySelectorAll('.is-submitting').forEach((button) => button.classList.remove('is-submitting'));
};

const resetSubmissions = () => document.querySelectorAll('form[aria-busy="true"]').forEach(resetSubmission);

// Pengiriman ulang terkunci + tombol beranimasi selama permintaan berjalan.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.hasAttribute('data-confirm-name') && !approved.has(form)) {
        const kind = form.dataset.confirmKind;
        const title = kind === 'deactivate' ? 'Nonaktifkan akun' : kind === 'activate' ? 'Aktifkan akun' : 'Hapus akun';
        const detail = kind === 'deactivate' ? 'Sesi aktifnya akan diakhiri. Akun dapat diaktifkan kembali.' : kind === 'activate' ? 'Pengguna dapat masuk kembali dengan kata sandinya.' : 'Tindakan ini tidak bisa dibatalkan.';
        const message = `${title} ${form.dataset.confirmName}? ${detail}`;
        event.preventDefault();
        if (confirmationReady) {
            pending = { form, submitter: event.submitter };
            confirmation.querySelector('[data-confirm-text]').textContent = message;
            const heading = confirmation.querySelector('[data-confirm-title]');
            if (heading) heading.textContent = `Konfirmasi ${title.toLocaleLowerCase()}`;
            confirmation.querySelector('[data-confirm-accept]').textContent = title;
            confirmation.showModal();
        }
        return;
    }
    if (submissions.has(form)) {
        event.preventDefault();
        return;
    }
    submissions.add(form);
    form.setAttribute('aria-busy', 'true');
    event.submitter?.classList.add('is-submitting');
    submissionTimers.set(form, setTimeout(() => resetSubmission(form), 8000));
});
window.addEventListener('pageshow', resetSubmissions);
window.addEventListener('focus', resetSubmissions);
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') resetSubmissions();
});

// === Menu navigasi seluler (delegasi, aman terhadap pergantian DOM) ===
document.addEventListener('click', (event) => {
    for (const menu of document.querySelectorAll('details[data-mobile-nav][open]')) {
        if (!menu.contains(event.target)) menu.open = false;
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const menu = document.querySelector('details[data-mobile-nav][open]');
    if (!menu) return;
    menu.open = false;
    menu.querySelector('summary')?.focus();
});

// === Pintasan keyboard R untuk tombol refresh utama ===
window.addEventListener('keydown', (event) => {
    if (event.ctrlKey || event.altKey || event.metaKey) return;
    const tag = document.activeElement?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
    if (event.key.toLowerCase() === 'r') document.querySelector('[data-refresh]')?.click();
});

// === Reveal-on-scroll: satu observer, mengamati elemen halaman baru tiap kunjungan ===
const revealed = new WeakSet();
const revealObserver = !reduceMotion.matches && 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            entry.target.classList.add('reveal-in');
            revealObserver.unobserve(entry.target);
        }
    }, { threshold: 0.04 })
    : null;

// === Scrollspy section nav: state per halaman, dibaca ulang oleh hashchange di bawah ===
let navSectionState = null;
document.addEventListener('hashchange', () => navSectionState?.onHash());

// Identitas <body> dipakai sebagai penanda halaman: Turbo Drive mengganti
// seluruh elemen body pada tiap kunjungan, jadi pemanggilan berulang pada
// halaman yang sama cukup diabaikan (termasuk turbo:load pertama).
let boundBody = null;

const bindPage = () => {
    if (document.body === boundBody) return;
    boundBody = document.body;
    // Tombol "Segarkan" di header halaman: animasi ikon putar + teks "Menyegarkan...",
    // lalu reload ulang halaman untuk menampilkan data terbaru.
    for (const button of document.querySelectorAll('[data-refresh]')) {
        button.addEventListener('click', () => {
            if (button.classList.contains('is-refreshing')) return;
            const text = button.querySelector('[data-refresh-text]');
            const original = text ? text.textContent : '';

            button.classList.add('is-refreshing');
            button.setAttribute('disabled', '');
            if (text) text.textContent = 'Menyegarkan...';

            // Memberi kesempatan animasi ikon (0,85 detik) terlihat sebelum benar-benar reload.
            setTimeout(() => window.location.reload(), reduceMotion.matches ? 0 : 850);
        });
    }

    for (const button of document.querySelectorAll('[data-sidebar-toggle]')) {
        button.hidden = false;
        const apply = (collapsed) => {
            document.documentElement.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
            button.setAttribute('aria-expanded', String(!collapsed));
            button.setAttribute('aria-label', collapsed ? 'Bentangkan navigasi' : 'Ciutkan navigasi');
            button.setAttribute('title', collapsed ? 'Bentangkan navigasi' : 'Ciutkan navigasi');
        };
        // Pre-paint bootstrap (x-sidebar-bootstrap) sudah membaca localStorage dan
        // menyetel dataset.sidebar sebelum animasi first paint; di sini sinkronkan
        // hanya state toggle-nya agar terhindar dari animasi lebar sidebar. Bila
        // skrip itu tidak berjalan, jatuhkan diri ke localStorage agar preferensi
        // pengguna tetap pulih.
        let stored = null;
        try {
            stored = localStorage.getItem('growvia.sidebar');
        } catch {}
        const preset = document.documentElement.dataset.sidebar;
        let collapsed = preset ? preset === 'collapsed' : stored === 'collapsed';
        apply(collapsed);
        button.addEventListener('click', () => {
            collapsed = document.documentElement.dataset.sidebar !== 'collapsed';
            try {
                localStorage.setItem('growvia.sidebar', collapsed ? 'collapsed' : 'expanded');
            } catch {}
            apply(collapsed);
        });
    }

    for (const button of document.querySelectorAll('[data-password-toggle]')) {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) continue;
        button.hidden = false;
        button.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            input.focus({ preventScroll: true });
        });
    }

    for (const menu of document.querySelectorAll('[data-mobile-nav]')) {
        const trigger = menu.querySelector('summary');
        const closeButton = menu.querySelector('[data-mobile-close]');
        menu.addEventListener('toggle', () => {
            trigger.setAttribute('aria-expanded', String(menu.open));
            trigger.setAttribute('aria-label', menu.open ? 'Tutup navigasi' : 'Buka navigasi');
            if (menu.open) closeButton?.focus();
        });
        trigger.setAttribute('aria-expanded', String(menu.open));
        closeButton?.addEventListener('click', () => {
            menu.open = false;
            trigger.focus();
        });
        menu.addEventListener('click', (event) => {
            if (event.target.closest('a')) menu.open = false;
        });
    }

    // Dialog konfirmasi hanya ada di halaman tertentu; resolve ulang tiap kunjungan.
    confirmation = document.querySelector('[data-confirm-dialog]');
    confirmationReady = Boolean(confirmation)
        && typeof confirmation.showModal === 'function'
        && Boolean(confirmation.querySelector('[data-confirm-text]'))
        && Boolean(confirmation.querySelector('[data-confirm-cancel]'))
        && Boolean(confirmation.querySelector('[data-confirm-accept]'));

    confirmation?.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => confirmation.close());
    confirmation?.addEventListener('close', () => {
        if (confirmation.open) return;
        pending?.submitter?.focus();
        pending = null;
    });
    confirmation?.querySelector('[data-confirm-accept]')?.addEventListener('click', () => {
        if (!pending) return;
        const { form, submitter } = pending;
        pending = null;
        approved.add(form);
        confirmation.close();
        form.requestSubmit(submitter ?? undefined);
    });

    for (const button of document.querySelectorAll('[data-confirm-trigger]')) {
        button.disabled = !confirmationReady;
    }
    document.querySelectorAll('[data-confirm-required]').forEach((notice) => { notice.hidden = Boolean(confirmationReady); });

    const errorSummary = document.querySelector('[data-error-summary]');
    errorSummary?.addEventListener('click', (event) => {
        const link = event.target.closest('a[href^="#"]');
        if (!link) return;
        const field = decodeURIComponent(link.getAttribute('href').slice(1));
        if (document.getElementById(field)) return;
        const control = document.querySelector(`[name="${CSS.escape(field.split('.')[0])}"]`);
        if (!control) return;
        event.preventDefault();
        const target = control.hidden ? control.parentElement?.querySelector('[data-select-trigger]') : control;
        target?.focus();
    });
    errorSummary?.focus();

    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        if (revealed.has(element) || !revealObserver) return;
        revealed.add(element);
        element.style.animationDelay = `${Math.min(index % 4, 3) * 45}ms`;
        revealObserver.observe(element);
    });

    // Scrollspy nav section: target dan link milik halaman aktif saja.
    const navSectionLinks = [...document.querySelectorAll('a[data-nav-section]')];
    const navSectionTargets = new Map();
    for (const link of navSectionLinks) {
        const section = link.dataset.navSection;
        if (section === 'top' || navSectionTargets.has(section)) continue;
        const target = document.getElementById(section);
        if (target) navSectionTargets.set(section, target);
    }

    navSectionState = null;
    if (navSectionLinks.length > 0 && navSectionTargets.size > 0 && 'IntersectionObserver' in window) {
        let activeNavSection = 'top';
        const visibleNavSections = new Set();
        const activateNavSection = (section) => {
            if (section === activeNavSection) return;
            activeNavSection = section;
            for (const link of navSectionLinks) {
                const on = link.dataset.navSection === section;
                link.classList.toggle('is-active', on);
                if (on) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            }
        };
        const firstNavOffset = () => {
            const first = navSectionTargets.values().next().value;
            return first.getBoundingClientRect().top + window.scrollY;
        };
        const sectionObserver = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) visibleNavSections.add(entry.target.id);
                else visibleNavSections.delete(entry.target.id);
            }
            // Pilih section paling bawah yang bendera -35% sudah lewat, bukan
            // yang rentangnya paling atas, agar tidak mengaitkan offset halaman.
            const choice = [...visibleNavSections].sort((a, b) => {
                const targetA = navSectionTargets.get(a);
                const targetB = navSectionTargets.get(b);
                return (targetB?.offsetTop ?? 0) - (targetA?.offsetTop ?? 0);
            });
            const target = choice.find((id) => {
                const el = navSectionTargets.get(id);
                return el !== undefined && el.getBoundingClientRect().top <= window.innerHeight * 0.35 + 1;
            });
            if (target) {
                activateNavSection(target);
            } else if (window.scrollY + 140 < firstNavOffset()) activateNavSection('top');
        }, { rootMargin: '-35% 0px -60% 0px' });
        navSectionTargets.forEach((target) => sectionObserver.observe(target));
        navSectionState = {
            onHash: () => {
                const id = window.location.hash.slice(1);
                if (navSectionTargets.has(id)) activateNavSection(id);
            },
        };
    }

    // Putar ulang animasi masuk halaman setelah Turbo menukar body.
    const page = document.querySelector('.page-content');
    if (page && !reduceMotion.matches) {
        page.style.animation = 'none';
        void page.offsetWidth;
        page.style.animation = '';
    }
};

const start = () => {
    bindPage();
    initCustomSelects();
    initValidation();
};

// Turbo Drive menukar isi halaman tanpa reload penuh, jadi ikatan dijalankan
// ulang di tiap turbo:load. Bootstrap di bawah menangani pemuatan pertama,
// termasuk saat app.js dimuat sendiri tanpa Turbo (harness tes UI).
document.addEventListener('turbo:load', start);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
    start();
}
