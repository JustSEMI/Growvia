@extends('layouts.petani')
@section('title', 'Pengaturan akun — Growvia')
@section('page', 'Pengaturan')

@section('content')
<x-page-header title="Ruang untuk akun Anda." description="Perbarui profil dan jaga akses ke ruang kerja tetap aman."><x-slot:eyebrow>Akun / Pengaturan</x-slot:eyebrow></x-page-header>
<x-alerts />
<div class="settings-layout"><nav class="settings-nav" aria-label="Bagian pengaturan"><a href="#profile"><x-icon name="users" />Profil Anda</a><a href="#security"><x-icon name="shield" />Kata sandi</a></nav><div class="stack"><x-account-forms :user="auth()->user()" /></div></div>
@endsection
