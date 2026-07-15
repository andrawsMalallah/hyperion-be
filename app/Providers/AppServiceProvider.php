<?php

namespace App\Providers;

use App\Mail\BrevoApiTransport;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        // Send mail through Brevo's HTTP API (mailer "brevo-api") so production
        // isn't dependent on outbound SMTP, which Render's free tier blocks —
        // a synchronous SMTP send there hangs until the request 504s.
        Mail::extend('brevo-api', function (array $config) {
            return new BrevoApiTransport(
                app(Factory::class),
                $config['key'] ?? '',
            );
        });

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

        // Same idea for email verification: the default link targets the API's
        // signed `verification.verify` route (JSON-only). We sign that backend
        // URL, then hand the SPA the same {id}/{hash} + signature query so its
        // verify page can forward them to the API — the signature stays valid
        // because it was computed against the backend route the SPA calls.
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // Reuse the signed query (expires + signature) unchanged.
            $query = parse_url($signedUrl, PHP_URL_QUERY);

            return config('app.frontend_url')
                .'/verify-email/'.$notifiable->getKey()
                .'/'.sha1($notifiable->getEmailForVerification())
                .'?'.$query;
        });
    }
}
