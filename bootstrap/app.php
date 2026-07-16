<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureEmailVerified;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();

        // Override the framework's `verified` alias so an unverified account
        // gets a 409 { code: email_unverified } JSON response the SPA can act on,
        // instead of the default 403 (which the app reserves for authorization).
        $middleware->alias([
            'verified' => EnsureEmailVerified::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Prevent database details leaking in production
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => config('app.debug')
                        ? $e->getMessage()
                        : 'An unexpected database error occurred. Please try again later.',
                ], 500);
            }
        });

        $exceptions->render(function (PDOException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => config('app.debug')
                        ? $e->getMessage()
                        : 'A connection error occurred. Please try again later.',
                ], 500);
            }
        });

        // A missing record (findOrFail / route-model-binding) surfaces as a
        // NotFoundHttpException with an empty client message — give it real text.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'The requested item was not found.',
                ], 404);
            }
        });

        // Bare abort(403) renders with an empty message; supply a friendly one
        // while preserving any explicit message passed to abort().
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 403 && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json([
                    'message' => $e->getMessage() ?: "You don't have permission to do that.",
                ], 403);
            }
        });
    })->create();
