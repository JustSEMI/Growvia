<!DOCTYPE html>
<html lang="id">
<head><x-document-head /></head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten</a>
<div class="auth-shell">
    <aside class="auth-story" aria-label="Tentang Growvia">
        <x-brand />
        <div class="auth-story-content"><p class="eyebrow">PERHATIAN KECIL. TUMBUH LEBIH BAIK.</p><p class="auth-story-title">Ruang tumbuh.<br>Ruang untuk<br><em>lebih peduli.</em></p><p>Kenalan sama kondisi rumah kaca dan kelola kebun dari satu ruang kerja yang simpel.</p><x-garden-art /></div>
        <div class="auth-story-footer"><span>Ruang kerja rumah kaca</span><span>Dirawat bareng.</span></div>
    </aside>
    <main class="auth-main" id="main-content" tabindex="-1">
        <div class="auth-top"><x-brand class="auth-mobile-brand" :compact="true" /><span class="auth-access"><x-icon name="shield" />Akses ruang kerja kejaga</span><x-language-select /><x-theme-select /></div>
        <div class="auth-form-wrap">@yield('content')</div>
        <footer class="auth-footer">Growvia / Perhatian kecil biar tumbuhnya makin oke.</footer>
    </main>
</div>
<x-notifications />
</body>
</html>
