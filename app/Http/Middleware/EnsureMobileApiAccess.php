<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        if (! $user instanceof User || ! $token instanceof PersonalAccessToken || ! $request->bearerToken()) {
            throw new AuthenticationException;
        }

        $fresh = User::query()->find($user->getKey());
        if (! $fresh || ! $fresh->is_active || ! $fresh->hasRole('admin', 'user')
            || ! hash_equals((string) $token->account_version, (string) $fresh->auth_version)
            || ! $fresh->tokens()->whereKey($token->getKey())->exists()) {
            throw new AuthenticationException;
        }
        abort_unless($token->can('mobile:access'), 403, 'Token tidak memiliki akses aplikasi mobile.');

        $fresh->withAccessToken($token);
        $request->setUserResolver(static fn (?string $guard = null): User => $fresh);

        return $next($request);
    }
}
