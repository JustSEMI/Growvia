<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Observers\UserObserver;
use App\Support\SettingRules;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Konsol dikecualikan agar key:generate dan package:discover tetap berjalan.
        if (! $this->app->runningInConsole() && blank(config('app.key'))) {
            throw new \RuntimeException('APP_KEY belum diatur. Jalankan: php artisan key:generate');
        }

        $perIpAndEmail = function (Request $request): array {
            $email = $request->input('email');
            $key = hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : 'invalid');

            return [Limit::perMinute(5)->by('ip:'.$request->ip()), Limit::perMinute(5)->by('email:'.$key)];
        };

        RateLimiter::for('mobile-login', $perIpAndEmail);
        RateLimiter::for('recovery', $perIpAndEmail);
        RateLimiter::for('reset-submit', $perIpAndEmail);
        RateLimiter::for('mobile-api', fn (Request $request): array => [
            Limit::perMinute(120)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('reauthentication', fn (Request $request): array => [
            Limit::perMinute(6)->by('account:'.$request->user()->getAuthIdentifier()),
            Limit::perMinute(6)->by('ip:'.$request->ip()),
        ]);

        User::observe(UserObserver::class);
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web' && request()->hasSession()) {
                request()->session()->put('account_version', (string) $event->user->auth_version);
            }
        });

        ResetPassword::createUrlUsing(function ($user, string $token): string {
            return rtrim(config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false);
        });
        Paginator::defaultView('vendor.pagination.growvia');

        View::composer('dashboard', fn (\Illuminate\View\View $view) => $view->with('recentActivities', ActivityLog::query()->latest('created_at')->latest('id')->limit(3)->get()));

        View::composer(['dashboard', 'petani.node1', 'petani.node2'], function (\Illuminate\View\View $view): void {
            $view->with('monitorSettings', array_merge(
                SettingRules::DEFAULTS,
                Setting::query()->pluck('value', 'key')->all(),
            ));
        });
    }
}
