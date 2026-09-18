<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $delivery = null;
        DB::transaction(function () use ($request, &$delivery): void {
            $delivery = null;
            $user = User::query()->where('email', $request->input('email'))->lockForUpdate()->first();
            Password::sendResetLink($request->only('email') + ['is_active' => true, 'id' => $user?->id ?? 0], function ($account, string $token) use (&$delivery): void {
                $delivery = [$account, $token];
            });
        }, 3);
        if ($delivery !== null) {
            try {
                $delivery[0]->sendPasswordResetNotification($delivery[1]);
            } catch (\Throwable) {
                Log::warning('Pengiriman email pemulihan tidak tersedia.');
            }
        }

        return back()->with('status', 'Jika akun aktif terdaftar, tautan pemulihan akan dikirim. Periksa email dan folder spam.');
    }

    public function edit(Request $request, string $token): View
    {
        abort_if(! is_string($request->query('email', '')), 422, 'Alamat email tidak valid.');

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = DB::transaction(function () use ($request): string {
            $user = User::query()->where('email', $request->input('email'))->lockForUpdate()->first();

            return Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token') + ['is_active' => true, 'id' => $user?->id ?? 0],
                function ($user, $password): void {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                    ActivityLog::record('account.password_reset', $user, $user);
                }
            );
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Kata sandi diganti — silakan masuk.')
            : back()->withErrors(['email' => 'Tautan tidak valid atau kedaluwarsa.'])->onlyInput('email');
    }
}
