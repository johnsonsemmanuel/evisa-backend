<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class HandleAuthenticationErrors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) {
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'attempts' => $e->getHeaders()['X-RateLimit-Remaining'] ?? 'unknown',
            ]);
            
            return Response::json([
                'message' => 'Too many authentication attempts. Please wait a moment before trying again.',
                'code' => 429,
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                'type' => 'rate_limit_exceeded'
            ], 429)->header('Retry-After', $e->getHeaders()['Retry-After'] ?? 60);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return Response::json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'code' => 422,
                'type' => 'validation_error'
            ], 422);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return Response::json([
                'message' => 'Authentication failed. Please check your credentials.',
                'code' => 401,
                'type' => 'authentication_error'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Unexpected authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            
            return Response::json([
                'message' => 'An error occurred during authentication. Please try again.',
                'code' => 500,
                'type' => 'server_error'
            ], 500);
        }
    }
}
