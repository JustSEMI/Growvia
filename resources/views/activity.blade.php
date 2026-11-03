@extends('layouts.admin')
@section('title', 'Aktivitas — Growvia')
@section('page', 'Aktivitas')

@section('content')
<x-page-header title="Riwayat aktivitas aplikasi" description="Catatan perubahan akun, sesi, dan pengaturan Growvia. Bukan riwayat sensor atau perangkat.">
    <x-slot:eyebrow>Kelola / Aktivitas</x-slot:eyebrow>
</x-page-header>
<x-alerts />

<form class="panel form-section section-space" method="GET" action="{{ route('activity.index') }}" aria-label="Filter riwayat aktivitas">
    <div class="panel-body form-grid">
        <x-field name="q" label="Cari nama" :value="$search" maxlength="100" placeholder="Nama pelaku atau akun terkait" />
        <div class="field">
            <label class="field-label" for="action">Jenis aktivitas</label>
            <select class="field-input" id="action" name="action" data-custom-select @if($errors->has('action')) aria-invalid="true" aria-describedby="action-error" @endif>
                <option value="">Semua aktivitas</option>
                @foreach($actionLabels as $action => $label)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $label }}</option>
                @endforeach
            </select>
            @error('action')<p class="field-error" id="action-error">{{ $message }}</p>@enderror
        </div>
        <x-field name="from" type="date" label="Dari tanggal" :value="$from" />
        <x-field name="to" type="date" label="Sampai tanggal" :value="$to" />
    </div>
    <div class="form-actions"><a class="button button--secondary" href="{{ route('activity.index') }}">Hapus filter</a><button class="button" type="submit">Terapkan filter</button></div>
</form>

<section class="panel mt-6" aria-labelledby="activity-title" data-reveal>
    <div class="table-toolbar">
        <div><h2 class="panel-title" id="activity-title">Aktivitas kecatat</h2><p class="panel-subtitle">{{ $activities->total() }} aktivitas · Zona waktu {{ $timezone }}</p></div>
    </div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Riwayat aktivitas, geser untuk melihat semua kolom">
        <table class="data-table">
            <thead><tr><th scope="col">Waktu</th><th scope="col">Pelaku</th><th scope="col">Aktivitas</th><th scope="col">Akun terkait</th><th scope="col">Bagian yang berubah</th></tr></thead>
            <tbody>
            @forelse($activities as $activity)
                <tr>
                    <td><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->copy()->timezone($timezone)->locale('id')->translatedFormat('d F Y, H.i.s') }} {{ $timezone }}</time></td>
                    <td>{{ $activity->actor_name }}</td>
                    <td>{{ $actionLabels[$activity->action] ?? 'Aktivitas aplikasi' }}</td>
                    <td>{{ $activity->subject_name ?? '—' }}</td>
                    <td>{{ collect($activity->fields ?? [])->filter(fn (mixed $field, mixed $key): bool => is_int($key) && is_string($field))->map(fn (string $field): ?string => $fieldLabels[$field] ?? null)->filter()->unique()->implode(', ') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="table-empty">
                    @if($filtered)
                        <strong>Tidak ada aktivitas yang sesuai filter.</strong><p>Coba nama, jenis aktivitas, atau rentang tanggal lain ya.</p>
                    @elseif($activities->total() > 0)
                        <strong>Nggak ada aktivitas di halaman ini.</strong><a class="text-link" href="{{ route('activity.index') }}">Balik ke halaman pertama</a>
                    @else
                        <strong>Belum ada aktivitas aplikasi.</strong><p>Catatannya muncul setelah ada yang ganti akun, sesi, atau pengaturan.</p>
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($activities->hasPages())
        <nav class="pagination" aria-label="Navigasi halaman aktivitas">
            <span class="pagination-summary">{{ $activities->firstItem() }}–{{ $activities->lastItem() }} dari {{ $activities->total() }} aktivitas</span>
            @if($activities->onFirstPage())
                <span data-page aria-disabled="true">Sebelumnya</span>
            @else
                <a href="{{ $activities->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @endif
            <span data-page aria-current="page">{{ $activities->currentPage() }}</span>
            @if($activities->hasMorePages())
                <a href="{{ $activities->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else
                <span data-page aria-disabled="true">Berikutnya</span>
            @endif
        </nav>
    @endif
    <p class="panel-footer">Yang dicatat cuma nama bagiannya aja, bukan nilai isian atau kata sandi.</p>
</section>
@endsection
