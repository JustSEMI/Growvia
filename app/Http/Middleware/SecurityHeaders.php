<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline transport and content-security headers for server-rendered pages.
 *
 * API responses deliberately keep their own headers in EnsureApiTransport so
 * this middleware stays scoped to the web group and never double-sets them.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Dibuat per respons dan dibagikan ke layer view agar skrip bootstrap
        // inline dapat membawa nonce tanpa perlu 'unsafe-inline' pada seluruh halaman.
        $nonce = Str::random(40);
        Vite::useCspNonce($nonce);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        if (app()->environment('production') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', $this->policy($nonce, $request));

        return $response;
    }

    private function policy(string $nonce, Request $request): string
    {
        // Dev server Vite melayani aset dari origin tersendiri, sehingga
        // hot reload membutuhkan izin origin dev saat aktif.
        $hot = Vite::isRunningHot();
        $scriptSources = $styleSources = $connectSources = $fontSources = ["'self'"];
        if ($hot) {
            $origin = rtrim((string) config('app.url'), '/');
            foreach ([$origin] as $allowed) {
                $scriptSources[] = $allowed;
                $styleSources[] = $allowed;
                $connectSources[] = $allowed;
            }
            $scriptSources[] = 'ws:';
            $scriptSources[] = 'wss:';
            $connectSources[] = 'ws:';
            $connectSources[] = 'wss:';
            // Port dev server dinamis, izinkan rentang loopback lokal.
            $scriptSources[] = 'http://127.0.0.1:*';
            $scriptSources[] = 'http://localhost:*';
            $styleSources[] = 'http://127.0.0.1:*';
            $styleSources[] = 'http://localhost:*';
            $connectSources[] = 'http://127.0.0.1:*';
            $connectSources[] = 'http://localhost:*';
            // Plugin Vite menyajikan berkas font dari origin dev, bukan origin aplikasi.
            $fontSources[] = 'http://127.0.0.1:*';
            $fontSources[] = 'http://localhost:*';
        }

        $scriptSources[] = "'nonce-{$nonce}'";
        $scriptSources[] = "'strict-dynamic'";

        // Bootstrap tema wajib berjalan sebelum first paint (dicegah via nonce).
        // Atribut style inline masih digunakan komponen, tetap butuh 'unsafe-inline'.
        $styleSources[] = "'unsafe-inline'";

        // Direktif upgrade-insecure-requests memaksa browser menaikkan semua
        // subresource dan navigasi bawa halaman ke HTTPS. Jika halaman masih
        // disajikan lewat HTTP (contoh: dev tanpa TLS), redirect setelah login
        // ikut terangkat ke https:// yang tidak tersedia sehingga Turbo Drive
        // gagal menukar halaman. Aktif hanya saat koneksi memang sudah aman.
        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSources),
            'style-src '.implode(' ', $styleSources),
            'connect-src '.implode(' ', $connectSources),
            "img-src 'self' data:",
            'font-src '.implode(' ', $fontSources),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];
        if ($request->secure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', array_filter($directives));
    }
}
