@props(['user'])

<section class="panel form-section form-section--green" id="profile" aria-labelledby="profile-title" data-reveal>
    <div class="panel-header"><div><p class="eyebrow">01 · Identitas</p><h2 class="panel-title mt-2" id="profile-title">Profil kamu</h2><p class="panel-subtitle">Nama dan email yang kepake buat akun ini.</p></div><span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span></div>
    <form method="POST" action="{{ route('settings.profile') }}">@csrf @method('PATCH')
        <div class="panel-body form-grid"><x-field name="name" label="Nama lengkap" :value="old('name', $user->name)" :required="true" maxlength="255" autocomplete="name" /><x-field name="email" label="Alamat email" type="email" :value="old('email', $user->email)" :required="true" maxlength="255" autocomplete="email" /><div class="span-full"><x-field name="profile_password" type="password" label="Konfirmasi kata sandi buat ganti email" hint="Kosongin aja kalau emailnya nggak berubah. Ganti email bakal ngakhirin sesi lain dan ngebatalin tautan pemulihan yang lama." autocomplete="current-password" /></div></div>
        <div class="form-actions"><button type="submit" class="button">Simpan profil</button></div>
    </form>
</section>
<section class="panel form-section form-section--blue" id="security" aria-labelledby="password-title" data-reveal>
    <div class="panel-header"><div><p class="eyebrow">02 · Keamanan akun</p><h2 class="panel-title mt-2" id="password-title">Ganti kata sandi</h2><p class="panel-subtitle">Pake kata sandi unik yang nggak kepake di layanan lain ya.</p></div><span class="icon-tile"><x-icon name="shield" /></span></div>
    <form method="POST" action="{{ route('settings.password') }}">@csrf @method('PATCH')
        <div class="panel-body form-grid"><div class="span-full"><x-field name="current_password" type="password" label="Kata sandi sekarang" :required="true" autocomplete="current-password" /></div><x-field name="password" type="password" label="Kata sandi baru" :required="true" hint="Minimal 8 karakter." minlength="8" autocomplete="new-password" /><x-field name="password_confirmation" type="password" label="Ulangi kata sandi baru" :required="true" minlength="8" autocomplete="new-password" /></div>
        <div class="form-actions"><button type="submit" class="button">Ganti kata sandi</button></div>
    </form>
</section>
<section class="panel form-section form-section--neutral form-section--row" id="sessions" aria-labelledby="sessions-title" data-reveal>
    <div class="panel-header"><div><p class="eyebrow">03 · Aktivitas akun</p><h2 class="panel-title mt-2" id="sessions-title">Sesi perangkat</h2><p class="panel-subtitle">Cek perangkat yang lagi nyambung ke akunmu.</p></div></div>
    <div class="panel-body">
        <a class="settings-link-row" href="{{ route('sessions.index') }}"><span class="icon-tile"><x-icon name="clock" /></span><span class="settings-link-copy"><strong>Daftar sesi aktif</strong><small>Keluarin perangkat yang nggak kamu kenal.</small></span><x-icon name="arrow" class="settings-link-arrow" /></a>
    </div>
</section>
