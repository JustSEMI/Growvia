@props(['user'])

<section class="panel form-section" id="profile" aria-labelledby="profile-title" data-reveal>
    <div class="panel-header"><div><p class="eyebrow">Identitas</p><h2 class="panel-title mt-2" id="profile-title">Profil Anda</h2><p class="panel-subtitle">Nama dan email yang digunakan untuk akun ini.</p></div><span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span></div>
    <form method="POST" action="{{ route('settings.profile') }}">@csrf @method('PATCH')
        <div class="panel-body form-grid"><x-field name="name" label="Nama lengkap" :value="old('name', $user->name)" :required="true" maxlength="255" autocomplete="name" /><x-field name="email" label="Alamat email" type="email" :value="old('email', $user->email)" :required="true" maxlength="255" autocomplete="email" /><div class="span-full"><x-field name="profile_password" type="password" label="Konfirmasi kata sandi untuk mengubah email" hint="Kosongkan jika email tidak berubah. Perubahan email mengakhiri sesi lain dan membatalkan tautan pemulihan lama." autocomplete="current-password" /></div></div>
        <div class="form-actions"><button type="submit" class="button">Simpan profil</button></div>
    </form>
</section>
<section class="panel form-section" id="security" aria-labelledby="password-title" data-reveal>
    <div class="panel-header"><div><p class="eyebrow">Keamanan akun</p><h2 class="panel-title mt-2" id="password-title">Ganti kata sandi</h2><p class="panel-subtitle">Gunakan kata sandi unik yang tidak dipakai di layanan lain.</p></div><span class="icon-tile"><x-icon name="shield" /></span></div>
    <form method="POST" action="{{ route('settings.password') }}">@csrf @method('PATCH')
        <div class="panel-body form-grid"><div class="span-full"><x-field name="current_password" type="password" label="Kata sandi sekarang" :required="true" autocomplete="current-password" /></div><x-field name="password" type="password" label="Kata sandi baru" :required="true" hint="Minimal 8 karakter." minlength="8" autocomplete="new-password" /><x-field name="password_confirmation" type="password" label="Ulangi kata sandi baru" :required="true" minlength="8" autocomplete="new-password" /></div>
        <div class="form-actions"><a class="button button--secondary" href="{{ route('sessions.index') }}">Sesi perangkat</a><button type="submit" class="button">Ganti kata sandi</button></div>
    </form>
</section>
