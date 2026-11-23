<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccountSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        AccountSessions::sendRecoveryLink($validated['email']);

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
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = AccountSessions::resetPassword($credentials);

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Kata sandi diganti, silakan masuk.')
            : back()->withErrors(['email' => 'Tautan tidak valid atau kedaluwarsa.'])->onlyInput('email');
    }
}
