<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Header keamanan untuk halaman web; respons API diatur terpisah di EnsureApiTransport. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Dibagikan ke view agar inline bootstrap script memakai nonce tanpa 'unsafe-inline'.
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
        $hot = Vite::isRunningHot();
        $scriptSources = $styleSources = $connectSources = $fontSources = ["'self'"];
        if ($hot) {
            $origin = rtrim((string) config('app.url'), '/');
            $scriptSources[] = $styleSources[] = $connectSources[] = $origin;
            $scriptSources[] = 'ws:';
            $scriptSources[] = 'wss:';
            $connectSources[] = 'ws:';
            $connectSources[] = 'wss:';
            $scriptSources[] = 'http://127.0.0.1:*';
            $scriptSources[] = 'http://localhost:*';
            $styleSources[] = 'http://127.0.0.1:*';
            $styleSources[] = 'http://localhost:*';
            $connectSources[] = 'http://127.0.0.1:*';
            $connectSources[] = 'http://localhost:*';
            // Vite dev server menyajikan font dari origin loopback, bukan origin aplikasi.
            $fontSources[] = 'http://127.0.0.1:*';
            $fontSources[] = 'http://localhost:*';
        }

        $scriptSources[] = "'nonce-{$nonce}'";
        $scriptSources[] = "'strict-dynamic'";

        // Komponen masih memakai atribut style inline.
        $styleSources[] = "'unsafe-inline'";

        // Aktif hanya pada HTTPS agar redirect HTTP lokal tidak dipaksa ke https:// oleh Turbo Drive.
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
