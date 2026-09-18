<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountMutation
{
    /**
     * Mutate accounts using fresh rows, without changing the request or session.
     *
     * Keep irreversible side effects outside the retryable callback. Callers remain
     * responsible for authorization, input validation, and keeping their session.
     *
     * @template TReturn
     *
     * @param  Closure(User, ?User): TReturn  $callback
     * @return TReturn
     *
     * @throws AuthenticationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public static function run(Request $request, Closure $callback, ?User $target = null): mixed
    {
        $requestActor = $request->user();
        if (! $requestActor instanceof User || $requestActor->getKey() === null) {
            throw new AuthenticationException(guards: ['web']);
        }

        $actorId = $requestActor->getKey();
        $expectedActorVersion = (string) $requestActor->auth_version;
        $targetId = $target?->getKey();
        $expectedTargetVersion = (string) $target?->auth_version;
        $hasTarget = $target !== null;

        return DB::transaction(function () use ($actorId, $expectedActorVersion, $targetId, $expectedTargetVersion, $hasTarget, $callback): mixed {
            // Kunci baris dalam urutan ID yang sama untuk mencegah potensi deadlock.
            // SQLite menggunakan transaksi IMMEDIATE alih-alih row lock.
            $ids = $targetId === null ? [$actorId] : array_unique([$actorId, $targetId]);
            sort($ids, SORT_NUMERIC);
            $users = [];
            foreach ($ids as $id) {
                $users[$id] = User::query()->lockForUpdate()->find($id);
            }

            $actor = $users[$actorId];

            if (! $actor || ! $actor->is_active || ! hash_equals($expectedActorVersion, (string) $actor->auth_version)) {
                throw new AuthenticationException(guards: ['web']);
            }

            $freshTarget = $targetId === null ? null : $users[$targetId];
            if ($hasTarget && ! $freshTarget) {
                throw (new ModelNotFoundException)->setModel(User::class);
            }

            if ($freshTarget && ! hash_equals($expectedTargetVersion, (string) $freshTarget->auth_version)) {
                throw ValidationException::withMessages([
                    'user' => 'Data akun telah berubah. Muat ulang halaman lalu coba lagi.',
                ]);
            }

            return $callback($actor, $freshTarget);
        }, attempts: 3);
    }
}
