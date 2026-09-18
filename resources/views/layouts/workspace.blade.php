<!DOCTYPE html>
<html lang="id">
<head><x-document-head /></head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten</a>
<div class="workspace">
    <aside class="sidebar">
        <x-brand />
        <div class="workspace-label"><span class="workspace-symbol"><x-icon name="leaf" /></span><div><strong>Rumah kaca</strong><span>Ruang kerja Growvia</span></div></div>
        <x-navigation :panel="$panel" />
        <div class="sidebar-bottom">
            <div class="sidebar-note"><x-icon name="connection" /><div><strong>Ruang untuk bertumbuh.</strong><p>Data kebun, di satu tempat.</p></div></div>
            <a href="{{ $panel === 'admin' ? route('settings.index') : route('petani.settings') }}" class="sidebar-account">
                <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="account-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Petani' }}</small></span>
                <x-icon name="settings" />
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout-link" type="submit"><x-icon name="logout" /><span>Keluar</span></button></form>
        </div>
    </aside>
    <div class="workspace-main">
        <header class="topbar">
            <div class="topbar-location"><span class="topbar-workspace">Growvia</span><span class="breadcrumb-divider" aria-hidden="true">/</span><span>@yield('page', 'Ringkasan')</span></div>
            <div class="topbar-actions">
                <span class="topbar-label">{{ $panel === 'admin' ? 'RUANG KERJA ADMIN' : 'PANEL PETANI' }}</span>
                <x-theme-select />
                <a class="avatar avatar--small" href="{{ $panel === 'admin' ? route('settings.index') : route('petani.settings') }}" aria-label="Pengaturan akun {{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</a>
                <details class="mobile-menu" data-mobile-nav>
                    <summary class="icon-button" aria-label="Buka navigasi"><x-icon name="menu" /><span class="sr-only">Buka navigasi</span></summary>
                    <div class="mobile-menu-panel">
                        <x-brand :compact="true" />
                        <x-navigation :panel="$panel" />
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout-link" type="submit"><x-icon name="logout" /><span>Keluar</span></button></form>
                    </div>
                </details>
            </div>
        </header>
        <main id="main-content" class="page-content" tabindex="-1">@yield('content')</main>
        <footer class="workspace-footer"><span>Growvia / Ruang kerja rumah kaca</span><span>Dirancang untuk kebun yang lebih terawat.</span></footer>
    </div>
</div>
<x-notifications />
</body>
</html>
