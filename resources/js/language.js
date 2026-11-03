const languageKey = 'growvia.language';
const languageControls = document.querySelectorAll('[data-language-select]');

const applyLanguage = (preference) => {
    const language = preference === 'en' ? 'en' : 'id';
    document.documentElement.lang = language;
    languageControls.forEach((control) => {
        control.value = language === 'en' && [...control.options].some((option) => option.value === 'en' && !option.disabled) ? 'en' : 'id';
        control.dispatchEvent(new Event('growvia:select-sync'));
    });
};

for (const control of languageControls) {
    control.addEventListener('change', () => {
        if (control.value !== 'id') {
            control.value = 'id';
            control.dispatchEvent(new Event('growvia:select-sync'));
            return;
        }
        applyLanguage('id');
        try { localStorage.setItem(languageKey, 'id'); } catch {}
    });
    control.closest('[data-language-control]').hidden = false;
}

window.addEventListener('storage', (event) => {
    if (event.key === languageKey || event.key === null) applyLanguage(event.newValue);
});

window.addEventListener('pageshow', () => {
    let preference = 'id';
    try { preference = localStorage.getItem(languageKey) ?? 'id'; } catch {}
    applyLanguage(preference);
});

applyLanguage('id');
