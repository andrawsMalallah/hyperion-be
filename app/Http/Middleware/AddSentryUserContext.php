<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Attaches the authenticated user's ID to Sentry error reports.
 *
 * Only the numeric ID is sent — enough to tell "one user" from "everyone" and to
 * line an API error up with the matching browser error, without shipping emails
 * or names to a third party. This is why `send_default_pii` stays false in
 * config/sentry.php: that flag would attach the user's ID *and* their email and
 * IP address automatically.
 */
class AddSentryUserContext
{
    public function handle(Request $request, Closure $next)
    {
        // Nothing to do when Sentry has no DSN configured (e.g. local dev).
        if (empty(config('sentry.dsn'))) {
            return $next($request);
        }

        // The 'api' guard must be named explicitly: the app's default guard is
        // 'web' (session), so $request->user() would always be null here.
        // Passport caches the resolved user, so the later auth:api middleware
        // does not repeat this lookup.
        $user = $this->resolveApiUser($request);

        if ($user !== null) {
            \Sentry\configureScope(function (Scope $scope) use ($user): void {
                $scope->setUser(['id' => $user->getAuthIdentifier()]);
            });
        }

        return $next($request);
    }

    /**
     * Resolve the Passport user, tolerating a malformed or expired bearer token.
     *
     * This middleware runs ahead of auth:api, so a bad token surfaces here first;
     * swallowing that keeps auth:api responsible for rejecting the request with
     * its normal 401 rather than this middleware turning it into a 500.
     */
    private function resolveApiUser(Request $request)
    {
        try {
            return $request->user('api');
        } catch (HttpExceptionInterface) {
            return null;
        }
    }
}
