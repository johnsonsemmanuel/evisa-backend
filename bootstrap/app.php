<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'   => \App\Http\Middleware\EnsureRole::class,
            'audit'  => \App\Http\Middleware\AuditAction::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'api.error' => \App\Http\Middleware\ApiErrorHandler::class,
            'auth.errors' => \App\Http\Middleware\HandleAuthenticationErrors::class,
            'robust.throttle' => \App\Http\Middleware\RobustRateLimiting::class,
        ]);

        // Rate limiting for API routes
        $middleware->throttleApi('60,1'); // 60 requests per minute

        $middleware->statefulApi();
        
        // Configure authentication to return JSON for API routes
        $middleware->redirectGuestsTo(fn ($request) => $request->expectsJson() ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'Authentication required'
                ], 401);
            }
        });
    })->create();
