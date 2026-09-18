@props(['panel' => 'admin'])

<nav class="nav-stack" aria-label="Navigasi utama">
    <p class="nav-label">Ruang kerja</p>
    @if($panel === 'admin')
        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="grid">Ringkasan</x-nav-link>
        <x-nav-link :href="route('dashboard').'#node1'" icon="sun">Iklim rumah kaca</x-nav-link>
        <x-nav-link :href="route('dashboard').'#node2'" icon="drop">Tanah &amp; air</x-nav-link>
        <p class="nav-label nav-label--spaced">Kelola</p>
        <x-nav-link :href="route('activity.index')" :active="request()->routeIs('activity.*')" icon="clock">Aktivitas</x-nav-link>
        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="users">Pengguna</x-nav-link>
        <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*')" icon="settings">Pengaturan</x-nav-link>
        <x-nav-link :href="route('sessions.index')" :active="request()->routeIs('sessions.*')" icon="shield">Sesi perangkat</x-nav-link>
        <p class="nav-label nav-label--spaced">Tampilan lain</p>
        <x-nav-link :href="route('petani.dashboard')" icon="leaf">Panel petani <x-icon name="arrow-up" class="nav-end-icon" /></x-nav-link>
    @else
        <x-nav-link :href="route('petani.dashboard')" :active="request()->routeIs('petani.dashboard')" icon="grid">Kebun saya</x-nav-link>
        <x-nav-link :href="route('petani.node1')" :active="request()->routeIs('petani.node1')" icon="sun">Iklim rumah kaca</x-nav-link>
        <x-nav-link :href="route('petani.node2')" :active="request()->routeIs('petani.node2')" icon="drop">Tanah &amp; air</x-nav-link>
        <p class="nav-label nav-label--spaced">Panduan &amp; akun</p>
        <x-nav-link :href="route('petani.edukasi')" :active="request()->routeIs('petani.edukasi')" icon="book">Edukasi</x-nav-link>
        <x-nav-link :href="route('petani.settings')" :active="request()->routeIs('petani.settings')" icon="settings">Pengaturan</x-nav-link>
        <x-nav-link :href="route('sessions.index')" :active="request()->routeIs('sessions.*')" icon="shield">Sesi perangkat</x-nav-link>
        @if(auth()->user()->isAdmin())
            <p class="nav-label nav-label--spaced">Tampilan lain</p>
            <x-nav-link :href="route('dashboard')" icon="shield">Panel admin <x-icon name="arrow-up" class="nav-end-icon" /></x-nav-link>
        @endif
    @endif
</nav>
