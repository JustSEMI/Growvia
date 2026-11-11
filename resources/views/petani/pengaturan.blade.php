@extends('layouts.petani')
@section('title', 'Pengaturan akun — Growvia')
@section('page', 'Pengaturan')

@section('content')
@php($user = auth()->user())
<div class="ledger-page">
<x-page-header title="Ruang akunmu." description="Benerin profil, ganti kata sandi, dan jagain akses kebunmu tetap aman."><x-slot:eyebrow>Panel petani / Pengaturan</x-slot:eyebrow></x-page-header>
<x-alerts />
<div class="settings-layout">
    <div class="settings-aside">
        <nav class="settings-nav" aria-label="Bagian pengaturan"><a href="#profile"><x-icon name="users" />Profil kamu<span class="settings-nav-num">01</span></a><a href="#security"><x-icon name="shield" />Kata sandi<span class="settings-nav-num">02</span></a><a href="#sessions"><x-icon name="clock" />Sesi perangkat<span class="settings-nav-num">03</span></a></nav>
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
    </div>
</div>
</div>
@endsection
