<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiErrorHandler
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (\Exception $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle exceptions and return appropriate API response.
     */
    protected function handleException(\Exception $e, Request $request): Response
    {
        // Log the error
        Log::error('API Error: ' . $e->getMessage(), [
            'exception' => $e,
            'request' => [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]
        ]);

        // Handle different exception types
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'code' => 422
            ], 422);
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 401
            ], 401);
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'message' => 'Forbidden',
                'code' => 403
            ], 403);
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'message' => 'Resource not found',
                'code' => 404
            ], 404);
        }

        if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'code' => 429,
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 60
            ], 429);
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return response()->json([
                'message' => 'Endpoint not found',
                'code' => 404
            ], 404);
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return response()->json([
                'message' => 'Method not allowed',
                'code' => 405
            ], 405);
        }

        // Default error response
        $statusCode = $this->getStatusCode($e);
        $message = app()->environment('production') 
            ? 'An error occurred. Please try again.' 
            : $e->getMessage();

        return response()->json([
            'message' => $message,
            'code' => $statusCode,
            'timestamp' => now()->toISOString(),
            'path' => $request->path()
        ], $statusCode);
    }

    /**
     * Get appropriate status code for exception.
     */
    protected function getStatusCode(\Exception $e): int
    {
        $statusCode = 500;

        // Check for HTTP exceptions
        if (method_exists($e, 'getStatusCode')) {
            $statusCode = $e->getStatusCode();
        }

        // Handle specific exception types
        if ($e instanceof \Illuminate\Database\QueryException) {
            $statusCode = 500;
        }

        if ($e instanceof \Illuminate\Http\Exceptions\PostTooLargeException) {
            $statusCode = 413;
        }

        return $statusCode;
    }
}
