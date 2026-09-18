@extends('layouts.app')
@section('title', 'Masuk — Growvia')

@section('content')
<span class="icon-tile"><x-icon name="leaf" /></span>
<h1>Selamat datang kembali.</h1>
<p>Masuk untuk membuka ruang kerja kebun Anda.</p>
<x-alerts />
<form action="{{ route('login') }}" method="POST" class="form-stack" novalidate>
    @csrf
    <x-field name="email" label="Alamat email" type="email" :value="old('email')" :required="true" autocomplete="username" maxlength="255" placeholder="nama@growvia.id" autofocus />
    <x-field name="password" label="Kata sandi" type="password" :required="true" autocomplete="current-password" placeholder="Masukkan kata sandi" />
    <div class="auth-form-bottom"><label class="check-inline" for="remember"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>Ingat saya</label><a href="{{ route('password.request') }}" class="text-link">Lupa kata sandi?</a></div>
    <button type="submit" class="button button--wide">Masuk ke ruang kerja <x-icon name="arrow" /></button>
</form>
<div class="auth-divider">Belum punya akses?</div>
<p class="auth-note">Hubungi admin kebun untuk mendapatkan akses.</p>
@endsection
