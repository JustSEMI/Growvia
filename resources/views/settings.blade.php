@extends('layouts.admin')
@section('title', 'Pengaturan - Growvia')
@section('page', 'Pengaturan')

@section('content')
<div class="ledger-page">
<x-page-header title="Atur-atur kebunmu." description="Urus akun, koneksi, dan settingan tempat tumbuh di sini."><x-slot:eyebrow>Kelola / Pengaturan</x-slot:eyebrow></x-page-header>
<x-alerts />
<div class="settings-layout">
    <div class="settings-aside">
        <nav class="settings-nav" aria-label="Bagian pengaturan"><a href="#profile"><x-icon name="users" />Profil kamu<span class="settings-nav-num">01</span></a><a href="#security"><x-icon name="shield" />Kata sandi<span class="settings-nav-num">02</span></a><a href="#sessions"><x-icon name="clock" />Sesi perangkat<span class="settings-nav-num">03</span></a><a href="#mqtt"><x-icon name="connection" />Koneksi MQTT<span class="settings-nav-num">04</span></a><a href="#climate"><x-icon name="sun" />Ambang iklim<span class="settings-nav-num">05</span></a><a href="#soil"><x-icon name="drop" />Tanah & pompa<span class="settings-nav-num">06</span></a><a href="#notifications"><x-icon name="info" />Notifikasi<span class="settings-nav-num">07</span></a><a href="#sensor-data"><x-icon name="trash" />Data sensor<span class="settings-nav-num">08</span></a></nav>
        <div class="settings-tips">
            <p class="settings-tips-title"><x-icon name="leaf" />Jaga akunmu tetap aman</p>
            <p class="settings-tip"><x-icon name="shield" />Pake kata sandi unik yang nggak kepake di aplikasi lain.</p>
            <p class="settings-tip"><x-icon name="clock" />Sering-sering cek daftar sesi, keluarin perangkat yang nggak kamu kenal.</p>
            <p class="settings-tip"><x-icon name="mail" />Email aktif kepake buat kirim tautan pemulihan kata sandi.</p>
        </div>
    </div>
    <div class="stack">
        <section class="panel account-card" data-reveal aria-label="Ringkasan akun">
            <span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="account-card-copy">
                <p class="eyebrow">Akun aktif</p>
                <strong>{{ $user->name }}</strong>
                <small><x-icon name="mail" />{{ $user->email }}</small>
                <small><x-icon name="clock" />Bergabung {{ $user->created_at?->format('d/m/Y') }}</small>
            </div>
            <x-badge>{{ $user->isAdmin() ? 'Administrator' : 'Petani' }}</x-badge>
        </section>
        <x-account-forms :user="$user" />
        <section class="panel form-section form-section--amber" id="mqtt" aria-labelledby="mqtt-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">04 · Node & koneksi</p><h2 class="panel-title mt-2" id="mqtt-title">Broker MQTT</h2><p class="panel-subtitle">Koneksi yang dipakai consumer data sensor. Kosongkan untuk pakai nilai .env.</p></div><x-badge tone="green">Aktif</x-badge></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="mqtt">
                <div class="panel-body form-grid">
                    <div class="span-full"><x-field name="mqtt_host" label="Host broker" :value="old('mqtt_host', data_get($settings, 'mqtt_host'))" placeholder="broker.local" maxlength="255" hint="Kosong = pakai MQTT_HOST dari .env server." /></div>
                    <x-field name="mqtt_port" label="Port" type="number" :value="old('mqtt_port', data_get($settings, 'mqtt_port'))" min="1" max="65535" hint="Kosong = pakai MQTT_PORT dari .env." />
                    <x-field name="mqtt_user" label="Nama pengguna broker" :value="old('mqtt_user', data_get($settings, 'mqtt_user'))" maxlength="255" autocomplete="off" hint="Kosong = pakai MQTT_USERNAME dari .env. Password selalu dari .env." />
                    <x-field name="mqtt_topic" label="Topik dasar" :value="old('mqtt_topic', data_get($settings, 'mqtt_topic'))" maxlength="255" hint="Awalan topik untuk data node." />
                    <x-field name="update_interval" label="Interval pembaruan (detik)" type="number" :value="old('update_interval', data_get($settings, 'update_interval'))" :required="true" min="5" max="300" hint="Rentang 5–300 detik." />
                </div><div class="form-actions"><button class="button" type="submit">Simpan koneksi</button></div>
            </form>
        </section>

        <section class="panel form-section form-section--green" id="climate" aria-labelledby="climate-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">05 · Device 1</p><h2 class="panel-title mt-2" id="climate-title">Ambang iklim</h2><p class="panel-subtitle">Sesuaiin target sama tanaman dan kondisi kebunmu.</p></div><span class="icon-tile"><x-icon name="sun" /></span></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="th1">
                <div class="panel-body form-grid">
                    <x-field name="th_suhu_min" label="Suhu minimum (°C)" type="number" step="0.1" :value="old('th_suhu_min', data_get($settings, 'th_suhu_min'))" :required="true" />
                    <x-field name="th_suhu_max" label="Suhu maksimum (°C)" type="number" step="0.1" :value="old('th_suhu_max', data_get($settings, 'th_suhu_max'))" :required="true" />
                    <x-field name="th_hum_min" label="Kelembapan minimum (%)" type="number" step="1" min="0" max="100" :value="old('th_hum_min', data_get($settings, 'th_hum_min'))" :required="true" />
                    <x-field name="th_hum_max" label="Kelembapan maksimum (%)" type="number" step="1" min="0" max="100" :value="old('th_hum_max', data_get($settings, 'th_hum_max'))" :required="true" />
                </div><div class="form-actions"><button class="button" type="submit">Simpan ambang iklim</button></div>
            </form>
        </section>

        <section class="panel form-section form-section--blue" id="soil" aria-labelledby="soil-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">06 · Device 2</p><h2 class="panel-title mt-2" id="soil-title">Tanah & pompa</h2><p class="panel-subtitle">Udah kesimpen, tapi belum jalan di perangkat.</p></div><span class="icon-tile"><x-icon name="drop" /></span></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="th2">
                <div class="panel-body form-grid">
                    <x-field name="soil_on" label="Mulai menyiram di bawah (%)" type="number" step="1" min="0" max="100" :value="old('soil_on', data_get($settings, 'soil_on'))" :required="true" />
                    <x-field name="soil_off" label="Berhenti menyiram di atas (%)" type="number" step="1" min="0" max="100" :value="old('soil_off', data_get($settings, 'soil_off'))" :required="true" />
                    <x-field name="tank_min" label="Isi minimum tandon (%)" type="number" step="1" min="0" max="100" :value="old('tank_min', data_get($settings, 'tank_min'))" :required="true" />
                    <x-field name="pump_max_run" label="Durasi maksimum (menit)" type="number" min="1" max="120" :value="old('pump_max_run', data_get($settings, 'pump_max_run'))" :required="true" />
                    <div class="field span-full"><label class="field-label" for="pump_mode">Mode pompa</label><select class="field-input" id="pump_mode" name="pump_mode" data-custom-select required @if($errors->has('pump_mode')) aria-invalid="true" aria-describedby="pump_mode-error" @endif><option value="auto" @selected(old('pump_mode', data_get($settings, 'pump_mode')) === 'auto')>Otomatis (mengikuti ambang tanah)</option><option value="manual" @selected(old('pump_mode', data_get($settings, 'pump_mode')) === 'manual')>Manual (lewat kontrol operator)</option></select><p class="field-hint">Kontrol perangkat belum aktif.</p>@error('pump_mode')<p class="field-error" id="pump_mode-error">{{ $message }}</p>@enderror</div>
                </div><div class="form-actions"><button class="button" type="submit">Simpan tanah & pompa</button></div>
            </form>
        </section>

        <section class="panel form-section form-section--neutral" id="notifications" aria-labelledby="notifications-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">07 · Pemberitahuan</p><h2 class="panel-title mt-2" id="notifications-title">Preferensi notifikasi</h2><p class="panel-subtitle">Pilihannya kesimpen; ngirim notif perangkatnya belum jalan.</p></div><x-badge tone="amber">Belum aktif</x-badge></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="notif">
                <div class="panel-body">
                    <label class="check-row" for="notif_pompa"><span><strong>Perubahan status pompa</strong><small>Pemberitahuan saat pompa menyala atau berhenti.</small></span><input id="notif_pompa" name="notif_pompa" type="checkbox" value="1" @checked(old('notif_pompa', data_get($settings, 'notif_pompa')) === '1')></label>
                    <label class="check-row" for="notif_tandon"><span><strong>Persediaan air rendah</strong><small>Pemberitahuan saat level tandon melewati batas minimum.</small></span><input id="notif_tandon" name="notif_tandon" type="checkbox" value="1" @checked(old('notif_tandon', data_get($settings, 'notif_tandon')) === '1')></label>
                    <label class="check-row" for="notif_offline"><span><strong>Node tidak terhubung</strong><small>Preferensi peringatan ketika node tidak mengirim data.</small></span><input id="notif_offline" name="notif_offline" type="checkbox" value="1" @checked(old('notif_offline', data_get($settings, 'notif_offline')) === '1')></label>
                </div><div class="form-actions"><button class="button" type="submit">Simpan notifikasi</button></div>
            </form>
        </section>

        @php($purgeConfirmMessage = $readingsCount > 0 ? 'Hapus permanen '.number_format($readingsCount).' data sensor dari database? Riwayat dan grafik akan dikosongkan. Tindakan ini tidak bisa dibatalkan.' : 'Belum ada data sensor tersimpan. Tetap jalankan pembersihan data sensor?')
        <section class="panel form-section form-section--red" id="sensor-data" aria-labelledby="sensor-data-title" data-reveal>
            <div class="panel-header">
                <div>
                    <p class="eyebrow">08 · Pemeliharaan data</p>
                    <h2 class="panel-title mt-2" id="sensor-data-title">Data sensor</h2>
                    <p class="panel-subtitle">Hapus seluruh riwayat pembacaan sensor dan grafik. Akun pengguna dan pengaturan sistem tetap aman.</p>
                </div>
                <x-badge :tone="$readingsCount > 0 ? 'amber' : 'neutral'">{{ number_format($readingsCount) }} baris</x-badge>
            </div>
            <div class="panel-body">
                <p class="field-hint" id="sensor-data-hint">Tersimpan {{ number_format($readingsCount) }} data pembacaan dari semua node. Data baru tetap masuk kembali selama perangkat aktif mengirim ke broker MQTT.</p>
                <p class="field-hint mt-2" data-confirm-required>Konfirmasi hapus memerlukan JavaScript dan dukungan dialog browser. Aktifkan JavaScript, perbarui browser, atau muat ulang jika tombol tetap nonaktif.</p>
            </div>
            <div class="form-actions">
                <form method="POST" action="{{ route('settings.readings.destroy') }}"
                    data-confirm-title="Konfirmasi hapus data sensor"
                    data-confirm-message="{{ $purgeConfirmMessage }}"
                    data-confirm-accept="Hapus semua data">
                    @csrf
                    @method('DELETE')
                    <button class="button button--danger" type="submit" data-confirm-trigger disabled aria-describedby="sensor-data-hint"><x-icon name="trash" /><span>Hapus semua data sensor</span></button>
                </form>
            </div>
        </section>
    </div>
</div>
</div>
<x-confirm-dialog />
@endsection
