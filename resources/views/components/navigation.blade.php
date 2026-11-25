@props(['panel' => 'admin'])

<nav class="nav-stack" aria-label="Navigasi utama">
    <p class="nav-label">Ruang kerja</p>
    @if($panel === 'admin')
        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="grid">Ringkasan</x-nav-link>
        <x-nav-link :href="route('dashboard.riwayat')" :active="request()->routeIs('dashboard.riwayat')" icon="chart">Riwayat</x-nav-link>
        <x-nav-link :href="route('dashboard.mqtt')" :active="request()->routeIs('dashboard.mqtt')" icon="connection">MQTT</x-nav-link>
        <x-nav-link :href="route('dashboard.api-docs')" :active="request()->routeIs('dashboard.api-docs')" icon="code">API Mobile</x-nav-link>
        <p class="nav-label nav-label--spaced">Kelola</p>
        <x-nav-link :href="route('activity.index')" :active="request()->routeIs('activity.*')" icon="clock">Aktivitas</x-nav-link>
        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="users">Pengguna</x-nav-link>
        <x-nav-link :href="route('dashboard.backup.index')" :active="request()->routeIs('dashboard.backup.*')" icon="archive">Backup</x-nav-link>
        <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*', 'sessions.*')" icon="settings">Pengaturan</x-nav-link>
        <div class="nav-switch-slot">
            <x-nav-link :href="route('petani.dashboard')" icon="leaf" class="nav-link--switch">Panel petani <x-icon name="arrow-up" class="nav-end-icon" /></x-nav-link>
        </div>
    @else
        <x-nav-link :href="route('petani.dashboard')" :active="request()->routeIs('petani.dashboard')" icon="grid">Kebun saya</x-nav-link>
        <x-nav-link :href="route('petani.node1')" :active="request()->routeIs('petani.node1')" icon="sun">Device 1</x-nav-link>
        <x-nav-link :href="route('petani.node2')" :active="request()->routeIs('petani.node2')" icon="drop">Device 2</x-nav-link>
        <p class="nav-label nav-label--spaced">Panduan &amp; akun</p>
        <x-nav-link :href="route('petani.edukasi')" :active="request()->routeIs('petani.edukasi')" icon="book">Edukasi</x-nav-link>
        <x-nav-link :href="route('petani.settings')" :active="request()->routeIs('petani.settings', 'sessions.*')" icon="settings">Pengaturan</x-nav-link>
        @if(auth()->user()->isAdmin())
            <div class="nav-switch-slot">
                <x-nav-link :href="route('dashboard')" icon="shield" class="nav-link--switch">Panel admin <x-icon name="arrow-up" class="nav-end-icon" /></x-nav-link>
            </div>
        @endif
    @endif
</nav>
