@extends('layouts.app')
@section('title', 'Atur ulang kata sandi — Growvia')

@section('content')
<span class="icon-tile"><x-icon name="shield" /></span><h1>Kata sandi yang baru.</h1><p>Buat kata sandi unik untuk menjaga akses ke ruang kerja Anda.</p>
<x-alerts />
<form method="POST" action="{{ route('password.update') }}" class="form-stack" novalidate>
    @csrf<input type="hidden" name="token" id="token" value="{{ $token }}">
    <x-field name="email" type="email" label="Alamat email" :value="old('email', $email)" :required="true" maxlength="255" autocomplete="username" />
    <x-field name="password" type="password" label="Kata sandi baru" :required="true" hint="Minimal 8 karakter." minlength="8" autocomplete="new-password" />
    <x-field name="password_confirmation" type="password" label="Ulangi kata sandi baru" :required="true" minlength="8" autocomplete="new-password" />
    <button class="button button--wide" type="submit">Simpan kata sandi <x-icon name="arrow" /></button>
</form>
<div class="auth-divider">Ruang kerja Growvia</div><div class="text-center"><a class="text-link" href="{{ route('login') }}">Kembali ke halaman masuk</a></div>
@endsection
