<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RecoveryController;
use App\Http\Controllers\Api\V1\UsersController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Middleware\EnsureMobileApiAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:mobile-api')->group(function (): void {
    Route::post('/auth/forgot-password', [RecoveryController::class, 'forgot'])->middleware('throttle:recovery')->name('auth.forgot-password');
    Route::post('/auth/reset-password', [RecoveryController::class, 'reset'])->middleware('throttle:reset-submit')->name('auth.reset-password');
    Route::post('/auth/login', [AuthController::class, 'store'])->middleware('throttle:mobile-login')->name('auth.login');
    Route::get('/health', fn () => response()->json([
        'app' => 'Growvia',
        'api_version' => 'v1',
        'status' => 'ok',
    ])->header('Cache-Control', 'no-store'))->name('health');

    Route::middleware(['auth:sanctum', EnsureMobileApiAccess::class])->group(function (): void {
        Route::get('/workspace', [WorkspaceController::class, 'show'])->name('workspace');
        Route::get('/account/sessions', [AccountController::class, 'sessions'])->name('account.sessions');
        Route::patch('/account/profile', [AccountController::class, 'profile'])->middleware('throttle:reauthentication')->name('account.profile');
        Route::patch('/account/password', [AccountController::class, 'password'])->middleware('throttle:reauthentication')->name('account.password');
        Route::post('/account/sessions/revoke-others', [AccountController::class, 'revokeOthers'])->middleware('throttle:reauthentication')->name('account.revoke-others');
        Route::middleware('role:admin')->group(function (): void {
            Route::get('/users', [UsersController::class, 'index'])->name('users.index');
            Route::post('/users', [UsersController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [UsersController::class, 'update'])->middleware('throttle:reauthentication')->name('users.update');
            Route::patch('/users/{user}/status', [UsersController::class, 'status'])->name('users.status');
            Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
            Route::get('/settings', [WorkspaceController::class, 'settings'])->name('settings');
            Route::patch('/settings', [WorkspaceController::class, 'updateSettings'])->name('settings.update');
            Route::get('/activities', [WorkspaceController::class, 'activities'])->name('activities');
        });
        Route::get('/auth/me', [AuthController::class, 'show'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'destroy'])->name('auth.logout');
        Route::get('/readings', function (Request $request) {
            $request->validate(['node' => ['nullable', 'string', Rule::in(['node1', 'node2'])]]);

            return response()->json([
                'data' => [],
                'meta' => [
                    'available' => false,
                    'reason' => 'Integrasi sensor belum aktif. Belum ada riwayat pembacaan.',
                    'source' => 'rest_api',
                ],
            ])->header('Cache-Control', 'no-store');
        })->name('readings.index');
    });
});
