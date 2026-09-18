<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $credentials['is_active'] = true;
        $intended = $this->safeIntended($request);
        $invalidCredentials = false;
        $attempted = null;

        try {
            DB::transaction(function () use ($request, $credentials, &$invalidCredentials, &$attempted): void {
                $invalidCredentials = false;
                $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();
                $attempted = $user;
                if (! Auth::attempt($credentials + ['id' => $user?->id ?? 0], $request->boolean('remember'))) {
                    $invalidCredentials = true;
                    throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
                }
                ActivityLog::record('auth.login', $request->user(), $request->user());
            }, 3);
        } catch (\Throwable $exception) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($invalidCredentials && $exception instanceof ValidationException) {
                // Dicatat di luar transaksi yang di-rollback: log audit percobaan
                // gagal hanya berguna jika tetap tersimpan saat transaksi gagal.
                $this->recordFailedAttempt($attempted, $request);
                if ($intended !== null) {
                    $request->session()->put('url.intended', $intended);
                }
            }
            throw $exception;
        }
        $request->session()->regenerate();

        $home = $request->user()->isAdmin()
            ? route('dashboard')
            : route('petani.dashboard');

        $request->session()->forget('url.intended');

        return redirect()->to($intended ?? $home);
    }

    /**
     * Audit trail for brute-force detection. Failure to record must never
     * change the login outcome, so it is swallowed and logged instead.
     *
     * The attempted password is never stored, and neither is the submitted
     * email address: an unknown address has no subject, which is why the
     * account is only attached when it actually exists.
     */
    private function recordFailedAttempt(?User $attempted, Request $request): void
    {
        try {
            ActivityLog::recordRejected($attempted, $request->ip(), $request->userAgent());
        } catch (\Throwable) {
            Log::warning('Pencatatan percobaan masuk gagal tidak tersedia.');
        }
    }

    private function safeIntended(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '' || preg_match('/[\x00-\x20\x7f]/', $intended) || str_contains($intended, '\\')) {
            return null;
        }

        if (str_starts_with($intended, '/') && ! str_starts_with($intended, '//')) {
            return $intended;
        }

        $parts = parse_url($intended);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return in_array($scheme, ['http', 'https'], true)
            && $scheme === $request->getScheme()
            && strtolower($parts['host'] ?? '') === strtolower($request->getHost())
            && $port === $request->getPort()
                ? $intended
                : null;
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            ActivityLog::record('auth.logout', $request->user(), $request->user());
        } catch (\Throwable) {
            Log::warning('Pencatatan aktivitas keluar tidak tersedia.');
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
