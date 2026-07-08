<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

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
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addDays(30));

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Password::defaults(function () {
            $rule = Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // The default reset link targets the API's named route, which is a
        // POST-only JSON endpoint — useless in an email. Point it at the
        // SPA's reset page instead (it reads the token from the path and
        // prefills the email from the query string).
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return config('app.frontend_url')
                .'/reset-password/'.$token
                .'?email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
