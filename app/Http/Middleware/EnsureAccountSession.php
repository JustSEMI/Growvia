<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $version = $request->session()->get('account_version', '');
        if ($user && (! $user->is_active || ! is_string($version) || ! hash_equals((string) $user->auth_version, $version))) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.'], 401)
                : redirect()->route('login')->with('status', 'Sesi berakhir. Silakan masuk kembali atau hubungi administrator.');
        }

        if ($user && Auth::guard('web')->viaRemember()) {
            try {
                ActivityLog::record('auth.login', $user, $user);
            } catch (\Throwable $exception) {
                Auth::guard('web')->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                throw $exception;
            }
        }

        return $next($request);
    }
}
