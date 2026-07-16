<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Restrict a route to admin accounts. A non-admin (or guest) gets a plain
     * 403 — this is a genuine authorization boundary on an admin-only surface,
     * not the resource-hiding 404 the app uses for object-level ownership.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            abort(403, 'This area is restricted to administrators.');
        }

        return $next($request);
    }
}
