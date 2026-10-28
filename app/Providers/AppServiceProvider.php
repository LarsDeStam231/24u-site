<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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
        ResetPassword::toMailUsing(fn (User $user, string $token): MailMessage => (new MailMessage)
            ->subject('Stel jouw wachtwoord in — 24 Uur Live')
            ->line('Er is een verzoek gedaan om jouw wachtwoord in te stellen of te wijzigen.')
            ->action('Wachtwoord instellen', rtrim(config('app.url'), '/').route('password.reset', [], false).'#'.http_build_query(['token' => $token, 'email' => $user->email]))
            ->line('Deze link werkt één keer en is '.config('auth.passwords.users.expire').' minuten geldig. Heb je dit niet aangevraagd? Dan kun je deze e-mail negeren.'));
        Gate::define('manage-qr-accounts', fn (User $user): bool => $user->isApproved() && $user->role === 'admin');
        Gate::define('scan-attendance', fn (User $user): bool => $user->isApproved()
            && in_array($user->role, ['admin', 'organisatie', 'vrijwilliger'], true));

        RateLimiter::for('qr-login', function (Request $request): array {
            $token = $request->input('token');

            return [
                Limit::perMinute(120)->by('ip:'.$request->ip()),
                Limit::perMinute(10)->by('token:'.hash('sha256', is_string($token) ? $token : '').'|'.$request->ip()),
            ];
        });
        RateLimiter::for('qr-poll', fn (Request $request): Limit => Limit::perMinute(45)
            ->by($request->ip().'|'.$request->session()->getId()));
        RateLimiter::for('password-login', function (Request $request): array {
            $email = $request->input('email');

            return [
                Limit::perMinute(120)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('email:'.hash('sha256', is_string($email) ? Str::lower(trim($email)) : '').'|'.$request->ip()),
            ];
        });
        RateLimiter::for('qr-mail', fn (Request $request): array => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(3)->by(hash('sha256', Str::lower(is_string($request->input('email')) ? trim($request->input('email')) : ''))),
        ]);
    }
}
