@extends('layouts.app')
@section('title', 'Masuk — Growvia')

@section('content')
<div class="auth-head" data-reveal>
    <span class="icon-tile"><x-icon name="leaf" /></span>
    <div>
        <p class="eyebrow">Ruang kerja kebunmu</p>
        <h1>Selamat datang <em>lagi.</em></h1>
        <p>Masuk untuk membuka ruang kerja dan lihat kebunmu bertumbuh.</p>
    </div>
</div>

@if($errors->has('email') || $errors->has('password'))
<div class="alert alert--error auth-login-error" role="alert" tabindex="-1" data-error-summary data-reveal>
    <x-icon name="info" />
    <p>Email atau kata sandi salah. Coba periksa lagi.</p>
</div>
@endif

<form action="{{ route('login') }}" method="POST" class="form-stack" novalidate data-reveal>
    @csrf
    <x-field name="email" label="Alamat email" type="email" icon="mail" :value="old('email')" :required="true" autocomplete="username" maxlength="255" placeholder="nama@growvia.id" autofocus />
    <x-field name="password" label="Kata sandi" type="password" icon="lock" :required="true" autocomplete="current-password" placeholder="Masukkan kata sandi" />
    <div class="auth-form-bottom"><label class="check-inline" for="remember"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>Ingat saya</label><a href="{{ route('password.request') }}" class="text-link">Lupa kata sandi?</a></div>
    <button type="submit" class="button button--wide button--login">Login <x-icon name="arrow" /></button>
</form>

<div class="auth-divider" data-reveal>Belum punya akses?</div>
<p class="auth-note" data-reveal>Minta akses ke admin kebun ya.</p>
@endsection
