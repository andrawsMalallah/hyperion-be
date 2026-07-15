<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks unverified accounts from the app's data endpoints.
 *
 * Returns 409 (not the framework default 403) with a stable `code` so the SPA's
 * axios interceptor can tell "verify your email" apart from an authorization
 * 403 (which the app maps to 404 for policies) and route the user to the
 * verification screen instead of logging them out.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address to continue.',
                'code' => 'email_unverified',
            ], 409);
        }

        return $next($request);
    }
}
