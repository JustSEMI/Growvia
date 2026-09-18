let validationId = 0;

for (const form of document.querySelectorAll('form')) {
    let summary = null;
    const errors = new Map();
    const controls = () => [...form.elements].filter(control => control.willValidate && !['submit', 'button', 'reset'].includes(control.type));
    const labelFor = control => (control.labels?.[0]?.textContent || control.closest('.field')?.querySelector('label')?.textContent || control.getAttribute('aria-label') || 'Isian ini').replace(/\*/g, '').trim();
    const messageFor = control => {
        const validity = control.validity;
        const name = labelFor(control);
        if (validity.valueMissing) return `${name} wajib diisi.`;
        if (validity.typeMismatch) return control.type === 'email' ? 'Masukkan alamat email yang valid.' : `${name} harus diisi dengan format yang valid.`;
        if (validity.badInput) return `${name} harus berupa angka.`;
        if (validity.rangeUnderflow) return `${name} minimal ${control.min}.`;
        if (validity.rangeOverflow) return `${name} maksimal ${control.max}.`;
        if (validity.stepMismatch) return `${name} harus sesuai kelipatan ${control.step || '1'}.`;
        if (validity.tooShort) return `${name} minimal ${control.minLength} karakter.`;
        if (validity.tooLong) return `${name} maksimal ${control.maxLength} karakter.`;
        if (validity.patternMismatch) return `${name} belum sesuai format yang diminta.`;
        if (!validity.valid) return `Periksa kembali ${name.toLocaleLowerCase()}.`;
        return '';
    };
    const focusControl = control => {
        const target = control.hidden ? control.parentElement.querySelector('[data-select-trigger]') : control;
        target?.focus();
    };
    const clearError = control => {
        const error = errors.get(control);
        if (!error) return;
        error.element.remove();
        for (const [name, value] of Object.entries(error.attributes)) {
            value === null ? control.removeAttribute(name) : control.setAttribute(name, value);
        }
        errors.delete(control);
        control.dispatchEvent(new Event('growvia:select-sync'));
    };
    const showError = (control, message) => {
        if (errors.has(control)) {
            errors.get(control).element.textContent = message;
            return;
        }
        control.id ||= `growvia-field-${++validationId}`;
        const element = document.createElement('p');
        element.id = `${control.id}-client-error`;
        element.className = 'field-error';
        element.dataset.clientError = '';
        element.textContent = message;
        const attributes = { 'aria-invalid': control.getAttribute('aria-invalid'), 'aria-describedby': control.getAttribute('aria-describedby') };
        errors.set(control, { element, attributes });
        control.setAttribute('aria-invalid', 'true');
        control.setAttribute('aria-describedby', [attributes['aria-describedby'], element.id].filter(Boolean).join(' '));
        (control.closest('.field') || control.parentElement).append(element);
        control.dispatchEvent(new Event('growvia:select-sync'));
    };
    const updateSummary = () => {
        if (!errors.size) { summary?.remove(); summary = null; return; }
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'alert alert--error client-summary';
            summary.dataset.clientSummary = '';
            summary.setAttribute('role', 'alert');
            summary.setAttribute('tabindex', '-1');
            summary.setAttribute('aria-label', 'Periksa kembali isian berikut.');
            form.prepend(summary);
        }
        const heading = document.createElement('strong');
        heading.textContent = 'Periksa kembali isian berikut.';
        const list = document.createElement('ul');
        for (const [control, { element }] of errors) {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = `#${control.id}`;
            link.textContent = element.textContent;
            link.addEventListener('click', event => { event.preventDefault(); focusControl(control); });
            item.append(link);
            list.append(item);
        }
        summary.replaceChildren(heading, list);
    };
    form.addEventListener('submit', event => {
        if (event.submitter?.formNoValidate) return;
        for (const control of controls()) {
            const message = messageFor(control);
            message ? showError(control, message) : clearError(control);
        }
        updateSummary();
        if (errors.size) {
            event.preventDefault();
            if (errors.size > 1) summary.focus();
            else focusControl(errors.keys().next().value);
        }
    });
    form.addEventListener('input', event => {
        if (!errors.has(event.target)) return;
        const message = messageFor(event.target);
        message ? showError(event.target, message) : clearError(event.target);
        updateSummary();
    });
    form.addEventListener('reset', () => {
        for (const control of errors.keys()) clearError(control);
        updateSummary();
    });
    form.noValidate = true;
}
