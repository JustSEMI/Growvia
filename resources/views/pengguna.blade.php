@extends('layouts.admin')
@section('title', 'Pengguna — Growvia')
@section('page', 'Pengguna')

@section('content')
<x-page-header title="Orang di balik kebun." description="Kelola akses administrator dan petani dalam satu ruang kerja.">
    <x-slot:eyebrow>Kelola / Pengguna</x-slot:eyebrow>
    <x-slot:actions><a class="button" href="{{ route('users.index') }}#create-account"><x-icon name="plus" />Buat akun</a></x-slot:actions>
</x-page-header>
<x-alerts :prefix="$editing ? 'edit_' : ''" />

<div class="form-layout">
    <section class="panel" aria-labelledby="users-title" data-reveal>
        <div class="table-toolbar"><div><h2 class="panel-title" id="users-title">Anggota ruang kerja</h2><p class="panel-subtitle">{{ $users->total() }} akun ditemukan</p></div><x-badge tone="green">Akses terkelola</x-badge></div>
        <form method="GET" action="{{ route('users.index') }}" class="panel-body form-grid user-filters" aria-label="Filter pengguna">
            <div class="span-full"><x-field name="q" id="user-search" label="Cari nama atau email" :value="request('q')" maxlength="100" placeholder="Nama atau alamat email" /></div>
            <div class="field"><label class="field-label" for="filter-role">Hak akses</label><select id="filter-role" name="role" class="field-input" data-custom-select><option value="">Semua hak akses</option><option value="admin" @selected(request('role') === 'admin')>Administrator</option><option value="user" @selected(request('role') === 'user')>Petani</option></select></div>
            <div class="field"><label class="field-label" for="filter-status">Status akun</label><select id="filter-status" name="status" class="field-input" data-custom-select><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option></select></div>
            <div class="field"><label class="field-label" for="sort">Urutkan</label><select id="sort" name="sort" class="field-input" data-custom-select><option value="newest" @selected(request('sort', 'newest') === 'newest')>Terbaru bergabung</option><option value="oldest" @selected(request('sort') === 'oldest')>Terlama bergabung</option><option value="name_asc" @selected(request('sort') === 'name_asc')>Nama A–Z</option><option value="name_desc" @selected(request('sort') === 'name_desc')>Nama Z–A</option></select></div>
            <div class="filter-actions"><button class="button" type="submit">Terapkan</button><a class="button button--secondary" href="{{ route('users.index') }}">Bersihkan</a></div>
        </form>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Daftar pengguna, geser untuk melihat semua kolom">
            <table class="data-table">
                <thead><tr><th scope="col">Pengguna</th><th scope="col">Hak akses</th><th scope="col" class="text-right">Aksi</th></tr></thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td><div class="cell-user"><span class="avatar">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span><div><strong>{{ $u->name }} @if($u->is(auth()->user()))<span class="text-muted text-xs">(Anda)</span>@endif</strong><small>{{ $u->email }}</small><small>Bergabung {{ $u->created_at->format('d M Y') }}</small></div></div></td>
                        <td><x-badge :tone="$u->isAdmin() ? 'green' : 'neutral'">{{ $u->isAdmin() ? 'Admin' : 'Petani' }}</x-badge><div class="mt-2"><x-badge :tone="$u->is_active ? 'green' : 'neutral'">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge></div></td>
                        <td><div class="table-actions">
                            <a class="button button--secondary" href="{{ route('users.index', ['edit' => $u->id]) }}#edit-account" aria-label="Ubah akun {{ $u->name }}"><x-icon name="edit" /><span>Ubah</span></a>
                            @unless($u->is(auth()->user()))
                                <form method="POST" action="{{ route('users.status', $u) }}" data-confirm-name="{{ $u->name }}" data-confirm-kind="{{ $u->is_active ? 'deactivate' : 'activate' }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $u->is_active ? '0' : '1' }}"><button class="button button--secondary" type="submit" data-confirm-trigger disabled aria-label="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $u->name }}">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                                <form method="POST" action="{{ route('users.destroy', $u) }}" data-confirm-name="{{ $u->name }}">@csrf @method('DELETE')<button class="button button--danger" type="submit" data-confirm-trigger disabled aria-label="Hapus akun {{ $u->name }}"><x-icon name="trash" /><span class="sr-only">Hapus</span></button></form>
                            @endunless
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="table-empty">Tidak ada pengguna yang cocok. Coba kata kunci atau filter lain.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
        <p class="panel-footer" data-confirm-required>Konfirmasi hapus memerlukan JavaScript dan dukungan dialog browser. Aktifkan JavaScript, perbarui browser, atau muat ulang jika tombol tetap nonaktif.</p>
    </section>

    <div class="stack">
        @if($editing)
        <section id="edit-account" class="panel form-section" aria-labelledby="edit-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">Ubah akses</p><h2 class="panel-title mt-2" id="edit-title">{{ $editing->name }}</h2><p class="panel-subtitle">Perbarui detail akun yang dipilih.</p></div><x-icon name="edit" class="text-forest" /></div>
            <form method="POST" action="{{ route('users.update', $editing) }}">@csrf @method('PATCH')
                <div class="panel-body form-stack">
                    <x-field name="name" id="edit_name" label="Nama lengkap" :value="old('name', $editing->name)" :required="true" maxlength="255" autocomplete="name" />
                    <x-field name="email" id="edit_email" type="email" label="Alamat email" :value="old('email', $editing->email)" :required="true" maxlength="255" autocomplete="email" />
                    <div class="field"><label class="field-label" for="edit_role">Hak akses akun</label><select id="edit_role" name="role" data-custom-select class="field-input" required @if($errors->has('role')) aria-invalid="true" aria-describedby="edit_role-error" @endif><option value="user" @selected(old('role', $editing->role) === 'user')>Petani</option><option value="admin" @selected(old('role', $editing->role) === 'admin')>Administrator</option></select>@error('role')<p class="field-error" id="edit_role-error">{{ $message }}</p>@enderror</div>
                    <x-field name="password" id="edit_password" type="password" label="Atur ulang kata sandi" hint="Kosongkan jika tidak diganti. Minimal 8 karakter." minlength="8" autocomplete="new-password" />
                    <x-field name="admin_password" id="edit_admin_password" type="password" label="Konfirmasi kata sandi Anda" hint="Wajib saat mengubah email atau kata sandi akun. Sesi lama akun tersebut akan diakhiri." autocomplete="current-password" />
                </div>
                <div class="form-actions"><a class="button button--secondary" href="{{ route('users.index') }}">Batal</a><button class="button" type="submit">Simpan perubahan</button></div>
            </form>
        </section>
        @else

        <section id="create-account" class="panel form-section" aria-labelledby="create-title" data-reveal>
            <div class="panel-header"><div><p class="eyebrow">Anggota baru</p><h2 class="panel-title mt-2" id="create-title">Buat akun</h2><p class="panel-subtitle">Berikan akses ke ruang kerja Growvia.</p></div><span class="icon-tile"><x-icon name="users" /></span></div>
            <form method="POST" action="{{ route('users.store') }}">@csrf
                <div class="panel-body form-stack">
                    <x-field name="name" label="Nama lengkap" :value="old('name')" :required="true" maxlength="255" autocomplete="name" placeholder="Nama anggota" />
                    <x-field name="email" type="email" label="Alamat email" :value="old('email')" :required="true" maxlength="255" autocomplete="email" placeholder="nama@growvia.id" />
                    <x-field name="password" type="password" label="Kata sandi awal" hint="Minimal 8 karakter. Bagikan lewat saluran yang aman." :required="true" minlength="8" autocomplete="new-password" />
                    <div class="field"><label class="field-label" for="role">Hak akses akun</label><select name="role" id="role" data-custom-select class="field-input" required @if($errors->has('role')) aria-invalid="true" aria-describedby="role-error" @endif><option value="user" @selected(old('role') !== 'admin')>Petani</option><option value="admin" @selected(old('role') === 'admin')>Administrator</option></select><p class="field-hint">Petani dapat melihat kebun dan mengelola akunnya sendiri.</p>@error('role')<p class="field-error" id="role-error">{{ $message }}</p>@enderror</div>
                </div>
                <div class="form-actions"><button type="submit" class="button button--wide"><x-icon name="plus" />Buat akun</button></div>
            </form>
        </section>
        @endif
    </div>
</div>
<x-confirm-dialog />
@endsection
