import './theme.js';
import './custom-select.js';
import './validation.js';
import './notifications.js';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

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
    menu.addEventListener('toggle', () => trigger.setAttribute('aria-expanded', String(menu.open)));
    trigger.setAttribute('aria-expanded', String(menu.open));
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            trigger.focus();
        }
    });
    menu.addEventListener('click', (event) => {
        if (event.target.closest('a')) menu.open = false;
    });
}

const confirmation = document.querySelector('[data-confirm-dialog]');
const confirmationReady = confirmation && typeof confirmation.showModal === 'function' && confirmation.querySelector('[data-confirm-text]') && confirmation.querySelector('[data-confirm-cancel]') && confirmation.querySelector('[data-confirm-accept]');
const approved = new WeakSet();
let pending = null;

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

const submissions = new WeakSet();
const submissionTimers = new WeakMap();
const resetSubmission = (form) => {
    clearTimeout(submissionTimers.get(form));
    submissionTimers.delete(form);
    submissions.delete(form);
    approved.delete(form);
    form.removeAttribute('aria-busy');
    form.querySelectorAll('.is-submitting').forEach((button) => button.classList.remove('is-submitting'));
};
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

const resetSubmissions = () => document.querySelectorAll('form[aria-busy="true"]').forEach(resetSubmission);
window.addEventListener('pageshow', resetSubmissions);
window.addEventListener('focus', resetSubmissions);
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') resetSubmissions();
});

for (const button of document.querySelectorAll('[data-confirm-trigger]')) {
    button.disabled = !confirmationReady;
}
document.querySelectorAll('[data-confirm-required]').forEach((notice) => { notice.hidden = Boolean(confirmationReady); });

document.querySelector('[data-error-summary]')?.focus();

if (!reduceMotion.matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            entry.target.classList.add('reveal-in');
            observer.unobserve(entry.target);
        }
    }, { threshold: 0.04 });
    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        element.style.animationDelay = `${Math.min(index % 4, 3) * 45}ms`;
        observer.observe(element);
    });
}
