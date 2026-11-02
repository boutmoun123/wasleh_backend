<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
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
        RateLimiter::for('login', fn (Request $request): array => $this->authLimits($request, 10, 30));
        RateLimiter::for('otp', fn (Request $request): array => $this->authLimits($request, 5, 15));
        RateLimiter::for('password-reset', fn (Request $request): array => $this->authLimits($request, 3, 10));
        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->user()?->id.'|'.$request->ip()));

        ResetPassword::createUrlUsing(function (CanResetPassword $user, string $token): string {
            return rtrim(config('app.frontend_url'), '/')
                .'/reset-password?token='.urlencode($token)
                .'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }

    /** @return array<int, Limit> */
    private function authLimits(Request $request, int $perMinute, int $perHour): array
    {
        $email = Str::lower($request->string('email')->toString());

        return [
            Limit::perMinute($perMinute)->by($email.'|'.$request->ip()),
            Limit::perHour($perHour)->by($email),
        ];
    }
}
