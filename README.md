# Growvia — Greenhouse Monitor (PBL)

Panel monitoring greenhouse Device 1 (iklim) & Device 2 (tanah & air). Dua peran: **admin** (dashboard, pengaturan, pengguna) dan **petani/user** (panel kebun + edukasi).

## Jalan cepat

```sh
composer setup      # install + key + migrate
npm install
npm run build
php artisan serve   # http://127.0.0.1:8000
```

Login dibatasi 5x/menit. Lupa sandi: via tautan email (lokal: cek `laravel.log`, mailer `log`).

**Catatan penting:** `DatabaseSeeder` sekarang **kosong**, jadi `php artisan db:seed` **tidak membuat akun apa pun**. Untuk membuat akun admin pertama, jalankan:

```sh
php artisan tinker --execute 'App\Models\User::create(["name"=>"Admin","email"=>"admin@example.test","password"=>"kata-sandi-ku","role"=>"admin"]);'
```

Ganti email dan kata sandi sebelum dipakai. Jangan mengandalkan kredensial contoh dari dokumentasi lama.

## Struktur penting

- `routes/web.php` — semua rute (guard `role:admin` / `role:user,admin`).
- `app/Http/Controllers/` — `SettingsController`, `UsersController`, `Auth/*`.
- `resources/views/` — `layouts/{app,admin,petani}`, `components/{page-header,metric-tile}`, `petani/*`, `errors/*`.
- `database/migrations/` — users + `role`, tabel `settings` (key-value + default bawaan).
- Telemetri saat ini **empty state** — slot grafik/tabel/log siap diisi saat MQTT ingest jadi.

## Tes & build

File tes PHP ada di `tests/` di dalam folder aplikasi, sehingga project ini **self-contained**: salin folder `Growvia/` saja, dan tes tetap bisa dijalankan. `composer.json` (`autoload-dev`) dan `phpunit.xml` menunjuk ke `tests/`.

```sh
php vendor/bin/phpunit                      # tes PHP, langsung dari folder aplikasi
npm run test:ui                             # tes JavaScript di tests/Frontend
npm run build
```

`Tools/growvia-tests/laravel/run.py` masih berfungsi dan mengisolasi DB/cache/mail/storage ke folder temporer, tetapi ia **berada di luar** repo aplikasi dan tidak diperlukan untuk menjalankan tes.

Jangan menjalankan ulang `composer setup` atau seeder pada database kerja hanya untuk membersihkan cache; perintah tersebut dapat mengganti key atau data akun. Detail implementasi dan setup API mobile ada di `CONTEXT.md`.

Catatan Blade: jangan taruh `$arr['key']` di dalam `@php(...)` inline / atribut bound komponen (`:prop="..."`) — compiler rusak diam-diam. Hitung di route/controller, passing variabel polos.
