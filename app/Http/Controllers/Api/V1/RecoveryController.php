<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MobileInput;
use App\Support\MobileMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class RecoveryController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $input = MobileInput::validate($request, ['email' => ['required', 'string', 'email', 'max:255']]);
        $delivery = null;
        DB::transaction(function () use ($input, &$delivery): void {
            $delivery = null;
            $user = User::query()->where('email', $input['email'])->lockForUpdate()->first();
            Password::sendResetLink($input + ['is_active' => true, 'id' => $user?->id ?? 0], function (User $account, string $token) use (&$delivery): void {
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
        $status = MobileMutation::atomic(function () use ($input): string {
            $user = User::query()->where('email', $input['email'])->lockForUpdate()->first();

            return Password::reset($input + ['is_active' => true, 'id' => $user?->id ?? 0], function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                ActivityLog::record('account.password_reset', $user, $user);
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan tidak valid atau kedaluwarsa.']);
        }

        return response()->json(['message' => 'Kata sandi diganti — silakan masuk.'])->header('Cache-Control', 'no-store');
    }
}
