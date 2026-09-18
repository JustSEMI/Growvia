@extends('layouts.app')
@section('title', 'Lupa kata sandi — Growvia')

@section('content')
<span class="icon-tile"><x-icon name="shield" /></span><h1>Kembali ke kebun Anda.</h1><p>Masukkan email akun untuk meminta tautan pengaturan ulang kata sandi.</p>
<x-alerts />
<form method="POST" action="{{ route('password.email') }}" class="form-stack" novalidate>@csrf<x-field name="email" type="email" label="Alamat email" :value="old('email')" :required="true" autocomplete="username" maxlength="255" placeholder="nama@growvia.id" autofocus /><button type="submit" class="button button--wide">Kirim tautan pengaturan ulang <x-icon name="arrow" /></button></form>
<div class="auth-divider">Sudah ingat?</div><div class="text-center"><a href="{{ route('login') }}" class="text-link">Kembali ke halaman masuk</a></div>
@endsection
