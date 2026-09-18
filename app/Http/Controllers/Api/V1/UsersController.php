<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileUserResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MobileInput;
use App\Support\MobileMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = MobileInput::validate($request, [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $query = User::query();
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }
        if ($role = $filters['role'] ?? null) {
            $query->where('role', $role);
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        [$column, $direction] = match ($filters['sort'] ?? 'newest') {
            'oldest' => ['created_at', 'asc'], 'name_asc' => ['name', 'asc'], 'name_desc' => ['name', 'desc'], default => ['created_at', 'desc'],
        };

        return response()->json(MobileInput::page($query->orderBy($column, $direction)->orderBy('id', $direction)->paginate(15), MobileUserResource::class, $request))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $user = MobileMutation::run($request, function (User $actor) use ($request): User {
            $input = MobileInput::validate($request, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'max:4096'],
                'role' => ['required', Rule::in(['admin', 'user'])],
            ]);
            $input['password'] = Hash::make($input['password']);
            $user = User::create($input);
            ActivityLog::record('user.created', $actor, $user, ['name', 'email', 'role']);

            return $user;
        }, admin: true);

        return response()->json(['user' => (new MobileUserResource($user))->resolve($request), 'message' => 'Akun dibuat.'], 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $result = MobileMutation::run($request, function (User $actor, User $target, PersonalAccessToken $token) use ($request): array {
            $input = MobileInput::validate($request, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($target->id)],
                'role' => ['required', Rule::in(['admin', 'user'])],
                'password' => ['nullable', 'string', 'min:8', 'max:4096'],
                'admin_password' => ['nullable', 'string', 'max:4096'],
            ]);
            if ($target->is($actor) && $input['role'] !== 'admin') {
                throw ValidationException::withMessages(['role' => 'Hak akses sendiri tidak boleh diturunkan.']);
            }
            if ($input['email'] !== $target->email || isset($input['password']) || isset($input['admin_password'])) {
                MobileMutation::password($actor, $input['admin_password'] ?? null, 'admin_password');
            }
            $snapshot = clone $actor;
            $target->fill(array_intersect_key($input, array_flip(['name', 'email', 'role'])));
            if (isset($input['password'])) {
                $target->password = Hash::make($input['password']);
            }
            $target->save();
            if ($target->wasChanged(['name', 'email', 'role'])) {
                ActivityLog::record('user.updated', $snapshot, $target, array_keys($target->getChanges()));
            }
            if ($target->wasChanged('password')) {
                ActivityLog::record('account.password_reset', $snapshot, $target);
            }
            $result = ['user' => (new MobileUserResource($target))->resolve($request), 'message' => 'Akun diperbarui.'];
            if ($target->is($actor) && $target->wasChanged('auth_version')) {
                $result['token'] = MobileMutation::rotate($target, $token);
            }

            return $result;
        }, $user, admin: true);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function status(Request $request, User $user): JsonResponse
    {
        $updated = MobileMutation::run($request, function (User $actor, User $target) use ($request): User {
            $input = MobileInput::validate($request, ['is_active' => ['required', 'boolean']]);
            if ($actor->is($target)) {
                throw ValidationException::withMessages(['user' => 'Akun sendiri tidak boleh dinonaktifkan.']);
            }
            $target->is_active = (bool) $input['is_active'];
            $target->save();
            if ($target->wasChanged('is_active')) {
                ActivityLog::record($target->is_active ? 'user.activated' : 'user.deactivated', $actor, $target, ['is_active']);
            }

            return $target;
        }, $user, admin: true);

        return response()->json(['user' => (new MobileUserResource($updated))->resolve($request), 'message' => 'Status akun diperbarui.'])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, User $user): Response
    {
        MobileMutation::run($request, function (User $actor, User $target) use ($request): void {
            MobileInput::validate($request, []);
            if ($actor->is($target)) {
                throw ValidationException::withMessages(['user' => 'Akun sendiri tidak boleh dihapus.']);
            }
            ActivityLog::record('user.deleted', $actor, $target);
            $target->delete();
        }, $user, admin: true);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
