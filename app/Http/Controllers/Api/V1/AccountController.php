<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileUserResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MobileInput;
use App\Support\MobileMutation;
use App\Support\MobileSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AccountController extends Controller
{
    public function sessions(Request $request): JsonResponse
    {
        return response()->json(MobileSessions::page($request))->header('Cache-Control', 'no-store');
    }

    public function profile(Request $request): JsonResponse
    {
        $result = MobileMutation::run($request, function (User $actor, ?User $target, PersonalAccessToken $token) use ($request): array {
            $input = MobileInput::validate($request, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($actor->id)],
                'current_password' => ['nullable', 'string', 'max:4096'],
            ]);
            if ($input['email'] !== $actor->email || isset($input['current_password'])) {
                MobileMutation::password($actor, $input['current_password'] ?? null);
            }
            $snapshot = clone $actor;
            $actor->fill(array_intersect_key($input, array_flip(['name', 'email'])))->save();
            if ($actor->wasChanged(['name', 'email'])) {
                ActivityLog::record($actor->wasChanged('email') ? 'account.email_changed' : 'account.profile_updated', $snapshot, $actor, array_keys($actor->getChanges()));
            }

            return $this->result($request, $actor, $token, 'Profil tersimpan.');
        });

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function password(Request $request): JsonResponse
    {
        $result = MobileMutation::run($request, function (User $actor, ?User $target, PersonalAccessToken $token) use ($request): array {
            $input = MobileInput::validate($request, [
                'current_password' => ['required', 'string', 'max:4096'],
                'password' => ['required', 'string', 'min:8', 'max:4096', 'confirmed'],
                'password_confirmation' => ['required', 'string', 'max:4096'],
            ]);
            MobileMutation::password($actor, $input['current_password']);
            $actor->update(['password' => Hash::make($input['password'])]);
            ActivityLog::record('account.password_changed', $actor, $actor);

            return $this->result($request, $actor, $token, 'Kata sandi diganti.');
        });

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function revokeOthers(Request $request): JsonResponse
    {
        $result = MobileMutation::run($request, function (User $actor, ?User $target, PersonalAccessToken $token) use ($request): array {
            $input = MobileInput::validate($request, ['current_password' => ['required', 'string', 'max:4096']]);
            MobileMutation::password($actor, $input['current_password']);
            $actor->forceFill(['auth_version' => Str::random(40), 'remember_token' => Str::random(60)])->save();
            ActivityLog::record('account.sessions_revoked', $actor, $actor);

            return $this->result($request, $actor, $token, 'Sesi perangkat lain diakhiri. Perangkat ini tetap masuk.');
        });

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function result(Request $request, User $user, PersonalAccessToken $token, string $message): array
    {
        $result = ['user' => (new MobileUserResource($user))->resolve($request), 'message' => $message];
        if ($user->wasChanged('auth_version')) {
            $result['token'] = MobileMutation::rotate($user, $token);
        }

        return $result;
    }
}
