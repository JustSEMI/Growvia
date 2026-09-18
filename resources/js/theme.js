const themeKey = 'growvia.theme';
const themeRoot = document.documentElement;
const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
const themeControls = document.querySelectorAll('[data-theme-select]');
let themePreference = themeRoot.dataset.themePreference ?? 'system';

const applyTheme = (preference) => {
    themePreference = ['light', 'dark', 'system'].includes(preference) ? preference : 'system';
    const theme = themePreference === 'system' ? (themeMedia.matches ? 'dark' : 'light') : themePreference;
    themeRoot.dataset.themePreference = themePreference;
    themeRoot.dataset.theme = theme;
    themeRoot.style.colorScheme = theme;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#171b18' : '#f7f6f3');
    themeControls.forEach((control) => {
        control.value = themePreference;
        control.dispatchEvent(new Event('growvia:select-sync'));
    });
};

for (const control of themeControls) {
    control.addEventListener('change', () => {
        applyTheme(control.value);
        try { localStorage.setItem(themeKey, themePreference); } catch {}
    });
    control.closest('[data-theme-control]').hidden = false;
}

themeMedia.addEventListener('change', () => {
    if (themePreference === 'system') applyTheme('system');
});

window.addEventListener('storage', (event) => {
    if (event.key === themeKey || event.key === null) applyTheme(event.newValue);
});

window.addEventListener('pageshow', () => {
    let preference = themePreference;
    try { preference = localStorage.getItem(themeKey); } catch {}
    applyTheme(preference);
});

applyTheme(themePreference);
