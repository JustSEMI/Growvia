@extends('layouts.admin')
@section('title', 'Pengaturan — Growvia')
@section('page', 'Pengaturan')

@section('content')
<x-page-header title="Atur-atur kebunmu." description="Urus akun, koneksi, dan settingan tempat tumbuh di sini."><x-slot:eyebrow>Kelola / Pengaturan</x-slot:eyebrow></x-page-header>
<x-alerts />
<div class="settings-layout">
    <nav class="settings-nav" aria-label="Bagian pengaturan"><a href="#profile"><x-icon name="users" />Profil kamu</a><a href="#security"><x-icon name="shield" />Kata sandi</a><a href="#mqtt"><x-icon name="connection" />Koneksi MQTT</a><a href="#climate"><x-icon name="sun" />Ambang iklim</a><a href="#soil"><x-icon name="drop" />Tanah &amp; pompa</a><a href="#notifications"><x-icon name="info" />Notifikasi</a></nav>
    <div class="stack">
        <x-account-forms :user="$user" />
        <section class="panel form-section" id="mqtt" aria-labelledby="mqtt-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">Node &amp; koneksi</p><h2 class="panel-title mt-2" id="mqtt-title">Broker MQTT</h2><p class="panel-subtitle">Settingan koneksi buat nyambungin perangkat.</p></div><x-badge tone="amber">Belum terintegrasi</x-badge></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="mqtt">
                <div class="panel-body form-grid">
                    <div class="span-full"><x-field name="mqtt_host" label="Host broker" :value="old('mqtt_host', data_get($settings, 'mqtt_host'))" placeholder="broker.local" maxlength="255" hint="Nyimpen host doang belum bikin nyambung ke broker ya." /></div>
                    <x-field name="mqtt_port" label="Port" type="number" :value="old('mqtt_port', data_get($settings, 'mqtt_port'))" min="1" max="65535" />
                    <x-field name="mqtt_user" label="Nama pengguna broker" :value="old('mqtt_user', data_get($settings, 'mqtt_user'))" maxlength="255" autocomplete="off" />
                    <x-field name="mqtt_topic" label="Topik dasar" :value="old('mqtt_topic', data_get($settings, 'mqtt_topic'))" maxlength="255" hint="Awalan topik untuk data node." />
                    <x-field name="update_interval" label="Interval pembaruan (detik)" type="number" :value="old('update_interval', data_get($settings, 'update_interval'))" :required="true" min="5" max="300" hint="Rentang 5–300 detik." />
                </div><div class="form-actions"><button class="button" type="submit">Simpan koneksi</button></div>
            </form>
        </section>

        <section class="panel form-section" id="climate" aria-labelledby="climate-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">Node1</p><h2 class="panel-title mt-2" id="climate-title">Ambang iklim</h2><p class="panel-subtitle">Sesuaiin target sama tanaman dan kondisi kebunmu.</p></div><span class="icon-tile icon-tile--amber"><x-icon name="sun" /></span></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="th1">
                <div class="panel-body form-grid">
                    <x-field name="th_suhu_min" label="Suhu minimum (°C)" type="number" step="0.1" :value="old('th_suhu_min', data_get($settings, 'th_suhu_min'))" :required="true" />
                    <x-field name="th_suhu_max" label="Suhu maksimum (°C)" type="number" step="0.1" :value="old('th_suhu_max', data_get($settings, 'th_suhu_max'))" :required="true" />
                    <x-field name="th_hum_min" label="Kelembapan minimum (%)" type="number" step="1" min="0" max="100" :value="old('th_hum_min', data_get($settings, 'th_hum_min'))" :required="true" />
                    <x-field name="th_hum_max" label="Kelembapan maksimum (%)" type="number" step="1" min="0" max="100" :value="old('th_hum_max', data_get($settings, 'th_hum_max'))" :required="true" />
                    <x-field name="th_co2_min" label="CO2 minimum (ppm)" type="number" step="1" min="0" :value="old('th_co2_min', data_get($settings, 'th_co2_min'))" :required="true" />
                    <x-field name="th_co2_max" label="CO2 maksimum (ppm)" type="number" step="1" min="0" :value="old('th_co2_max', data_get($settings, 'th_co2_max'))" :required="true" />
                    <div class="span-full"><x-field name="th_light_min" label="Cahaya minimum (klux)" type="number" step="0.1" min="0" :value="old('th_light_min', data_get($settings, 'th_light_min'))" :required="true" hint="Nilai minimum harus lebih kecil dari maksimum pada setiap pasangan ambang." /></div>
                </div><div class="form-actions"><button class="button" type="submit">Simpan ambang iklim</button></div>
            </form>
        </section>

        <section class="panel form-section" id="soil" aria-labelledby="soil-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">Node2</p><h2 class="panel-title mt-2" id="soil-title">Tanah &amp; pompa</h2><p class="panel-subtitle">Udah kesimpen, tapi belum jalan di perangkat.</p></div><span class="icon-tile icon-tile--blue"><x-icon name="drop" /></span></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="th2">
                <div class="panel-body form-grid">
                    <x-field name="soil_on" label="Mulai menyiram di bawah (%)" type="number" step="1" min="0" max="100" :value="old('soil_on', data_get($settings, 'soil_on'))" :required="true" />
                    <x-field name="soil_off" label="Berhenti menyiram di atas (%)" type="number" step="1" min="0" max="100" :value="old('soil_off', data_get($settings, 'soil_off'))" :required="true" />
                    <x-field name="tank_min" label="Isi minimum tandon (%)" type="number" step="1" min="0" max="100" :value="old('tank_min', data_get($settings, 'tank_min'))" :required="true" />
                    <x-field name="pump_max_run" label="Durasi maksimum (menit)" type="number" min="1" max="120" :value="old('pump_max_run', data_get($settings, 'pump_max_run'))" :required="true" />
                    <div class="field span-full"><label class="field-label" for="pump_mode">Mode pompa</label><select class="field-input" id="pump_mode" name="pump_mode" data-custom-select required @if($errors->has('pump_mode')) aria-invalid="true" aria-describedby="pump_mode-error" @endif><option value="auto" @selected(old('pump_mode', data_get($settings, 'pump_mode')) === 'auto')>Otomatis — ngikutin ambang tanah</option><option value="manual" @selected(old('pump_mode', data_get($settings, 'pump_mode')) === 'manual')>Manual — lewat kontrol operator</option></select><p class="field-hint">Kontrol perangkat belum aktif.</p>@error('pump_mode')<p class="field-error" id="pump_mode-error">{{ $message }}</p>@enderror</div>
                </div><div class="form-actions"><button class="button" type="submit">Simpan tanah &amp; pompa</button></div>
            </form>
        </section>

        <section class="panel form-section" id="notifications" aria-labelledby="notifications-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">Pemberitahuan</p><h2 class="panel-title mt-2" id="notifications-title">Preferensi notifikasi</h2><p class="panel-subtitle">Pilihannya kesimpen; ngirim notif perangkatnya belum jalan.</p></div><x-badge tone="amber">Belum aktif</x-badge></div>
            <form method="POST" action="{{ route('settings.system') }}">@csrf @method('PATCH')<input type="hidden" name="section" value="notif">
                <div class="panel-body">
                    <label class="check-row" for="notif_pompa"><span><strong>Perubahan status pompa</strong><small>Pemberitahuan saat pompa menyala atau berhenti.</small></span><input id="notif_pompa" name="notif_pompa" type="checkbox" value="1" @checked(old('notif_pompa', data_get($settings, 'notif_pompa')) === '1')></label>
                    <label class="check-row" for="notif_cahaya"><span><strong>Cahaya di bawah ambang</strong><small>Pemberitahuan saat intensitas cahaya rendah.</small></span><input id="notif_cahaya" name="notif_cahaya" type="checkbox" value="1" @checked(old('notif_cahaya', data_get($settings, 'notif_cahaya')) === '1')></label>
                    <label class="check-row" for="notif_tandon"><span><strong>Persediaan air rendah</strong><small>Pemberitahuan saat level tandon melewati batas minimum.</small></span><input id="notif_tandon" name="notif_tandon" type="checkbox" value="1" @checked(old('notif_tandon', data_get($settings, 'notif_tandon')) === '1')></label>
                    <label class="check-row" for="notif_offline"><span><strong>Node tidak terhubung</strong><small>Preferensi peringatan ketika node tidak mengirim data.</small></span><input id="notif_offline" name="notif_offline" type="checkbox" value="1" @checked(old('notif_offline', data_get($settings, 'notif_offline')) === '1')></label>
                </div><div class="form-actions"><button class="button" type="submit">Simpan notifikasi</button></div>
            </form>
        </section>
    </div>
</div>
@endsection
