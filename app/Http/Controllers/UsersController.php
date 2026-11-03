<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AccountMutation;
use App\Support\AccountSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'edit' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = User::query();
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            });
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        [$column, $direction] = match ($filters['sort'] ?? 'newest') {
            'oldest' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            default => ['created_at', 'desc'],
        };

        return view('pengguna', [
            'users' => $query->orderBy($column, $direction)->orderBy('id', $direction)->paginate(10)->appends($request->only('q', 'role', 'sort', 'status')),
            'editing' => ! empty($filters['edit']) ? User::query()->findOrFail($filters['edit']) : null,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AccountMutation::run($request, function (User $actor) use ($request): void {
            abort_unless($actor->isAdmin(), 403);
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
                'role' => ['required', Rule::in(['admin', 'user'])],
            ]);
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
            ]);
            ActivityLog::record('user.created', $actor, $user, ['name', 'email', 'role']);
        });

        return redirect()->route('users.index')->with('status', 'Akun kebuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $updated = AccountMutation::run($request, function (User $actor, User $target) use ($request): User {
            abort_unless($actor->isAdmin(), 403);
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($target->id)],
                'password' => ['nullable', 'string', 'min:8'],
                'role' => ['required', Rule::in(['admin', 'user'])],
                'admin_password' => [Rule::requiredIf($request->input('email') !== $target->email || $request->filled('password')), 'nullable', 'string', 'current_password:web'],
            ]);
            if ($target->is($actor) && $validated['role'] !== 'admin') {
                throw ValidationException::withMessages(['role' => 'Hak akses sendiri tidak boleh diturunkan.']);
            }
            $actorSnapshot = clone $actor;
            $target->fill(collect($validated)->only(['name', 'email', 'role'])->all());
            if (! empty($validated['password'])) {
                $target->password = Hash::make($validated['password']);
            }
            $target->save();
            if ($target->wasChanged(['name', 'email', 'role'])) {
                ActivityLog::record('user.updated', $actorSnapshot, $target, array_keys($target->getChanges()));
            }
            if ($target->wasChanged('password')) {
                ActivityLog::record('account.password_reset', $actorSnapshot, $target);
            }

            return $target;
        }, $user);
        if ($updated->is($request->user())) {
            if ($updated->wasChanged('auth_version')) {
                AccountSessions::keepCurrent($request, $updated);
            } else {
                Auth::guard('web')->setUser($updated);
            }
        }

        return redirect()->route('users.index')->with('status', 'Akun keupdate.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $updated = AccountMutation::run($request, function (User $actor, User $target) use ($request): User {
            abort_unless($actor->isAdmin(), 403);
            $validated = $request->validate(['is_active' => ['required', 'boolean']]);
            if ($target->is($actor)) {
                throw ValidationException::withMessages(['user' => 'Akun sendiri tidak boleh dinonaktifkan.']);
            }
            $target->is_active = (bool) $validated['is_active'];
            $target->save();
            if ($target->wasChanged('is_active')) {
                ActivityLog::record($target->is_active ? 'user.activated' : 'user.deactivated', $actor, $target, ['is_active']);
            }

            return $target;
        }, $user);

        return back()->with('status', $updated->is_active ? 'Akun diaktifin.' : 'Akun dinonaktifin.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        AccountMutation::run($request, function (User $actor, User $target): void {
            abort_unless($actor->isAdmin(), 403);
            if ($target->is($actor)) {
                throw ValidationException::withMessages(['user' => 'Akun sendiri tidak boleh dihapus.']);
            }
            ActivityLog::record('user.deleted', $actor, $target);
            $target->delete();
        }, $user);

        return redirect()->route('users.index')->with('status', 'Akun kehapus.');
    }
}
