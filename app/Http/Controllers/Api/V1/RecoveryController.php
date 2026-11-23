<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\AccountSessions;
use App\Support\MobileInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class RecoveryController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $input = MobileInput::validate($request, ['email' => ['required', 'string', 'email', 'max:255']]);
        AccountSessions::sendRecoveryLink($input['email']);

        return response()->json(['message' => 'Jika akun aktif terdaftar, tautan pemulihan akan dikirim. Periksa email dan folder spam.'])->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request): JsonResponse
    {
        $input = MobileInput::validate($request, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:4096'],
            'password' => ['required', 'string', 'min:8', 'max:4096', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'max:4096'],
        ]);

        if (AccountSessions::resetPassword($input) !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan tidak valid atau kedaluwarsa.']);
        }

        return response()->json(['message' => 'Kata sandi diganti, silakan masuk.'])->header('Cache-Control', 'no-store');
    }
}
