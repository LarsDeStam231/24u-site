<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-qr-accounts', fn (User $user): bool => $user->isApproved() && $user->role === 'admin');
        Gate::define('scan-attendance', fn (User $user): bool => $user->isApproved()
            && in_array($user->role, ['admin', 'organisatie', 'vrijwilliger'], true));

        RateLimiter::for('qr-login', fn (Request $request): array => [
            Limit::perMinute(120)->by('ip:'.$request->ip()),
            Limit::perMinute(10)->by('token:'.hash('sha256', (string) $request->input('token', '')).'|'.$request->ip()),
        ]);
        RateLimiter::for('password-login', function (Request $request): array {
            $email = $request->input('email');

            return [
                Limit::perMinute(120)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('email:'.hash('sha256', is_string($email) ? Str::lower(trim($email)) : '').'|'.$request->ip()),
            ];
        });
        RateLimiter::for('qr-mail', fn (Request $request): array => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(3)->by(hash('sha256', Str::lower((string) $request->input('email', '')))),
        ]);
    }
}
