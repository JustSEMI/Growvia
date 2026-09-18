<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\Connection;
use Illuminate\Database\DeadlockException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use PDOException;
use Throwable;

final class MobileMutation
{
    /** @param Closure(User, ?User, PersonalAccessToken): mixed $callback */
    public static function run(Request $request, Closure $callback, ?User $target = null, bool $admin = false): mixed
    {
        $expected = $request->user()?->currentAccessToken();
        if (! $expected instanceof PersonalAccessToken || ! $request->bearerToken()) {
            throw new AuthenticationException;
        }

        return self::atomic(fn () => AccountMutation::run($request, function (User $actor, ?User $target) use ($request, $expected, $callback, $admin): mixed {
            $token = $actor->tokens()->lockForUpdate()->find($expected->getKey());
            $plain = $request->bearerToken();
            $secret = str_contains($plain, '|') ? explode('|', $plain, 2)[1] : $plain;
            if (! $token || ! hash_equals($token->token, hash('sha256', $secret))
                || ! hash_equals((string) $token->account_version, (string) $actor->auth_version)
                || ($token->expires_at && $token->expires_at->lte(now()))
                || (config('sanctum.expiration') && $token->created_at->lte(now()->subMinutes(config('sanctum.expiration'))))
                || ! $actor->hasRole('admin', 'user')) {
                throw new AuthenticationException;
            }
            abort_unless($token->can('mobile:access'), 403);
            abort_if($admin && ! $actor->isAdmin(), 403);

            return $callback($actor, $target, $token);
        }, $target));
    }

    /**
     * Roll back observer session deletions on a separate connection on failure.
     * Independent database commits are not a distributed transaction; auth_version
     * still makes any surviving web session unusable after account revocation.
     */
    public static function atomic(Closure $callback): mixed
    {
        $connection = DB::connection();
        $connections = [$connection];
        if (config('session.driver') === 'database') {
            $sessions = DB::connection(config('session.connection'));
            if ($sessions !== $connection) {
                $connections[] = $sessions;
            }
        }
        $levels = array_map(fn (Connection $db): int => $db->transactionLevel(), $connections);
        $outermost = max($levels) === 0;

        for ($attempt = 1; ; $attempt++) {
            $committed = false;
            try {
                foreach (array_reverse($connections) as $db) {
                    $db->beginTransaction();
                }
                $result = $callback();
                foreach ($connections as $db) {
                    $db->commit();
                    $committed = true;
                }

                return $result;
            } catch (Throwable $error) {
                foreach ($connections as $index => $db) {
                    $db->rollBack($levels[$index]);
                    // Deadlock bersarang di Laravel mengurangi level tanpa me-rollback
                    // PDO secara langsung. Owner terluar harus menyelesaikan rollback tersebut.
                    if ($levels[$index] === 0 && $db->getPdo()->inTransaction()) {
                        $db->getPdo()->rollBack();
                    }
                }
                $cause = $error;
                while ($cause instanceof DeadlockException && $cause->getPrevious()) {
                    $cause = $cause->getPrevious();
                }
                if (! $outermost || $committed || $attempt >= 3
                    || ! $cause instanceof PDOException
                    || ! (new ConcurrencyErrorDetector)->causedByConcurrencyError($cause)) {
                    throw $error;
                }
            }
        }
    }

    public static function password(User $actor, mixed $password, string $field = 'current_password'): void
    {
        if (! is_string($password) || ! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages([$field => 'Kata sandi tidak sesuai.']);
        }
    }

    public static function rotate(User $user, PersonalAccessToken $previous): string
    {
        $token = $user->createToken($previous->name, ['mobile:access'], now()->addMinutes(config('sanctum.expiration') ?: 10080));
        $token->accessToken->forceFill(['account_version' => $user->auth_version])->save();

        return $token->plainTextToken;
    }
}
