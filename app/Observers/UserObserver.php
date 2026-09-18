<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Support\AccountSessions;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserObserver
{
    public function created(User $user): void
    {
        Password::deleteToken($user);
    }

    public function updating(User $user): void
    {
        if ($user->isDirty(['password', 'email', 'role', 'is_active'])) {
            $user->auth_version = Str::random(40);
            $user->remember_token = Str::random(60);
        }
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('auth_version')) {
            AccountSessions::revokeArtifacts($user, $user->getOriginal('email'));
        }
    }

    public function deleted(User $user): void
    {
        AccountSessions::revokeArtifacts($user);
    }
}
