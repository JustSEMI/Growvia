<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileUserResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MobileMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => (new MobileUserResource($request->user()))->resolve($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): Response
    {
        MobileMutation::run($request, function (User $user, ?User $target, PersonalAccessToken $token): void {
            $token->delete();
        });

        try {
            ActivityLog::record('auth.logout', $request->user(), $request->user());
        } catch (\Throwable) {
            // Kegagalan pencatatan log tidak boleh membatalkan pencabutan token.
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
            'device_name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1f\x7f]/'],
        ]);

        // Objek penampung (stdClass), bukan variabel by-reference: arrow function
        // di luar mengikat variabel by-value sehingga reference akan terlepas.
        $state = new \stdClass;
        $state->failed = false;
        $state->attempted = null;

        $response = (new Timebox)->call(fn () => DB::transaction(function () use ($credentials, $request, $state): JsonResponse {
            $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();
            if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active || ! $user->hasRole('admin', 'user')) {
                $state->failed = true;
                $state->attempted = $user;

                return response()->json(['message' => 'Email atau kata sandi tidak sesuai.'], 401)->header('Cache-Control', 'no-store');
            }

            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make($credentials['password'])])->save();
            }

            $token = $user->createToken($credentials['device_name'], ['mobile:access'], now()->addMinutes(config('sanctum.expiration')));
            $token->accessToken->forceFill(['account_version' => $user->auth_version])->save();
            ActivityLog::record('auth.login', $user, $user);

            return response()->json([
                'token' => $token->plainTextToken,
                'user' => (new MobileUserResource($user))->resolve($request),
            ])->header('Cache-Control', 'no-store');
        }, attempts: 3), 200_000);

        if ($state->failed) {
            // Di luar transaksi agar jejak audit tetap tersimpan mandiri
            // dan tidak menghambat pengembalian respons 401.
            try {
                ActivityLog::recordRejected($state->attempted, $request->ip(), $request->userAgent());
            } catch (\Throwable) {
                // Kegagalan pencatatan log tidak boleh mengubah hasil login.
            }
        }

        return $response;
    }
}
