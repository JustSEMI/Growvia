<script data-theme-bootstrap @if(! empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
    (() => {
        let preference = 'system';
        try {
            const saved = localStorage.getItem('growvia.theme');
            if (['light', 'dark', 'system'].includes(saved)) preference = saved;
        } catch {}
        const theme = preference === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : preference;
        const root = document.documentElement;
        root.dataset.themePreference = preference;
        root.dataset.theme = theme;
        root.style.colorScheme = theme;
        document.querySelector('meta[name="theme-color"]').setAttribute('content', theme === 'dark' ? '#171b18' : '#f7f6f3');
    })();
</script>
