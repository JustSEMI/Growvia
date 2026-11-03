@extends($layout)
@section('title', 'Sesi perangkat — Growvia')
@section('page', 'Sesi perangkat')

@section('content')
<x-page-header title="Perangkat yang nyambung." description="Cek sesi masuk akunmu. Info perangkat asalnya dari browser, bukan identitas perangkat yang udah diverifikasi.">
    <x-slot:eyebrow>Akun / Keamanan</x-slot:eyebrow>
    <x-slot:actions><a class="button button--secondary" href="{{ $back }}#security">Kembali ke pengaturan</a></x-slot:actions>
</x-page-header>
<x-alerts />
<div class="stack">
    <section class="panel" aria-labelledby="sessions-title">
        <div class="panel-header"><div><h2 class="panel-title" id="sessions-title">Sesi aktif</h2><p class="panel-subtitle">Waktu ditampilkan dalam {{ config('app.timezone') }}. Sesi kedaluwarsa tidak ditampilkan.</p></div><x-icon name="display" /></div>
        @if($supported)
            <div class="panel-body">
                @forelse($sessions as $session)
                    <div class="device-row"><span class="icon-tile"><x-icon name="display" /></span><div class="device-copy"><strong>{{ $session['browser'] }} · {{ $session['device'] }}</strong><small>IP {{ $session['ip'] }}</small><small>Terakhir digunakan {{ $session['last_active'] }}</small></div>@if($session['current'])<x-badge tone="green">Perangkat ini</x-badge>@endif</div>
                @empty
                    <p class="panel-subtitle">Sesimu lagi dicatat. Muat ulang halamannya biar kelihatan.</p>
                @endforelse
            </div>
            {{ $sessions->links('vendor.pagination.growvia', ['noun' => 'sesi']) }}
        @else
            <div class="panel-body"><p class="panel-subtitle">Daftar perangkat butuh penyimpanan sesi database. Kabarin administrator buat ngaktifinnya.</p></div>
        @endif
    </section>
    <section class="panel form-section" aria-labelledby="revoke-title">
        <div class="panel-header"><div><h2 class="panel-title" id="revoke-title">Keluar dari perangkat lain</h2><p class="panel-subtitle">Sesi sama akses “Ingat saya” di perangkat lain bakal diakhirin. Sesi ini tetap jalan; “Ingat saya” perlu dinyalain lagi pas masuk berikutnya.</p></div><x-icon name="shield" /></div>
        <form method="POST" action="{{ route('sessions.destroy') }}">@csrf @method('DELETE')
            <div class="panel-body"><x-field name="sessions_password" type="password" label="Kata sandi sekarang" :required="true" autocomplete="current-password" /></div>
            <div class="form-actions"><button class="button button--danger" type="submit">Keluar dari perangkat lain</button></div>
        </form>
    </section>
</div>
@endsection
