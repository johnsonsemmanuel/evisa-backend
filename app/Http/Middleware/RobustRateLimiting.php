<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RobustRateLimiting
{
    /**
     * Handle an incoming request with robust rate limiting.
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        $key = $this->resolveRequestSignature($request, $prefix);
        
        try {
            // Try Redis-based rate limiting first
            $limiter = app(RateLimiter::class);
            
            if ($this->isRedisAvailable()) {
                if ($limiter->tooManyAttempts($key, $maxAttempts)) {
                    return $this->buildResponse($key, $maxAttempts);
                }
                
                $limiter->hit($key, $decayMinutes * 60);
            } else {
                // Fallback to database-based rate limiting
                if ($this->databaseRateLimit($key, $maxAttempts, $decayMinutes)) {
                    return $this->buildResponse($key, $maxAttempts);
                }
            }
        } catch (\Exception $e) {
            // Log the error but don't block the request
            \Log::warning('Rate limiting failed: ' . $e->getMessage(), [
                'key' => $key,
                'max_attempts' => $maxAttempts,
            ]);
        }
        
        return $next($request);
    }
    
    /**
     * Resolve request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request, string $prefix = ''): string
    {
        // Use user ID if authenticated, otherwise IP
        $identifier = $request->user()?->id ?: $request->ip();
        
        // Add user agent to prevent shared IP issues
        $userAgent = md5($request->userAgent());
        
        return 'rate_limit:' . $prefix . ':' . sha1($identifier . $userAgent);
    }
    
    /**
     * Check if Redis is available.
     * Returns false since Redis PHP extension is not installed.
     */
    protected function isRedisAvailable(): bool
    {
        // Redis PHP extension is not installed, always use database fallback
        return false;
    }
    
    /**
     * Fallback database-based rate limiting.
     */
    protected function databaseRateLimit(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        $cacheKey = 'rate_limit_db:' . $key;
        $attempts = cache()->get($cacheKey, 0);
        
        if ($attempts >= $maxAttempts) {
            return true;
        }
        
        cache()->put($cacheKey, $attempts + 1, $decayMinutes * 60);
        return false;
    }
    
    /**
     * Create a rate limiting response.
     */
    protected function buildResponse(string $key, int $maxAttempts): HttpResponse
    {
        $retryAfter = 60; // Default retry after 1 minute
        
        try {
            $limiter = app(RateLimiter::class);
            $retryAfter = $limiter->availableIn($key);
        } catch (\Exception $e) {
            // Use default retry after
        }
        
        return Response::json([
            'message' => 'Too many attempts. Please try again later.',
            'retry_after' => $retryAfter,
            'code' => 429
        ], 429)->header('Retry-After', $retryAfter);
    }
}
