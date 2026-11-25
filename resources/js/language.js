const languageKey = 'growvia.language';
const boundLanguageControls = new WeakSet();

const applyLanguage = (preference) => {
    const language = preference === 'en' ? 'en' : 'id';
    document.documentElement.lang = language;
    document.querySelectorAll('[data-language-select]').forEach((control) => {
        control.value = language === 'en' && [...control.options].some((option) => option.value === 'en' && !option.disabled) ? 'en' : 'id';
        control.dispatchEvent(new Event('growvia:select-sync'));
    });
};

const initLanguageControls = () => {
    for (const control of document.querySelectorAll('[data-language-select]')) {
        if (!boundLanguageControls.has(control)) {
            boundLanguageControls.add(control);
            control.addEventListener('change', () => {
                if (control.value !== 'id') {
                    control.value = 'id';
                    control.dispatchEvent(new Event('growvia:select-sync'));
                    return;
                }
                applyLanguage('id');
                try { localStorage.setItem(languageKey, 'id'); } catch {}
            });
        }
        const wrapper = control.closest('[data-language-control]');
        if (wrapper) wrapper.hidden = false;
    }
    applyLanguage('id');
};

window.addEventListener('storage', (event) => {
    if (event.key === languageKey || event.key === null) applyLanguage(event.newValue);
});

window.addEventListener('pageshow', () => {
    let preference = 'id';
    try { preference = localStorage.getItem(languageKey) ?? 'id'; } catch {}
    applyLanguage(preference);
});

document.addEventListener?.('turbo:load', initLanguageControls);
initLanguageControls();
