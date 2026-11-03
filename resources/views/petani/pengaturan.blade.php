@extends('layouts.petani')
@section('title', 'Pengaturan akun — Growvia')
@section('page', 'Pengaturan')

@section('content')
<x-page-header title="Ruang buat akunmu." description="Benerin profil dan jagain akses biar ruang kerja tetap aman."><x-slot:eyebrow>Akun / Pengaturan</x-slot:eyebrow></x-page-header>
<x-alerts />
<div class="settings-layout"><nav class="settings-nav" aria-label="Bagian pengaturan"><a href="#profile"><x-icon name="users" />Profil kamu</a><a href="#security"><x-icon name="shield" />Kata sandi</a></nav><div class="stack"><x-account-forms :user="auth()->user()" /></div></div>
@endsection
