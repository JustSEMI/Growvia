<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

final class AccountSessions
{
    /** @return array{browser: string, device: string} */
    public static function describeAgent(string $agent): array
    {
        return [
            'browser' => match (true) {
                str_contains($agent, 'Edg/') => 'Microsoft Edge',
                str_contains($agent, 'OPR/') => 'Opera',
                str_contains($agent, 'Firefox/'), str_contains($agent, 'FxiOS/') => 'Firefox',
                str_contains($agent, 'Chrome/'), str_contains($agent, 'CriOS/') => 'Chrome',
                str_contains($agent, 'Safari/') => 'Safari',
                default => 'Browser tidak dikenali',
            },
            'device' => match (true) {
                str_contains($agent, 'Android') => 'Android',
                str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS / iPadOS',
                str_contains($agent, 'Windows') => 'Windows',
                str_contains($agent, 'Macintosh') => 'macOS',
                str_contains($agent, 'Linux') => 'Linux',
                default => 'Perangkat tidak dikenali',
            },
        ];
    }

    public static function sendRecoveryLink(string $email): void
    {
        $delivery = null;
        DB::transaction(function () use ($email, &$delivery): void {
            $delivery = null;
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            Password::sendResetLink(
                ['email' => $email, 'is_active' => true, 'id' => $user?->id ?? 0],
                function (User $account, string $token) use (&$delivery): void {
                    $delivery = [$account, $token];
                },
            );
        }, 3);

        if ($delivery !== null) {
            try {
                $delivery[0]->sendPasswordResetNotification($delivery[1]);
            } catch (\Throwable) {
                Log::warning('Pengiriman email pemulihan tidak tersedia.');
            }
        }
    }

    /** @param array<string, mixed> $credentials */
    public static function resetPassword(array $credentials): string
    {
        return MobileMutation::atomic(function () use ($credentials): string {
            $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();

            return Password::reset(
                $credentials + ['is_active' => true, 'id' => $user?->id ?? 0],
                function (User $user, string $password): void {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                    ActivityLog::record('account.password_reset', $user, $user);
                },
            );
        });
    }

    public static function revokeArtifacts(User $user, ?string $previousEmail = null): void
    {
        Password::deleteToken($user);
        if ($previousEmail && $previousEmail !== $user->email) {
            $previous = clone $user;
            $previous->email = $previousEmail;
            Password::deleteToken($previous);
        }
        $user->tokens()->delete();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())->delete();
        }
    }

    public static function keepCurrent(Request $request, User $user): void
    {
        $request->session()->regenerate(true);
        $request->session()->put('account_version', (string) $user->auth_version);
        Auth::guard('web')->setUser($user);
        Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));
    }
}
