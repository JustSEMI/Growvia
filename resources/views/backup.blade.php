@extends('layouts.admin')
@section('title', 'Backup — Growvia')
@section('page', 'Backup')

@section('content')
<div class="ledger-page">
<x-page-header title="Amankan datanya." description="Jadwal backup database, status terakhir, dan berkas cadangan yang bisa diunduh."><x-slot:eyebrow>Ruang kerja / Backup</x-slot:eyebrow></x-page-header>
<x-alerts />
<div class="stack">
    <section class="panel form-section form-section--green" aria-labelledby="backup-schedule-title" data-reveal>
        <div class="panel-header"><div><p class="eyebrow">01 · Penjadwalan</p><h2 class="panel-title mt-2" id="backup-schedule-title">Jadwal backup</h2><p class="panel-subtitle">Scheduler mengecek tiap menit; jam mengikuti zona server (UTC).</p></div><span class="icon-tile"><x-icon name="clock" /></span></div>
        <form method="POST" action="{{ route('dashboard.backup.schedule') }}">@csrf
            <div class="panel-body form-grid">
                <div class="field span-full"><label class="field-label" for="backup_mode">Mode jadwal</label><select class="field-input" id="backup_mode" name="backup_mode" data-custom-select required @if($errors->has('backup_mode')) aria-invalid="true" aria-describedby="backup_mode-error" @endif><option value="off" @selected(old('backup_mode', $parsed['mode']) === 'off')>Nonaktif — backup manual saja</option><option value="daily" @selected(old('backup_mode', $parsed['mode']) === 'daily')>Harian — sekali sehari</option><option value="every" @selected(old('backup_mode', $parsed['mode']) === 'every')>Interval — tiap beberapa detik</option></select>@error('backup_mode')<p class="field-error" id="backup_mode-error">{{ $message }}</p>@enderror</div>
                <x-field name="backup_time" label="Jam backup (UTC)" type="time" :value="old('backup_time', $parsed['time'])" hint="Dipakai saat mode harian." />
                <x-field name="backup_interval" label="Interval (detik)" type="number" min="60" max="999999" :value="old('backup_interval', $parsed['interval'])" hint="Dipakai saat mode interval; minimum 60 detik." />
                <div class="span-full"><x-field name="backup_keep_days" label="Simpan berapa hari" type="number" min="1" max="90" :value="old('backup_keep_days', $schedule['backup_keep_days'])" :required="true" hint="Berkas lebih tua dari umur ini dihapus otomatis." /></div>
            </div><div class="form-actions"><button class="button" type="submit">Simpan jadwal</button></div>
        </form>
    </section>

    <section class="panel" aria-labelledby="backup-status-title" data-reveal>
        <div class="panel-header"><div><p class="eyebrow">02 · Status</p><h2 class="panel-title mt-2" id="backup-status-title">Backup terakhir</h2><p class="panel-subtitle">Hasil eksekusi terakhir, manual maupun jadwal.</p></div>@if(($state['last_status'] ?? '') === 'ok')<x-badge tone="green">Sehat</x-badge>@elseif(($state['last_status'] ?? '') === 'failed')<x-badge tone="red">Gagal</x-badge>@else<x-badge tone="neutral">Belum ada</x-badge>@endif</div>
        <div class="panel-body">
            <dl class="definition-list">
                <div><dt>Terakhir dijalankan</dt><dd>{{ $state['last_run'] ?? 'Belum pernah' }}</dd></div>
                <div><dt>Berkas terakhir</dt><dd>{{ $state['last_file'] ?? '-' }}</dd></div>
                @if(! empty($state['last_error']))
                    <div><dt>Pesan galat</dt><dd>{{ $state['last_error'] }}</dd></div>
                @endif
            </dl>
        </div>
        <div class="form-actions">
            <form method="POST" action="{{ route('dashboard.backup.run') }}">@csrf
                <button class="button" type="submit" @if(($state['last_status'] ?? '') === 'running') disabled aria-busy="true" @endif><x-icon name="refresh" /><span>Buat backup sekarang</span></button>
            </form>
        </div>
    </section>

    <section class="panel" aria-labelledby="backup-files-title" data-reveal>
        <div class="panel-header"><div><p class="eyebrow">03 · Berkas</p><h2 class="panel-title mt-2" id="backup-files-title">Daftar berkas cadangan</h2><p class="panel-subtitle">Master berformat .sql; pilih format unduhan per berkas.</p></div><x-badge tone="blue">{{ count($backups) }} berkas</x-badge></div>
        <div class="panel-body">
            @forelse($backups as $file)
                <dl class="definition-list">
                    <div><dt>{{ $file['name'] }}</dt><dd>{{ number_format($file['size'] / 1024, 1) }} KB</dd></div>
                    <div><dt>Dibuat</dt><dd>{{ \Illuminate\Support\Carbon::parse($file['created_at'])->format('d/m/Y H:i') }} UTC</dd></div>
                </dl>
                <div class="form-actions">
                    @foreach($formats as $format)
                        <a class="text-link" href="{{ route('dashboard.backup.download', ['name' => $file['name'], 'format' => $format]) }}">Unduh .{{ $format }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('dashboard.backup.destroy', $file['name']) }}" data-confirm-message="Hapus berkas {{ $file['name'] }}? Tindakan ini tidak bisa dibatalkan." data-confirm-title="Hapus berkas backup" data-confirm-accept="Hapus">@csrf @method('DELETE')
                        <button class="button button--danger" type="submit"><x-icon name="trash" /><span class="sr-only">Hapus {{ $file['name'] }}</span></button>
                    </form>
                </div>
            @empty
                <div class="empty-state mx-auto"><span class="empty-icon"><x-icon name="tank" /></span><h3>Belum ada berkas</h3><p>Jalankan backup manual atau nyalakan jadwalnya, berkas cadangan muncul di sini.</p></div>
            @endforelse
        </div>
        <p class="panel-footer">Format .sql adalah berkas master; .sql.gz dan .tgz dikonversi saat diunduh.</p>
    </section>
</div>
</div>
<x-confirm-dialog />
@endsection
