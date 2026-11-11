# Growvia — Context untuk AI dan Developer

> Snapshot kode web dan integrasi mobile: **17 September 2026**. Versi, jumlah file, dan angka tes di dokumen ini diambil langsung dari `composer.lock`, `package-lock.json`, `route:list`, dan hasil runner pada tanggal tersebut. Dokumen ini menjelaskan implementasi yang benar-benar ada, bukan fitur yang direncanakan. Kalau berbeda dengan kode, kode dan hasil tes terbaru yang menang.
>
> **Audit ulang (18 September 2026).** Seluruh angka di dokumen ini dijalankan ulang. Hasil: tes PHP (**284 tes / 2.887 assertion**) dan JavaScript (**44 tes**) tetap akurat. Yang dikoreksi: `route:list` kini **50 entri** (27 web termasuk route tooling Boost, 20 API, 3 framework), migrasi **9** (bukan 8), middleware **7** (bukan 6) di `app/Http/Middleware/`, dan klien mobile kini **31 file Dart lib / 21 file tes** dengan **212 tes lulus**. Repo juga **punya metadata `.git`** dengan remote GitHub.

## 1. Pahami aplikasi dalam 30 detik

**Growvia adalah aplikasi pemantauan rumah kaca untuk PBL, dengan panel admin dan panel petani.** Tujuannya menyatukan informasi iklim, tanah, persediaan air, dan penyiraman dalam satu ruang kerja.

Implementasi sekarang adalah **monolit Laravel yang merender HTML lewat Blade**, bukan SPA, ditambah **klien Flutter terpisah** yang memakai REST API Laravel yang sama. Login, pencarian/filter/CRUD/status akun, log aktivitas, daftar/revokasi sesi, reautentikasi perubahan email, recovery yang diperketat, penyimpanan konfigurasi, halaman edukasi, dan API mobile `/api/v1` sudah berjalan. **MQTT, pembacaan sensor, grafik berbasis data, riwayat perangkat, notifikasi perangkat, dan kontrol pompa belum diimplementasikan.** Integrasi perangkat sengaja di luar cakupan.

Kalimat singkat untuk presentasi:

> "Growvia menyediakan workspace greenhouse untuk administrator dan petani. Administrator mengelola pengguna serta konfigurasi koneksi dan ambang lingkungan. Petani melihat kondisi kebun, target konfigurasi, dan panduan perawatan. Fondasi aplikasi web dan API mobile sudah berfungsi, tetapi integrasi perangkat masih tahap berikutnya: angka sensor dan status pompa belum tersedia dan tidak diganti dengan data palsu."

### Hal yang jangan salah dipahami

- Role database hanya `admin` dan `user`. **Petani adalah label UI untuk role `user`**, bukan role bernama `petani`.
- **Device 1** adalah area/domain iklim rumah kaca; **Device 2** adalah tanah dan air.
- Setting yang berhasil disimpan **tidak berarti broker tersambung, aturan diterapkan, atau pompa menyala**.
- Label "Kebun saya" belum berarti ada kepemilikan kebun per pengguna. Konfigurasi masih global; belum ada model greenhouse, tenant, atau pembagian perangkat per akun.
- Laravel Boost/MCP adalah tooling pengembangan, **bukan fitur AI untuk pengguna Growvia**.
- Tidak ada registrasi publik. Akun diberikan lewat admin. **`DatabaseSeeder` sekarang kosong**, jadi `php artisan db:seed` tidak membuat akun apa pun.
- Aplikasi belum layak dianggap production-hardened. Lihat bagian 12 sebelum membukanya ke internet.

### Jalur baca cepat

1. `AGENTS.md`: aturan kontribusi. Direktori `.ai/rules/` belum ada pada snapshot ini.
2. `routes/web.php`, `routes/api.php`, `bootstrap/app.php`, `bootstrap/providers.php`: pintu masuk dan pembatasan akses.
3. `app/Http/Controllers/`: alur akun, pengguna, konfigurasi, dan API.
4. `app/Support/`: boundary transaksi dan helper API mobile.
5. `app/Models/` dan `database/migrations/`: bentuk data.
6. `app/Providers/AppServiceProvider.php`: view composer, rate limiter, dan listener.
7. `resources/views/layouts/workspace.blade.php`, halaman target, lalu komponennya.
8. `resources/js/app.js`, `resources/css/app.css`, dan `tests/`.

`CONTEXT.md` melengkapi, bukan menggantikan, aturan kontribusi.

## 2. Status fitur: aktif, konfigurasi saja, atau belum ada

| Area | Kondisi implementasi sekarang | Rujukan utama |
|---|---|---|
| Login/logout web | Session auth, opsi remember, regenerasi sesi, invalidasi saat logout | `Auth/AuthenticatedSessionController` |
| Login API mobile | Sanctum bearer token, tanpa cookie/session fallback | `Api/V1/AuthController` |
| Lupa/reset password | Broker password Laravel; pengiriman mengikuti mailer environment | `Auth/PasswordResetController`, `Api/V1/RecoveryController` |
| Akses admin/petani | Middleware role di server; admin bisa membuka kedua panel | `routes/web.php`, `EnsureRole` |
| Kelola pengguna | Cari/filter/urut/pagination, CRUD, aktif/nonaktif; akun sendiri tidak dapat dihapus/dinonaktifkan/diturunkan | `UsersController`, `Api/V1/UsersController`, `pengguna.blade.php` |
| Akun sendiri | Profil/password, reautentikasi perubahan email, daftar sesi, keluar perangkat lain | `account-forms.blade.php`, `SessionController`, `AccountSessions` |
| Aktivitas aplikasi | Riwayat admin dengan filter nama/aksi/tanggal, snapshot pelaku/target, ringkasan lima terbaru | `ActivityLog`, `ActivityController`, `activity.blade.php` |
| Bahasa UI | Istilah dan validasi Indonesia; middleware memilih locale `id` | `lang/id`, `SetInterfaceLocale` |
| Tema tampilan | Terang/Gelap/Sistem, tersimpan per browser/origin | `theme.js`, `theme-bootstrap`, `theme-select` |
| Konfigurasi sistem | Admin menyimpan broker, interval, ambang, mode pompa, preferensi notifikasi | `SettingsController`, `MobileSettings` |
| Target pada halaman monitor | Membaca konfigurasi tersimpan/default dari database; bukan pengukuran | `AppServiceProvider` |
| Edukasi | Konten Blade statis dengan accordion native; bukan CMS | `petani/edukasi.blade.php` |
| Telemetri dan status node | Empty state eksplisit, nilai `—`, belum ada heartbeat | `metric-tile`, `node-status`, `connection-notice` |
| Grafik dan riwayat | Placeholder di web; klien mobile sudah bisa merender chart kalau `data` berisi | `empty-chart`, `Api/V1` readings |
| Kontrol pompa | Tombol manual disabled; belum ada endpoint perintah/ack | `dashboard.blade.php` |
| Notifikasi perangkat | Hanya preferensi tersimpan; belum ada pengiriman | `settings.blade.php` |
| Export CSV | **Ada di klien Flutter** (dari data termuat), **belum ada di web**. Baru berguna setelah `/readings` mengembalikan data | `Growvia Mobile/lib/data/history.dart` |
| Catatan perawatan kebun | Belum diimplementasikan | Belum ada alur/endpoint |

Halaman responsif di browser bukan aplikasi native. Klien Flutter ada di folder saudara `../Growvia Mobile`; lihat README proyek mobile untuk setup USB. Firmware MCU, desain PCB/casing, broker deployment, Docker, dan konfigurasi cloud tidak ada di folder ini.

## 3. Stack dan versi terverifikasi

Versi di bawah dibaca dari `composer.lock` dan `package-lock.json`, bukan dari rentang di `composer.json`.

| Bagian | Versi terpasang | Catatan |
|---|---|---|
| PHP lokal | 8.5.6 (ZTS, VC++ 2022 x64) | Runtime audit, bukan syarat mutlak |
| Laravel | **v13.31.0** | `composer.json` meminta `^13.17` |
| Laravel Sanctum | **v4.3.3** | Bearer token mobile |
| Laravel Tinker | v3.0.2 | Dev tooling |
| Laravel Boost | v2.9.0 | Dev tooling, bukan fitur pengguna |
| Laravel Pail | v1.2.7 | Log viewer dev |
| Laravel Pao | v1.1.5 | Output runner JSON |
| Laravel Pint | v1.32.1 | Formatter |
| PHPUnit | 12.5.35 | Suite ada di `tests/` |
| Collision | v8.9.5 | Error handler CLI |
| Database lokal | MySQL / SQLite | `DB_CONNECTION` di `.env` |
| Rendering | Blade + Eloquent | Tidak ada React/Vue/Livewire/Inertia/Alpine |
| CSS | Tailwind **4.3.3** + CSS komponen sendiri | Konfigurasi tema di `resources/css/app.css` |
| Build | Vite **8.3.0**, laravel-vite-plugin **3.2.0** | Entry di `vite.config.js` |
| Node lokal | **v26.8.1** | Vite minta `^20.19.0 \|\| >=22.12.0` |
| concurrently | 10.0.5 | Dev dependency |
| Frontend JS | Vanilla ES module | Tanpa framework, tanpa polling/MQTT |
| Font | Montserrat + Horizon | Lokal di `resources/fonts/`, disajikan lewat build Vite |

Minimum PHP untuk lock saat ini **8.4.1** (dependency Symfony), meski root menyatakan `^8.3`. Verifikasi lewat `vendor/composer/platform_check.php`.

Ekstensi PHP untuk aplikasi dan tes perlu dukungan PDO SQLite; tes DOM juga butuh DOM/XML. `composer.json` masih memakai nama paket skeleton `laravel/laravel`, dan `.env.example` masih `APP_NAME=Laravel` — kosmetik, bukan nama produk.

## 4. Peta direktori dan angka nyata

```text
Growvia/
  app/
    Http/Controllers/            4 controller konkret + base Controller
      Auth/                      2: AuthenticatedSessionController, PasswordResetController
      Api/V1/                    5: Auth, Account, Recovery, Users, Workspace
    Http/Middleware/             7: EnsureAccountSession, EnsureApiTransport,
                                    EnsureMobileApiAccess, EnsureRole,
                                    SecurityHeaders, SetInterfaceLocale,
                                    ThrottleMobileApiRequests
    Http/Resources/              3: MobileActivityResource, MobileDeviceResource,
                                    MobileUserResource
    Models/                      3: ActivityLog, Setting, User
    Observers/                   1: UserObserver
    Support/                     6: AccountMutation, AccountSessions, MobileInput,
                                    MobileMutation, MobileSessions, MobileSettings
    Providers/AppServiceProvider.php
  bootstrap/app.php              routing, middleware, exception JSON
  config/                        konfigurasi framework + sanctum.php + cors.php
  database/
    migrations/                  9 migrasi
    factories/                   UserFactory, ActivityLogFactory
    seeders/DatabaseSeeder.php   KOSONG — tidak membuat akun
  routes/web.php                 27 route aplikasi web (+1 route tooling Boost)
  routes/api.php                 20 route API v1
  routes/console.php             command contoh
  resources/
    views/                       47 Blade (27 halaman/partial + 20 komponen anonim)
    views/components/            20 komponen anonim
    views/vendor/pagination/     override pagination milik aplikasi
    js/                          5 modul: app, theme, custom-select, validation, notifications
    css/app.css                  token, komponen, responsive, motion
  tests/Frontend/                5 file *.test.mjs + browser.mjs (helper)
  MASTER.md                      arah visual antarmuka
```

**Tes PHP berada di dalam folder aplikasi** (`Growvia/tests/`), sehingga repo bisa dikloning dan dites tanpa folder saudara. `composer.json` (`autoload-dev`) dan `phpunit.xml` menunjuk ke `tests/`; `Growvia/tests/Frontend/` berisi tes JavaScript. Salinan arsip lama di `../Tools/growvia-tests/laravel/tests/` tidak lagi dipakai oleh aplikasi dan hanya berfungsi sebagai runner terisolasi.

`resources/views/vendor/` adalah source aplikasi. Jangan ikut mengabaikannya saat mengecualikan dependency `vendor/` di root. `.gitignore` mengabaikan `/vendor`, `/node_modules`, `/public/build`, `.env`, `.env.*` (kecuali `.env.example`), dan `*.log`.

## 5. Arsitektur dan alur request

### Web

```text
Browser
  -> public/index.php
  -> bootstrap/app.php
  -> middleware web (cookie, session, CSRF, SetInterfaceLocale, EnsureAccountSession)
  -> routes/web.php + guest/auth + role
  -> controller atau closure
  -> Eloquent User/Setting/ActivityLog
  -> Blade layout + komponen
  -> HTML + aset Vite
```

### API mobile

```text
Flutter
  -> HTTPS (atau HTTP loopback khusus debug)
  -> EnsureApiTransport (menolak HTTP non-loopback di luar local/testing)
  -> throttle:mobile-api (120/menit per IP, sebelum autentikasi)
  -> auth:sanctum + EnsureMobileApiAccess
  -> Api/V1 controller
  -> MobileInput (whitelist) -> MobileMutation/AccountMutation (transaksi) -> Eloquent
  -> JSON allowlist lewat Resource
```

- Form web memakai POST dan `@method('PATCH')`/`@method('DELETE')`; `@csrf` wajib dipertahankan.
- Mayoritas perubahan memakai pola **POST/PATCH/DELETE -> redirect -> GET**, dengan flash `status`, validation errors, dan `old()` input.
- Guard web bernama `web` (driver `session`, provider Eloquent). API mobile memakai Sanctum bearer-only. **`sanctum.stateful` sengaja dikosongkan** supaya cookie web tidak bisa menggantikan bearer token.
- `SanctumServiceProvider` didaftarkan eksplisit di `bootstrap/providers.php` (bersama `AppServiceProvider`) agar cache discovery lama tidak menghalangi guard.
- `EnsureRole` memakai perbandingan string strict. Menyembunyikan tombol bukan pengganti middleware.
- Dashboard dan halaman petani banyak memakai closure `view(...)` di route, bukan controller.
- `AppServiceProvider` memasang rate limiter, view composer (`dashboard`, `petani.Device 1`, `petani.Device 2`), composer aktivitas terbaru, listener snapshot sesi, canonical URL recovery, dan `Paginator::defaultView('vendor.pagination.growvia')`.
- `resources/js/app.js` **tidak** melakukan fetch/polling, subscribe MQTT, atau membuka WebSocket/SSE.
- Bootstrap membungkus respons error API: detail exception dibersihkan (termasuk saat debug), hanya `message`/`errors` yang diteruskan, header protokol dipertahankan, dan `Cache-Control: no-store, private` + `X-Content-Type-Options: nosniff` dipasang.

### Alur login dan pemulihan akun

1. Login web memvalidasi email/password, mensyaratkan akun aktif, dan mengunci baris akun dalam transaksi sebelum `Auth::attempt(...)`. Login berhasil dicatat tanpa credential; automatic rehash ikut mengubah versi sesi.
2. Login API membuat token Sanctum ber-scope `mobile:access` dengan snapshot `account_version`, lalu mencatat `auth.login`.
3. Listener `Login` menyimpan `account_version` ke sesi; `EnsureAccountSession` menolak sesi nonaktif atau versi lama.
4. Logout web menghapus autentikasi, invalidate session, regenerate CSRF. Logout API mencabut token yang dipakai. Kegagalan pencatatan aktivitas tidak boleh mempertahankan akses.
5. Request recovery memakai respons netral dan limiter IP/email independen. Penerbitan token dalam transaksi, pengiriman setelah commit, origin link memakai `app.url`.
6. Reset valid mengganti password, merotasi versi sesi dan remember token, membatalkan sesi/token lama, serta mencatat aktivitas dalam transaksi. Token terpakai ditolak.
7. Perubahan email/password sendiri dan logout perangkat lain mempertahankan sesi saat ini lewat ID/CSRF baru, atau merotasi token mobile. Sesi perangkat memakai versi hasil mutasinya sendiri; revokasi yang lebih baru tetap mengalahkannya.

### Alur kelola akun

- Daftar web memakai `q`, `role=admin|user`, `status=active|inactive`, `sort=newest|oldest|name_asc|name_desc`, pagination **10**. API mobile memakai filter yang sama dengan pagination **15**.
- Edit web dipilih lewat query `?edit=ID` tervalidasi; create dan edit tidak dirender bersamaan agar old input/error tidak salah sasaran.
- Admin tidak dapat menghapus, menonaktifkan, atau menurunkan hak akses dirinya sendiri. Akun nonaktif dapat diaktifkan kembali, tetapi sesi lamanya tidak pulih.
- Password edit kosong berarti tidak diganti. Password baru minimal 8 karakter. Perubahan email/password akun lain meminta password aktor (`admin_password`), bukan password target.
- Profil mengambil akun dari sesi, bukan ID payload; perubahan email memerlukan `profile_password`/`current_password`.
- `AccountMutation` membaca ulang aktor/target yang terkunci sebelum write; seluruh mutasi sukses dicatat lewat `ActivityLog::record()` dalam transaksi yang sama. No-op tidak membuat log palsu.

## 6. Peta route dan akses

`php artisan route:list` melaporkan **50 entri**: 27 route aplikasi web (26 route dalam `routes/web.php` + 1 route tooling Boost otomatis) + 20 route API v1 + 3 route framework.

> Route ke-27 adalah `POST /_boost/browser-logs` (`boost.browser-logs`) yang didaftarkan otomatis oleh paket Laravel Boost (dev tooling). Route ini **bukan endpoint produk** dan tidak memakai middleware `web`.

### Route web (26 di `routes/web.php`; 27 pada `route:list`)

| Metode | Path | Nama | Akses tambahan |
|---|---|---|---|
| GET | `/` | `home` | Semua; redirect sesuai role |
| GET | `/login` | `login` | guest |
| POST | `/login` | — | guest, `throttle:5,1` |
| GET | `/lupa-sandi` | `password.request` | guest |
| POST | `/lupa-sandi` | `password.email` | guest, `throttle:recovery` |
| GET | `/reset-sandi/{token}` | `password.reset` | guest |
| POST | `/reset-sandi` | `password.update` | guest, `throttle:reset-submit` |
| GET | `/pengaturan/sesi` | `sessions.index` | auth |
| DELETE | `/pengaturan/sesi` | `sessions.destroy` | auth, `throttle:reauthentication`, `throttle:6,1` |
| GET | `/aktivitas` | `activity.index` | auth, admin |
| POST | `/logout` | `logout` | auth |
| GET | `/dashboard` | `dashboard` | auth, admin |
| GET | `/pengaturan` | `settings.index` | auth, admin |
| PATCH | `/pengaturan/sistem` | `settings.system` | auth, admin |
| GET | `/pengguna` | `users.index` | auth, admin; opsional `?edit=ID` |
| POST | `/pengguna` | `users.store` | auth, admin |
| PATCH | `/pengguna/{user}/status` | `users.status` | auth, admin |
| PATCH | `/pengguna/{user}` | `users.update` | auth, admin, `throttle:reauthentication` |
| DELETE | `/pengguna/{user}` | `users.destroy` | auth, admin |
| PATCH | `/pengaturan/profil` | `settings.profile` | auth, user/admin, `throttle:reauthentication` |
| PATCH | `/pengaturan/kata-sandi` | `settings.password` | auth, user/admin, `throttle:reauthentication` |
| GET | `/petani` | `petani.dashboard` | auth, user/admin |
| GET | `/petani/iklim` | `petani.Device 1` | auth, user/admin |
| GET | `/petani/tanah` | `petani.Device 2` | auth, user/admin |
| GET | `/petani/pengaturan` | `petani.settings` | auth, user/admin |
| GET | `/petani/edukasi` | `petani.edukasi` | auth, user/admin |

### Route API `/api/v1` (20)

Grup `throttle:mobile-api` (120/menit per IP) berlaku untuk semuanya.

| Metode | Path | Nama | Akses |
|---|---|---|---|
| GET | `/api/v1/health` | `api.v1.health` | Publik |
| POST | `/api/v1/auth/login` | `api.v1.auth.login` | Publik, `throttle:mobile-login` |
| POST | `/api/v1/auth/forgot-password` | `api.v1.auth.forgot-password` | Publik, `throttle:recovery` |
| POST | `/api/v1/auth/reset-password` | `api.v1.auth.reset-password` | Publik, `throttle:reset-submit` |
| GET | `/api/v1/auth/me` | `api.v1.auth.me` | Bearer + `mobile:access` |
| POST | `/api/v1/auth/logout` | `api.v1.auth.logout` | Bearer |
| GET | `/api/v1/readings` | `api.v1.readings.index` | Bearer; `data: []`, `meta.available=false` |
| GET | `/api/v1/workspace` | `api.v1.workspace` | Bearer |
| GET | `/api/v1/account/sessions` | `api.v1.account.sessions` | Bearer |
| PATCH | `/api/v1/account/profile` | `api.v1.account.profile` | Bearer, `throttle:reauthentication` |
| PATCH | `/api/v1/account/password` | `api.v1.account.password` | Bearer, `throttle:reauthentication` |
| POST | `/api/v1/account/sessions/revoke-others` | `api.v1.account.revoke-others` | Bearer, `throttle:reauthentication` |
| GET | `/api/v1/users` | `api.v1.users.index` | Bearer + `role:admin` |
| POST | `/api/v1/users` | `api.v1.users.store` | Bearer + `role:admin` |
| PATCH | `/api/v1/users/{user}` | `api.v1.users.update` | Bearer + `role:admin`, `throttle:reauthentication` |
| PATCH | `/api/v1/users/{user}/status` | `api.v1.users.status` | Bearer + `role:admin` |
| DELETE | `/api/v1/users/{user}` | `api.v1.users.destroy` | Bearer + `role:admin` |
| GET | `/api/v1/settings` | `api.v1.settings` | Bearer + `role:admin` |
| PATCH | `/api/v1/settings` | `api.v1.settings.update` | Bearer + `role:admin` |
| GET | `/api/v1/activities` | `api.v1.activities` | Bearer + `role:admin` |

### Route framework (3)

| Metode | Path | Arti |
|---|---|---|
| GET | `/up` | Health aplikasi Laravel, bukan heartbeat node |
| GET | `/storage/{path}` | Handler file private; signature diperiksa di handler |
| PUT | `/storage/{path}` | Handler upload bertanda tangan; bukan API upload umum |

Catatan navigasi:

- Menu iklim/tanah admin menuju `/dashboard#Device 1` dan `/dashboard#Device 2`, bukan halaman admin terpisah.
- Halaman pengaturan petani adalah `/petani/pengaturan`, tetapi submit profil/password menuju `/pengaturan/...` yang mengizinkan kedua role.
- Fragment pengaturan admin: `#profile`, `#security`, `#mqtt`, `#climate`, `#soil`, `#notifications`.
- Redirect guest middleware sesuai role: petani yang sudah masuk lalu membuka `/login` kembali ke panel petani.
- Limiter IP API berjalan sebelum autentikasi sehingga bearer salah juga dibatasi. Tidak ada registrasi publik atau endpoint perintah pompa.

## 7. Data model dan migrasi

**9 migrasi**: users, cache, jobs, `add_role_to_users_table`, `create_settings_table`, `create_activity_logs_table`, `add_account_state_to_users_table`, `create_personal_access_tokens_table`, `add_request_context_to_activity_logs_table`.

### User

- Field: `id`, `name`, email unik, `email_verified_at`, hash password, remember token, timestamps, `role` (20, default `user`, indexed), `is_active` (boolean, default true, indexed), `auth_version` (string 40, nullable).
- Model memakai atribut PHP 8 `#[Fillable(['name','email','password','role'])]` dan `#[Hidden([...])]`. Password, remember token, dan `auth_version` tidak ikut serialisasi.
- Cast: `email_verified_at` datetime, `password` hashed, `is_active` boolean. Password tidak pernah disimpan plaintext.
- `isAdmin()`/`hasRole()` membandingkan identifier role asli.
- `UserObserver` merotasi `auth_version` + `remember_token` saat `password`/`email`/`role`/`is_active` berubah, dan memanggil `AccountSessions::revokeArtifacts()` pada `updated`/`deleted`.
- Kolom `email_verified_at` bukan bukti verifikasi mailbox aktif; route tidak memakai middleware `verified`. Tidak ada ownership kebun/tenant.

### ActivityLog

- Tabel `activity_logs`: `action` (64, indexed), `actor_id`/`subject_id` (FK nullable, null-on-delete), `actor_name`/`subject_name` (255), `ip_hash` (64, nullable), `user_agent` (255, nullable), `fields` JSON, `created_at` (indexed). Tidak ada `updated_at`.
- `ip_hash`/`user_agent` hanya terisi untuk `auth.failed` (migrasi `add_request_context_to_activity_logs_table`); `ip_hash` adalah SHA-256 yang diasinkan `APP_KEY`, bukan alamat IP asli. Lihat §12.
- `record()` mengizinkan aksi dan nama field yang dikenal; nilai isian, credential, session ID, token, dan payload request tidak disimpan.
- Riwayat admin berpaginasi 15, filter nama/aksi/tanggal; dashboard menampilkan lima terbaru. Timestamp ditulis eksplisit dengan zona aplikasi (UTC).
- Aksi: `user.created/updated/deleted/activated/deactivated`, `account.profile_updated/email_changed/password_changed/password_reset/sessions_revoked`, `auth.login/failed/logout`, `settings.updated`.
- `auth.failed` dicatat untuk login web **dan** API: satu baris per percobaan ditolak, `actor_id` selalu null, `subject_id` hanya terisi bila email benar-benar ada. Password yang dicoba dan alamat email tebakan tidak pernah disimpan, dan pencatatannya berada di luar transaksi agar tidak ikut ter-rollback saat login gagal.
- Tidak ada endpoint edit/hapus log atau migrasi data lama menjadi log rekaan.

### Setting

- Primary key `key` (string 64, non-incrementing); `value` text nullable; ada timestamps.
- Model menerima `key` dan `value`, tanpa cast numerik/boolean.
- Konfigurasi **global**, bukan per user atau per node.
- Default berasal dari konstanta `SettingsController::DEFAULTS` saat dibaca, bukan row yang otomatis dibuat migrasi.

### Tabel framework

- `password_reset_tokens`: email sebagai primary key, token hash, waktu pembuatan.
- `sessions`: ID sesi, `user_id` nullable/indexed, IP, user-agent, payload, last activity. Tanpa constraint cascade delete.
- `cache`, `cache_locks`: cache dan lock database.
- `jobs`, `job_batches`, `failed_jobs`: infrastruktur queue standar. Keberadaannya bukan bukti consumer MQTT sudah dibuat.
- `personal_access_tokens`: token Sanctum (hash, abilities, expiry, `last_used_at`) + kolom custom **`account_version`** (string 40, nullable) sebagai snapshot versi akun saat token dibuat.
- Token mobile dicabut oleh `AccountSessions::revokeArtifacts` saat credential/status/role/`auth_version` berubah atau akun dihapus. Token tidak ditulis ke log aktivitas.
- Laravel mengelola tabel `migrations` sebagai catatan migrasi.

### Filter tanggal aktivitas harus memakai `betweenDates()`

`ActivityLog::scopeBetweenDates()` membandingkan kolom `created_at` secara langsung, bukan lewat `whereDate()`. `whereDate()` membungkus kolom dengan fungsi SQL (`date(created_at) >= ?`) sehingga index `activity_logs_created_at_index` tidak terpakai dan query jatuh ke **full table scan**. Diverifikasi dengan `EXPLAIN QUERY PLAN` pada SQLite:

| Bentuk query | Rencana eksekusi |
|---|---|
| `where date(created_at) >= ?` | `SCAN activity_logs` |
| `where created_at >= ?` | `SEARCH activity_logs USING INDEX activity_logs_created_at_index` |

Batas `to` tetap inklusif sehari penuh: scope memakai `< tanggal+1 00:00:00 UTC`. Jangan mengembalikan `whereDate()`; ada dua tes yang mengunci bentuk SQL dan batas harinya di `ActivityLogTest`.

Belum ada tabel pembacaan sensor, registry node, heartbeat, command/ack pompa, atau histori notifikasi.

## 8. Kontrak konfigurasi

Sumber tunggal daftar default: `SettingsController::DEFAULTS`. **Seluruh 19 nilai default berbentuk string.** 15 di antaranya non-MQTT; 4 kunci MQTT (`mqtt_host`, `mqtt_port`, `mqtt_user`, `mqtt_topic`) sengaja **tidak diekspos ke API mobile**.

| Key | Default | Makna/unit | Validasi |
|---|---|---|---|
| `mqtt_host` | `''` | Host broker | Nullable, string, maks 255 |
| `mqtt_port` | `'1883'` | Port | Nullable, integer 1–65535 |
| `mqtt_user` | `''` | Username broker | Nullable, string, maks 255 |
| `mqtt_topic` | `'growvia/node'` | Topik dasar | Nullable, string, maks 255 |
| `update_interval` | `'10'` | Detik | Required, integer 5–300 |
| `th_suhu_min` / `th_suhu_max` | `'25'` / `'30'` | °C | Required numeric; min < max |
| `th_hum_min` / `th_hum_max` | `'60'` / `'80'` | % | Required numeric 0–100; min < max |
| `th_light_min` | `'20'` | klux | Required numeric ≥ 0 |
| `soil_on` / `soil_off` | `'30'` / `'60'` | % | Required numeric 0–100; on < off |
| `tank_min` | `'20'` | % | Required numeric 0–100 |
| `pump_max_run` | `'15'` | menit | Required integer 1–120 |
| `pump_mode` | `'auto'` | Konfigurasi | Required, `auto`/`manual` |
| `notif_pompa` | `'1'` | Preferensi | Boolean, disimpan `'0'`/`'1'` |
| `notif_cahaya` | `'1'` | Preferensi | Boolean |
| `notif_tandon` | `'1'` | Preferensi | Boolean |
| `notif_offline` | `'1'` | Preferensi | Boolean |

### Kosakata `section` berbeda antara web dan API

Ini penting dan mudah salah:

| | Nilai `section` |
|---|---|
| **Web** (`settings.blade.php`) | `mqtt`, `th1`, `th2`, `notif` |
| **API mobile** (`MobileSettings::SECTIONS`) | `general`, `climate`, `soil`, `notifications` |

- `general` → `update_interval`
- `climate` → `th_suhu_min/max`, `th_hum_min/max`, `th_light_min`
- `soil` → `soil_on`, `soil_off`, `tank_min`, `pump_max_run`, `pump_mode`
- `notifications` → empat kunci `notif_*`

Di **web**, `section` hanya penanda form; belum menjadi whitelist field per panel, sehingga payload tetap bisa menulis ulang konfigurasi global. Di **API mobile**, `section` benar-benar membatasi key yang boleh dikirim (`MobileSettings::validate` memakai `MobileInput::only`).

### Cara update konfigurasi

1. Boundary transaksi mengunci/memeriksa ulang aktor aktif; web memeriksa admin, API memakai `MobileMutation(admin: true)`.
2. Ambil default + setting tersimpan di dalam transaksi.
3. Validasi; flag notifikasi dinormalisasi ke `'0'`/`'1'`.
4. Bandingkan dengan nilai tersimpan; tulis hanya lewat `updateOrCreate`.
5. Catat hanya nama field yang berubah melalui `ActivityLog`. No-op tidak membuat aktivitas.
6. Commit write + log bersama, atau rollback seluruhnya.

Tidak ada `mqtt_password`, konfigurasi TLS/certificate, subscriber/publisher, schema payload, QoS, atau aturan retained-message. Jangan memperlakukan nama topik default sebagai kontrak firmware final. Nilai cahaya di UI memakai **klux**, bukan lux. Angka target adalah konfigurasi workspace, bukan rekomendasi universal untuk semua tanaman.

## 9. Frontend, komponen, dan perilaku yang harus dipertahankan

### Layout

- `layouts/app.blade.php`: shell login/recovery dengan area ilustrasi dan form.
- `layouts/workspace.blade.php`: shell sidebar, topbar, konten, footer, mobile navigation.
- `layouts/admin.blade.php` / `layouts/petani.blade.php`: satu baris, hanya meneruskan `panel` ke `workspace`.
- `components/document-head.blade.php`: metadata, favicon, `@fonts`, `@vite`.
- `errors/layout.blade.php`: shell error. View 400/401/403/404/419/429/500/503.

Arah visual di `MASTER.md`: netral hangat pada light, hijau-charcoal desaturasi pada dark; Montserrat untuk UI, Horizon untuk aksen; token semantik `light-dark()` dengan `color-scheme`. Breakpoint aktual `max-width: 1279px`, `1023px`, `767px`, `479px`; sidebar hilang pada 1023px ke bawah.

### Kontrak komponen (20 komponen anonim)

| Komponen | Peran |
|---|---|
| `page-header`, `brand`, `badge`, `icon` | Heading, identitas, status, SVG inline |
| `navigation`, `nav-link` | Menu per panel dan active state |
| `metric-tile` | Kartu nilai; default `—` dan keterangan menunggu sensor |
| `connection-notice`, `node-status`, `empty-chart` | Menjelaskan ketiadaan integrasi, bukan mensimulasikan kondisi |
| `field` | Label/input, helper/error, ID dan aksesibilitas; password tidak diisi ulang dari old input |
| `account-forms` | Form profil/password untuk admin dan petani |
| `alerts` | Flash status (toast + receipt inline) dan ringkasan error dengan anchor field |
| `notifications` | Region toast, live announcement, template tombol tutup |
| `confirm-dialog` | Konfirmasi hapus dan aktif/nonaktif akun |
| `activity-list` | Daftar ringkas aktivitas nyata, termasuk empty state |
| `garden-art` | Ilustrasi dekoratif; fill/stroke mengikuti token tema |
| `theme-bootstrap` | Script statis sinkron di head, menerapkan tema sebelum stylesheet |
| `theme-select` | Pilihan Sistem/Terang/Gelap dengan combobox custom |

`components/field.blade.php` sengaja **tidak** menulis `value` untuk `type="password"`, sehingga password tidak pernah di-echo balik lewat `old()`.

### Hook JavaScript

| Hook | Perilaku dan batasan |
|---|---|
| `data-password-toggle` + `aria-controls` | Toggle visibilitas password, kembalikan fokus ke input |
| `data-mobile-nav` | Native details/summary, Escape/outside click, sinkronisasi `aria-expanded` |
| `data-confirm-name`, `data-confirm-kind` | Nama sebagai data; jenis delete/activate/deactivate menentukan salinan |
| `data-confirm-dialog`, `-text`, `-cancel`, `-accept` | Dialog custom memakai `textContent`; tidak memakai `window.confirm` |
| `data-confirm-trigger`, `data-confirm-required` | Tombol hapus awalnya disabled; aktif setelah handler siap |
| `form[aria-busy="true"]`, `.is-submitting` | Guard submit ganda; pulih pada pageshow/focus/Escape atau fallback 8 detik |
| `data-error-summary` | Fokus ke ringkasan error server |
| `data-reveal`, `.reveal-in` | Entrance satu kali via IntersectionObserver; hormati reduced motion |
| `data-theme-select`, `data-theme-control` | Pilih tema; hidden sampai handler siap; tidak submit form |
| `data-custom-select`, `data-select-trigger` | Enhance select jadi combobox/listbox; nilai native tetap sumber payload |
| `data-client-error`, `data-client-summary` | Validasi browser ditampilkan inline, bukan popup native |
| `data-theme`, `data-theme-preference` pada root | Tema efektif light/dark dan pilihan system/light/dark |

Navigasi/filter/form non-destruktif tetap memakai HTML native. Dropdown tema, hak akses, status/urutan pengguna, jenis aktivitas, dan mode pompa memakai panel opsi custom setelah bundle siap. **Hapus dan perubahan status akun sengaja disabled tanpa JS.** Dialog hanya perlindungan UX; otorisasi dan validasi tetap di server.

Jangan mengembalikan handler `onclick`/`onsubmit` yang menyisipkan nama pengguna. Blade HTML escaping tidak sama dengan escaping untuk JavaScript. Pertahankan `data-*` yang di-escape dan `textContent`.

### Toast/notifikasi visual

- `notifications.js` di-import `app.js`. Flash sukses server otomatis menjadi toast; receipt inline tetap terlihat.
- API: `notify(message, { tone })` dengan tone `success`/`info`/`warning`/`error`. Semua teks lewat `textContent`.
- Success/info tutup otomatis 7 detik; timer berhenti saat hover, fokus, atau tab tidak terlihat. Warning/error tidak hilang otomatis.
- Maksimal 3 toast terlihat; sisanya diantrikan. Satu live region polite.
- Error validasi tetap di summary/field, **bukan** toast sementara.
- Tanpa JS, flash server tetap tampil inline. Tidak ada permintaan izin notifikasi OS/browser.

### Preferensi tema

- Default `system` mengikuti `prefers-color-scheme`; user bisa memilih `light`/`dark`. Label: Sistem, Terang, Gelap.
- Penyimpanan `localStorage['growvia.theme']`, bukan database. Berlaku per origin, bukan sinkronisasi akun.
- `theme-bootstrap` berjalan sebelum asset links; `theme.js` menangani kontrol, perubahan OS, event storage antartab, dan pageshow.
- `localhost:8000` dan `127.0.0.1:8000` adalah origin berbeda.
- LocalStorage yang gagal tidak boleh menggagalkan JavaScript lain.

### Gotcha yang sudah relevan

- Create/edit akun memakai nama input sama. Pertahankan pemisahan render dan prefix ID `edit_` agar old input/error tidak salah sasaran.
- Dialog close event dapat terlambat. Guard `confirmation.open` mencegah pending konfirmasi baru ikut terhapus.
- Warna `::backdrop` punya fallback literal jika token `--scrim` tidak diwariskan.
- `.field { align-content: start; }` menjaga input sejajar saat helper text beda panjang.
- `.table-wrap` memakai `position: relative` + overflow horizontal agar label screen-reader-only tidak melebar di mobile.
- Animasi nonaktif saat `prefers-reduced-motion: reduce`; jangan hapus dukungan ini.
- Ikuti pola Blade aman: siapkan data di controller/provider, gunakan variabel sederhana atau `data_get()` untuk binding kompleks. Jangan taruh ekspresi array di inline `@php(...)` atau atribut bound komponen.
- Render view target saat mengubah pola, jangan hanya menjalankan PHP lint.

## 10. Menjalankan aplikasi tanpa merusak environment

### Pemeriksaan awal

```sh
php -v
node --version
composer show --direct
composer check-platform-reqs
php artisan route:list
```

Periksa `.env` (43 kunci pada snapshot), dependency, `database/database.sqlite`, `public/build/manifest.json`, dan proses lokal. **Jangan mencetak rahasia `.env` atau dump record pengguna ke laporan AI.**

### Instalasi baru (hanya untuk salinan dengan database kosong)

1. `composer install` memakai lock.
2. `npm ci`. `.npmrc` mengatur `ignore-scripts=true`; jangan bypass tanpa memahami script paket.
3. Salin `.env.example` ke `.env` **hanya jika belum ada**, lalu sesuaikan nama aplikasi, URL, database, mailer.
4. `php artisan key:generate` hanya bila benar-benar belum ada APP_KEY. Jangan mengganti key environment lama.
5. Buat file SQLite bila belum ada, lalu `php artisan migrate` setelah memastikan koneksi menunjuk database pengembangan.
6. `npm run build`.
7. Seeder **kosong**; tidak ada akun demo otomatis. Buat akun admin lewat `php artisan tinker` atau seeder terpisah yang disetujui.

**Jangan memakai `composer setup` sebagai perintah pemeriksaan.** Script itu menginstal dependency, generate key, migrate `--force`, npm install, dan build — menjalankannya ulang dapat mengubah environment yang sudah bekerja.

`README.md` masih mencantumkan akun demo `admin@growvia.id` / `petani@growvia.id`, tetapi `DatabaseSeeder::run()` sekarang kosong. **Perlakukan tabel akun demo di README sebagai tidak akurat** sampai seeder diisi kembali.

### Development harian

Artefak percobaan, hasil tes, preview, cache audit, dan backup lokal ditempatkan di `D:/Kuliah/SEMESTER 3/PBL/Tools`, bukan di folder PBL atau source aplikasi.

```sh
# Terminal aplikasi
php artisan serve --host=127.0.0.1 --port=8000

# Terminal aset terpisah untuk hot reload
npm run dev

# Alternatif tanpa dev server Vite
npm run build
```

`composer run dev` mendelegasikan ke `php artisan dev`. Orkestrator itu bukan consumer MQTT. Jangan menumpuk server atau mematikan proses existing tanpa izin.

### Aset dan font

- Entry: `resources/css/app.css` dan `resources/js/app.js`.
- Hasil build: `public/build/manifest.json`, `public/build/fonts-manifest.json`, CSS/JS/font.
- Font lokal (Montserrat, Horizon) di `resources/fonts/`; build pertama tidak butuh jaringan. Cache font lama di `node_modules/.cache/laravel-vite-plugin/fonts` tidak lagi dipakai (provider `local`).
- `public/hot` membuat Laravel memakai URL dev Vite **meski manifest production ada**. Kalau tampilan tanpa CSS/JS, cek file ini dan keterjangkauan dev server lebih dulu.
- Jangan mengedit file hashed di `public/build/assets/`; perbaiki source lalu build.

### Catatan Windows

- PHP lokal dari Laragon; sesuaikan PATH per mesin.
- Git Bash: path dengan spasi harus dikutip.
- Jika wrapper Composer gagal menerjemahkan path, panggil PHAR lewat PHP dengan lokasi benar.
- Direktori ini **memiliki metadata `.git`** dengan remote `origin` di GitHub (`JustSEMI/Growvia`). Jangan mengarang commit/branch/remote — periksa `git log`, `git remote -v`, dan `git status` bila perlu, lalu laporkan apa adanya. Snapshot audit menemukan satu perubahan belum di-commit (`resources/views/components/icon.blade.php`).

### Konfigurasi lokal yang memengaruhi perilaku

- SQLite; session/cache/queue driver database; session lifetime 120 menit; mailer `log`; `APP_ENV=local`; debug aktif; session serialization JSON.
- `config/app.php` memakai `'timezone' => 'UTC'` literal, sedangkan `'locale' => env('APP_LOCALE', 'en')`. **Locale default file config adalah `en`**, tetapi UI dan validasi web berbahasa Indonesia karena `SetInterfaceLocale` memaksa `app()->setLocale('id')` dan `lang/id` menyediakan terjemahan. Jangan mengubah `config/app.php` untuk mengejar bahasa UI.
- Mailer `log` menulis email recovery ke log, **tidak mengirimnya ke mailbox**.
- Timezone UTC ditampilkan eksplisit pada riwayat aktivitas/sesi.

## 11. Testing dan hasil verifikasi

### Suite yang dipertahankan

**19 file PHP** relatif terhadap `tests/`:

| File | Fokus |
|---|---|
| `tests/Feature/Auth/LoginTest.php` | Form login, login kedua role, password salah, logout |
| `tests/Feature/Auth/PasswordResetTest.php` | Request notification, reset valid, custom 404 |
| `tests/Feature/RoleAccessTest.php` | Pemisahan akses dashboard dan halaman petani |
| `tests/Feature/UsersTest.php` | CRUD akun, reset oleh admin, larangan hapus/demote diri |
| `tests/Feature/SettingsTest.php` | Profil/password, konfigurasi, rentang, akses petani |
| `tests/Feature/InterfaceTest.php` | Navigasi, nama sebagai data, konfigurasi jujur, error anchor, create/edit, delete tanpa JS, tema dan urutan bootstrap |
| `tests/Feature/AccountManagementTest.php` | Pencarian, filter, urutan, status akun, bahasa, input malformed |
| `tests/Feature/AccountSecurityTest.php` | Reautentikasi, lifecycle recovery, limiter, privasi sesi |
| `tests/Feature/SessionSecurityTest.php` | Cookie jar berbeda, database session, remember, legacy, regenerasi ID/CSRF |
| `tests/Feature/AccountMutationTest.php` | Akun fresh/terkunci, versi stale, rollback, dua koneksi SQLite, retry |
| `tests/Feature/AccountMutationIntegrationTest.php` | Penolakan mutasi setelah revokasi, target stale, CSRF |
| `tests/Feature/ActivityLogTest.php` | Allowlist, snapshot, role |
| `tests/Feature/ActivityIntegrationTest.php` | Filter/pagination dan pencatatan mutasi nyata |
| `tests/Feature/ReviewRegressionTest.php` | Limiter lintas endpoint/IP, remembered login, kegagalan audit, tujuan login aman |
| `tests/Feature/MobileApiTest.php` | Login API, token, scope, revokasi, rate limit |
| `tests/Feature/MobileParityTest.php` | Perilaku parity yang dipakai klien mobile |
| `tests/Feature/MobileParityReviewTest.php` | Regression review parity (binding recovery, retry mutasi) |
| `tests/Feature/ExampleTest.php` | Redirect root untuk guest |
| `tests/TestCase.php` | Base test case |

**5 file JavaScript** di `tests/Frontend/`: `theme.test.mjs` (7 tes), `custom-select.test.mjs`, `validation.test.mjs`, `confirmation.test.mjs`, `notifications.test.mjs`. `browser.mjs` adalah helper, bukan file tes.

`phpunit.xml` mengatur SQLite `:memory:`, mail/cache/session array, queue sync, dan `BCRYPT_ROUNDS=4`. Tes feature memakai `RefreshDatabase` dan factory; **jangan menjalankannya dengan database kerja.**

### Menjalankan tes

```sh
# Tes PHP langsung dari folder aplikasi (self-contained)
php vendor/bin/phpunit
php vendor/bin/phpunit --filter=UsersTest

# Runner opsional di luar repo: mengisolasi DB/mail/cache/storage + APP_KEY acak
python "../Tools/growvia-tests/laravel/run.py"

# Tes state tema di Node, tanpa dependency test tambahan
npm run test:theme

# Tes UI lengkap memakai Chrome/Chromium headless terisolasi
# Atur CHROME_PATH bila executable tidak berada di lokasi umum
npm run test:ui

# Untuk CI/headless yang saling berebut Chrome
node --test --test-concurrency=1 tests/Frontend/*.test.mjs
```

`composer test` juga melakukan `config:clear`; tidak cocok untuk audit yang harus mempertahankan runtime. Untuk audit non-invasif, arahkan storage/view/bootstrap cache ke folder temporer lewat `LARAVEL_STORAGE_PATH`, `VIEW_COMPILED_PATH`, dan `APP_CONFIG_CACHE`/`APP_PACKAGES_CACHE`/`APP_SERVICES_CACHE`/`APP_ROUTES_CACHE`/`APP_EVENTS_CACHE`. Di Windows, gunakan path relatif dari root aplikasi menuju `../Tools/<nama-audit>/cache/...`.

### Hasil yang dijalankan pada 17 September 2026

- **PHP: 284 tes / 2.887 assertion lulus** — **diverifikasi ulang** pada audit 18 September 2026 dengan `php vendor/bin/phpunit` dari folder aplikasi, storage/view cache diarahkan ke `Tools/` agar tidak menyentuh runtime kerja.
- **JavaScript: 44 tes lulus** — **diverifikasi ulang** (`node --test --test-concurrency=1 tests/Frontend/*.test.mjs`); `test:theme` sendiri 7 tes.
- **Flutter: 212 tes lulus, analyzer bersih** — diperbarui dari 180 pada audit 18 September 2026 (`flutter test` + `flutter analyze` di proyek `../Growvia Mobile`).
- `route:list`: **50 entri** (27 web — 26 aplikasi + 1 tooling Boost — + 20 API + 3 framework).
- File: 47 Blade (20 komponen), **9 migrasi**, 5 modul JS, 3 file `lang/id`.
- Klien mobile: **31 file Dart** di `lib/`, **21 file tes** di `test/`.
- Database kerja: `database/database.sqlite`, integrity ok, 8 migrasi Ran.

### Batas pengujian

- Coverage kode, pembaca layar fisik, browser versi lama, SMTP nyata, dan database selain SQLite **tidak** diuji.
- Tes Laravel biasanya melewati CSRF saat testing; tes khusus harus menonaktifkan bypass itu. Reset state guard, singleton `auth.driver`, session, queued-cookie, dan redirector saat memakai banyak cookie jar.
- Tes unit/widget mobile memakai transport fixture terisolasi dan **tidak menggantikan** uji jaringan nyata.
- Hasil tes bukan jaminan production-ready atau integrasi perangkat.

## 12. Keamanan: yang sudah diterapkan dan batasnya

### Sudah diterapkan

- Akun nonaktif ditolak saat login dan saat sesi dibaca. `EnsureAccountSession` membandingkan `account_version` sesi dengan `users.auth_version`; prioritas eksplisit menempatkannya setelah `StartSession` dan sebelum autentikasi/throttle/binding.
- `EnsureMobileApiAccess` memvalidasi token: ada, akun aktif, role `admin`/`user`, `account_version` cocok, dan token masih ada di database.
- `UserObserver` merotasi `auth_version` + remember token pada perubahan password/email/hak akses/status; sesi database dan token recovery dicabut. Versi nullable menerima sesi legacy hanya sebelum revokasi pertama. Mengaktifkan akun kembali tidak memulihkan sesi lama.
- `AccountMutation::run()` menangkap identitas/versi aktor dan target sebelum transaksi, membaca ulang akun dalam urutan ID (mencegah deadlock), lalu menolak aktor/target yang berubah.
- `MobileMutation::run()` memverifikasi hash token, `account_version`, expiry, scope, dan role; `MobileMutation::atomic()` me-rollback kedua koneksi lalu me-retry maksimal 3 kali **hanya** untuk concurrency error PDO. Validasi dan exception lain tidak di-retry.
- SQLite memakai `IMMEDIATE` dan busy timeout 5000 ms. `lockForUpdate()` saja tidak mengunci SQLite.
- Perubahan recovery email sendiri serta perubahan email/password oleh admin meminta kata sandi aktor. Field reautentikasi tidak di-flash ke sesi dan tidak di-trim. Empat endpoint pemeriksaan password berbagi limiter 6 request/menit per akun dan per IP; reset-submit memakai bucket terpisah 5 request/menit per IP/email.
- `EnsureApiTransport` menolak HTTP non-loopback di luar `local`/`testing` dengan status 426. CORS hanya mengizinkan origin `localhost`/`127.0.0.1` pada environment local/testing, `supports_credentials: false`.
- Cookie sesi memakai `secure` yang **mengikuti environment**: `SESSION_SECURE_COOKIE` default `APP_ENV === 'production'`. Lokal tetap bisa HTTP, produksi otomatis HTTPS-only. `http_only` dan `same_site=lax` selalu aktif. Override eksplisit selalu menang.
- `config/trustedproxy.php` hanya mempercayai `TRUSTED_PROXIES` (daftar IP/CIDR eksplisit; default kosong → `null`, jadi fallback Laravel utuh). Wildcard sengaja tidak disediakan: tanpa daftar tersebut `X-Forwarded-*` diabaikan sehingga `$request->secure()` tidak bisa dipalsukan. Konsekuensinya, di belakang TLS terminator yang belum didaftarkan, API akan menolak semua request dengan 426 sampai `TRUSTED_PROXIES` diisi.
- `SecurityHeaders` (grup web) memasang CSP dan HSTS. CSP memakai **nonce per respons** yang dibagikan ke view lewat `$cspNonce`, sehingga skrip bootstrap tema tetap bisa inline tanpa `unsafe-inline` pada `script-src`; `style-src` masih perlu `unsafe-inline` karena beberapa komponen memakai atribut `style`. HSTS hanya dikirim saat produksi **dan** request terdeteksi HTTPS. Saat Vite dev server menyala, kebijakan dilonggarkan ke origin dev + `ws:`/`wss:` supaya hot reload tidak rusak.
- `activity_logs` menyimpan `ip_hash` dan `user_agent`, tetapi **hanya** untuk `auth.failed` (lewati `ActivityLog::recordRejected()`). IP disimpan sebagai hash SHA-256 yang diasinkan `APP_KEY`, bukan alamat aslinya: tetap bisa mengelompokkan percobaan berulang dari satu sumber tanpa menyimpan data pribadi. Aksi lain tidak membawa konteks request sama sekali.
- Mutasi admin di web (`settings.system`, `users.store/status/destroy`) berbagi limiter `throttle:reauthentication` yang sama dengan `users.update`, jadi admin tidak bisa membuat/menonaktifkan/menghapus akun lebih cepat daripada ia bisa mengonfirmasi sandinya sendiri.
- `AppServiceProvider::boot()` menggagalkan request **web** saat `APP_KEY` kosong dengan pesan `php artisan key:generate`; konsol dikecualikan agar `key:generate` dan hook `package:discover` milik Composer tetap berjalan di checkout baru.
- `public/.htaccess` memasang header keamanan baseline untuk halaman HTML: `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: SAMEORIGIN`, dan `Permissions-Policy`. API memasang header sendiri lewat `EnsureApiTransport`; blok `.htaccess` hanya berlaku bila dilayani Apache, **tidak** oleh `php artisan serve`.
- `public/robots.txt` menolak crawler untuk `/api/`, `/pengaturan/`, `/pengguna/`, `/aktivitas/`, `/dashboard`, `/petani/`, dan rute pemulihan sandi.
- `MobileInput::only()` menolak field tak dikenal, jadi payload berlebih tidak masuk validasi.
- Respons error API membersihkan detail exception, hanya meneruskan `message`/`errors`, dan mempertahankan header protokol seperti `Retry-After`/`WWW-Authenticate`.
- Klien Flutter membatasi HTTP ke `127.0.0.1`, `localhost`, `10.0.2.2` dan hanya pada build debug; release/profile wajib HTTPS. Validasi endpoint menolak userinfo, query, fragmen, traversal, dan host ter-encode.
- Log aktivitas memakai allowlist aksi/nama field; bukan password, nilai email, token, cookie, atau payload.
- Flag notifikasi divalidasi boolean dan disimpan `'0'`/`'1'`; perubahan konfigurasi atomik.
- Redirect guest middleware sesuai role, query edit divalidasi, old input bertipe array tidak merusak field, dan query email array pada halaman recovery ditolak.

### Masih perlu sebelum deployment publik

- Verifikasi kepemilikan mailbox baru dan pemberitahuan ke alamat lama belum dibuat; reautentikasi bukan bukti kepemilikan mailbox.
- Mailer lokal masih `log`. Jangan mengklaim email terkirim atau menguji kirim ke akun nyata tanpa izin.
- Deploy harus menonaktifkan debug, memakai TLS, document root `public/`, trusted host benar, security headers, backup/restore, dan retensi log. Cookie policy sudah otomatis mengikuti environment, dan trusted proxy tinggal diisi `TRUSTED_PROXIES` bila ada load balancer.
- **Bulk SQL, `query()->update()/delete()`, dan `saveQuietly()` melewati observer.** Jalur sensitif harus memakai helper/model event; jangan menambah bypass.
- Di **web**, `section` belum membatasi whitelist key per panel (semua key tetap tervalidasi dan disimpan atomik). Di **API mobile**, pembatasan itu sudah ada.
- Kosakata `section` web dan mobile berbeda (`mqtt/th1/th2/notif` vs `general/climate/soil/notifications`). Jangan menganggapnya bisa dipertukarkan.
- Log aplikasi bukan arsip tamper-proof, pusat notifikasi, atau histori perangkat.
- Header keamanan di `public/.htaccess` hanya aktif bila web server-nya Apache. Deployment dengan Nginx, Caddy, atau `php artisan serve` harus memasang header ekuivalen di lapisan server.
- `robots.txt` **bukan kontrol keamanan**; ia hanya permintaan sopan ke crawler yang patuh. Otorisasi tetap bergantung pada middleware dan session.

Kontrol yang harus dipertahankan: parameterisasi Eloquent, whitelist input, role middleware, CSRF, generasi sesi, password hashing, dan escaping Blade/`textContent`. Tes cross-site mengaktifkan middleware CSRF sebenarnya, bukan mengandalkan bypass environment testing.

## 13. Klien mobile (`../Growvia Mobile`)

Klien Flutter terpisah; lihat README proyek mobile untuk detail setup. Ringkas:

- **31 file Dart** di `lib/`, **21 file tes** di `test/` (**212 tes lulus**, analyzer bersih). Lapisan: `core/` (controller, theme, preferences, tokens), `data/` (api_client, api_endpoint, model, history, reading, workspace_models), `features/` (16 halaman), `ui/` (komponen + chart). Angka ini diperbarui pada audit 18 September 2026; snapshot lama mencatat 26/19.
- `AppController` memakai counter `generation` + tiket `_Access` supaya respons async dari akun lama tidak pernah ditulis ke state akun baru. Semua operasi secure storage diserialkan lewat `_store()`.
- Halaman khusus admin: pengguna, log aktivitas, pengaturan sistem. Semua role: profil, sesi, dashboard, riwayat, panduan, detail node.
- `ApiEndpoint` adalah satu sumber kebijakan transport untuk preferensi, validasi, dan parsing tautan recovery.
- Parsing tautan recovery hanya membaca; **tidak pernah melakukan request ke URL yang ditempel**.
- Perbedaan yang disengaja dari web: pagination 15 (web 10), tidak ada kunci MQTT di pengaturan, dan ekspor CSV tersedia di mobile.
- **Dropdown wajib memakai `ManagementSelector`**, bukan `DropdownButtonFormField` bawaan Material. Selector custom memakai bottom sheet, punya target sentuh lebih besar, dan konsisten dengan halaman pengelolaan lain. Bawaan Material hanya boleh dipakai bila ada alasan kuat; saat ini tidak ada.
- **Label metric wajib lengkap.** `Reading.label` harus menerjemahkan seluruh metric yang dirender UI: `temperature`, `humidity`, `light`, `co2`, `soil_moisture`, `soil_temperature`, `water_level`, `pump`. Metric tak dikenal jatuh ke identifier apa adanya (bukan tebakan). Dikunci oleh `test/reading_label_test.dart`.
- **Ikon Android memakai adaptive icon.** Foreground adalah vektor `drawable/ic_launcher_foreground.xml` yang diturunkan dari `Growvia/public/favicon.svg` oleh `Tools/growvia-mobile/adaptive_icon_path.py`. Path disimpan dalam ruang koordinat sumber 32 unit, dan transformasinya (scale 2.25, translate 18) diterapkan lewat `<group>` supaya geometrinya tetap bisa dilacak ke SVG asal. Script memverifikasi konversi relatif→absolut dan kecocokan safe zone; jalankan ulang dan periksa `relative_to_absolute_delta` serta `fits_safe_zone` bila favicon berubah.
- **Decode/legacy icon harus lengkap per density.** Manifest memakai `android:roundIcon="@mipmap/ic_launcher_round"`, dan folder `mipmap-anydpi-v26` hanya dibaca API 26+. Karena `minSdk` = 24, setiap folder density (`mdpi`–`xxxhdpi`) **wajib** punya `ic_launcher.png` **dan** `ic_launcher_round.png`. `Tools/growvia-mobile/branding_icons.py` adalah generator kanonik untuk keduanya; jangan menambah PNG secara manual agar tidak ter-drift.
- **ID aplikasi final dan seragam: `com.growvia.mobile`** (audit 18 September 2026). Sebelumnya berbeda antar platform (Android `com.growvia.growvia_mobile`, iOS `com.growvia.growviaMobile`). Mengubah nilai ini setelah rilis store tidak dimungkinkan. Nama paket Dart tetap `growvia_mobile` (`package:growvia_mobile/...`) — itu identitas pubspec, bukan ID aplikasi, dan tidak ikut berubah.
- **Rilis Android memakai `android/key.properties`** (git-ignored; template di `android/key.properties.example`). Bila berkas itu tidak ada, build rilis jatuh ke debug signing agar tetap bisa dijalankan lokal — hasil semacam itu **tidak boleh diunggah**. Verifikasi dengan `apksigner verify --print-certs` (harus bukan `CN=Android Debug`). Prosedur lengkap ada di README proyek mobile.
- **Folder `../Growvia Mobile` belum punya repo Git** (web Growvia sudah, dengan remote GitHub). Kode mobile karenanya tanpa version control; jangan mengarang riwayat commit/remote untuk folder ini bila tidak diminta.

## 14. Melanjutkan pengembangan

### Peta perubahan

| Tujuan | Mulai dari | Tes minimum |
|---|---|---|
| Login/redirect/session | Auth controller, `bootstrap/app.php`, `routes/web.php` | LoginTest, RoleAccessTest, sesi multi-device |
| Recovery/credential lifecycle | PasswordResetController, SettingsController, UsersController | PasswordResetTest + invalidasi token/sesi |
| CRUD pengguna | UsersController, `Api/V1/UsersController`, `pengguna.blade.php` | UsersTest, MobileParityTest, InterfaceTest |
| Tambah setting/validasi | `DEFAULTS`, `updateSystem`, `MobileSettings`, `settings.blade.php`, view composer | SettingsTest, MobileParityTest |
| Perubahan API mobile | `routes/api.php`, `Api/V1/*`, `Support/Mobile*` | MobileApiTest, MobileParityTest, MobileParityReviewTest |
| Isi dashboard dengan telemetri | Alur ingest/query baru, route/view monitor, komponen metric/chart | Validasi payload, persist, otorisasi, stale/no-data |
| Layout/warna/interaksi | Layout bersama, CSS, JS, komponen | Render halaman, kedua role, light/dark, keyboard, no-JS |
| Preferensi tema | theme-bootstrap, theme-select, theme.js, token CSS | test:theme, InterfaceTest, prepaint, persistence |
| Bahasa/tanggal | `lang/id`, pesan validator, view | Pesan error dan timezone konsisten |

### Urutan yang disarankan (belum implementasi)

1. Lengkapi verifikasi mailbox, kebijakan retensi/backup, dan hardening deployment.
2. Samakan kosakata `section` web dengan `MobileSettings::SECTIONS`, atau dokumentasikan pemetaannya secara eksplisit di kode.
3. Integrasi MQTT/perangkat menunggu persetujuan berikutnya; jangan melanjutkan otomatis.
4. Sepakati kontrak data, lalu buat satu alur sensor nyata dengan heartbeat dan status fresh/stale/offline.
5. Isi grafik/tabel dari data yang sama, lalu filter waktu dan CSV di web. Ekspor CSV baru masuk akal setelah `/readings` benar-benar mengembalikan data; klien mobile sudah punya `readingsToCsv()` sebagai rujukan format.
6. Command pompa memerlukan acknowledgement, timeout, hak akses, audit perintah, dan pengaman firmware.

## 15. Checklist untuk AI/developer berikutnya

- Mulai dari status fitur, route, controller target, dan tes — bukan dari dugaan berdasarkan screenshot.
- Baca aturan repository; dokumen ini tidak menggantikan arahan pemilik terbaru.
- Untuk permintaan analisis/dokumentasi, jangan sekaligus refactor, install/update dependency, re-seed, regenerate key, migrate database kerja, restart, commit/push, atau deploy.
- Konfirmasikan perubahan source aplikasi dengan pemilik sebelum mengeksekusinya.
- Jangan menambahkan mock data yang tampak live, state pompa palsu, atau protokol perangkat rekaan.
- Jangan menyisipkan credential, APP_KEY, isi sesi, token reset, atau data pengguna ke dokumen/prompt.
- Pertahankan nama route, string role, key setting, dan unit secara literal kecuali sedang melakukan migrasi yang disetujui.
- Gunakan komponen existing dan named routes. Ikuti konvensi sekitar file; komentar hanya untuk alasan/gotcha penting.
- Tambahkan regression test saat mengubah perilaku; hasil tes tidak menggantikan pemeriksaan interaksi browser untuk perubahan UI.
- Bedakan bukti baru, hasil audit sebelumnya, hipotesis, dan fitur rencana.
- Perbarui `CONTEXT.md` ketika route, skema, default, status integrasi, setup, atau risiko utama berubah. Cantumkan ulang tanggal verifikasi dan angka tes yang benar-benar dijalankan.

### Prompt onboarding singkat

```text
Baca CONTEXT.md dan AGENTS.md di root Growvia, lalu source serta tes yang relevan.
Growvia adalah Laravel server-rendered greenhouse workspace dengan role admin/user,
ditambah REST API /api/v1 (Sanctum bearer) untuk klien Flutter di ../Growvia Mobile.
Auth, CRUD akun, konfigurasi, log aktivitas, dan API mobile sudah ada.
Telemetri/MQTT/pompa belum aktif; seeder kosong; tes PHP ada di `tests/` (self-contained).
Jangan menganggap placeholder sebagai data nyata atau setting sebagai state perangkat.
Verifikasi kondisi codebase terbaru, jelaskan scope perubahan, dan minta persetujuan
sebelum mengedit source aplikasi. Jangan menyentuh database kerja untuk pengujian.
```

## 16. Catatan audit terakhir

### Sudah diselesaikan (17 September 2026)

- `README.md` sudah tidak lagi mencantumkan akun demo; diganti instruksi membuat admin pertama lewat `tinker`.
- `.env.example` memakai `APP_NAME=Growvia`, bukan `Laravel`.
- Label metric mobile lengkap untuk 8 metric yang dirender UI.
- Dropdown mobile diseragamkan ke `ManagementSelector`.
- Filter tanggal aktivitas memakai `betweenDates()` sehingga index `created_at` terpakai.
- `public/robots.txt` dan header keamanan `public/.htaccess` sudah diisi.
- Adaptive icon Android (termasuk `roundIcon` dan `monochrome`) sudah dibuat dari favicon.

### Diverifikasi ulang pada audit 18 September 2026

- **284 tes PHP / 2.887 assertion** dan **44 tes JavaScript** dijalankan ulang di environment terisolasi (`Tools/growvia-audit-deepseek/`, storage & view cache di luar repo) — keduanya lulus, tidak menyentuh database kerja.
- **212 tes Flutter lulus** (`flutter test`) dan `flutter analyze` bersih di `../Growvia Mobile`; angka pada snapshot lama (180) sudah kedaluwarsa.
- `route:list` = 50 entri; migrasi = 9; middleware = 7; git metadata & remote GitHub terkonfirmasi.

### Sebelum membuat integrasi MQTT/telemetri/firmware, sepakati dulu

- Daftar sensor/aktuator dan firmware yang dipakai; jangan menebak dari label UI.
- Identitas node, ownership/tenant bila perlu, topik publish/subscribe, versi payload.
- Satuan, kalibrasi, rentang masuk akal, timestamp UTC, interval aktual, penanganan duplikat/out-of-order, dan retensi.
- Auth broker/TLS/ACL serta lokasi penyimpanan credential; jangan mengekspos credential broker ke browser.
- Mekanisme status perangkat dan acknowledgement command. "Perintah terkirim" harus berbeda dari "pompa terkonfirmasi menyala".
- Perilaku saat internet/sensor gagal, tandon kosong, durasi maksimum terlampaui, konflik manual/otomatis. Fail-safe perangkat tidak boleh hanya mengandalkan dashboard.
- Mekanisme update UI (polling/SSE/WebSocket) belum dipilih. Jangan menambah library hanya karena template grafik kosong.
