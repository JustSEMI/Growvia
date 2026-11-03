<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isAdmin()
        ? redirect()->route('dashboard')
        : redirect()->route('petani.dashboard');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('/lupa-sandi', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/lupa-sandi', [PasswordResetController::class, 'store'])->name('password.email')->middleware('throttle:recovery');
    Route::get('/reset-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-sandi', [PasswordResetController::class, 'update'])->name('password.update')->middleware('throttle:reset-submit');
});

Route::middleware('auth')->group(function () {
    Route::delete('/pengaturan/sesi', [SessionController::class, 'destroy'])->middleware(['throttle:reauthentication', 'throttle:6,1'])->name('sessions.destroy');
    Route::get('/pengaturan/sesi', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('role:admin')->group(function () {
        Route::get('/aktivitas', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('/dashboard', function () {
            return view('dashboard', [
                'headerTitle' => 'Halo, '.auth()->user()->name,
            ]);
        })->name('dashboard');

        Route::prefix('pengaturan')->name('settings.')->controller(SettingsController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::patch('/sistem', 'updateSystem')->middleware('throttle:reauthentication')->name('system');
        });

        Route::prefix('pengguna')->name('users.')->controller(UsersController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:reauthentication')->name('store');
            Route::patch('/{user}/status', 'updateStatus')->middleware('throttle:reauthentication')->name('status');
            Route::patch('/{user}', 'update')->middleware('throttle:reauthentication')->name('update');
            Route::delete('/{user}', 'destroy')->middleware('throttle:reauthentication')->name('destroy');
        });
    });

    Route::middleware('role:user,admin')->prefix('pengaturan')->name('settings.')->controller(SettingsController::class)->group(function () {
        Route::patch('/profil', 'updateProfile')->middleware('throttle:reauthentication')->name('profile');
        Route::patch('/kata-sandi', 'updatePassword')->middleware('throttle:reauthentication')->name('password');
    });

    Route::middleware('role:user,admin')->prefix('petani')->name('petani.')->group(function () {
        Route::get('/', fn () => view('petani.dashboard'))->name('dashboard');
        Route::get('/iklim', fn () => view('petani.node1'))->name('node1');
        Route::get('/tanah', fn () => view('petani.node2'))->name('node2');
        Route::get('/pengaturan', fn () => view('petani.pengaturan'))->name('settings');
        Route::get('/edukasi', fn () => view('petani.edukasi'))->name('edukasi');
    });
});
