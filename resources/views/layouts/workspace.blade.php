<!DOCTYPE html>
<html lang="id">
<head><x-document-head /></head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten</a>
<div class="workspace">
    <aside class="sidebar">
        <x-brand />
        <x-navigation :panel="$panel" />
        <div class="sidebar-bottom">
            <a href="{{ $panel === 'admin' ? route('settings.index') : route('petani.settings') }}" class="sidebar-account" data-label="Pengaturan akun">
                <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="account-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Petani' }}</small></span>
                <x-icon name="settings" />
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout-link" type="submit" data-label="Keluar"><x-icon name="logout" /><span>Keluar</span></button></form>
        </div>
    </aside>
    <div class="workspace-main">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="icon-button sidebar-toggle" data-sidebar-toggle hidden aria-expanded="true" aria-label="Ciutkan navigasi"><x-icon name="chevron-down" /></button>
                <details class="mobile-menu" data-mobile-nav>
                    <summary class="icon-button mobile-menu-toggle" aria-label="Buka navigasi"><x-icon name="menu" /><span class="sr-only">Buka navigasi</span></summary>
                    <div class="mobile-menu-panel" role="dialog" aria-label="Navigasi">
                        <div class="mobile-menu-head">
                            <x-brand :compact="true" />
                            <button class="icon-button mobile-menu-close" type="button" data-mobile-close aria-label="Tutup navigasi"><x-icon name="close" /></button>
                        </div>
                        <x-navigation :panel="$panel" />
                        <a href="{{ $panel === 'admin' ? route('settings.index') : route('petani.settings') }}" class="mobile-account">
                            <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="account-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Petani' }}</small></span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout-link" type="submit"><x-icon name="logout" /><span>Keluar</span></button></form>
                    </div>
                </details>
                <div class="topbar-location"><span class="topbar-divider" aria-hidden="true"></span><span class="topbar-workspace">Growvia</span><span class="topbar-current" aria-hidden="true">/</span><span class="topbar-page">@yield('page', 'Ringkasan')</span></div>
            </div>
            <div class="topbar-actions">
                <span class="topbar-label">{{ $panel === 'admin' ? 'RUANG KERJA ADMIN' : 'PANEL PETANI' }}</span>
                <x-language-select />
                <x-theme-select />
                <a class="avatar avatar--small" href="{{ $panel === 'admin' ? route('settings.index') : route('petani.settings') }}" aria-label="Pengaturan akun {{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</a>
            </div>
        </header>
        <main id="main-content" class="page-content" tabindex="-1">@yield('content')</main>
        <footer class="workspace-footer"><span>Growvia / Ruang kerja rumah kaca</span><span>Biar kebun makin kerawat.</span></footer>
    </div>
</div>
<x-notifications />
</body>
</html>
