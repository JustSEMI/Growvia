<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

final class AccountSessions
{
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
