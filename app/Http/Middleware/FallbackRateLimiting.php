<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FallbackRateLimiting
{
    /**
     * Handle rate limiting with fallback mechanisms.
     */
    public static function checkRateLimit(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        // Try Redis first
        if (static::isRedisAvailable()) {
            return static::redisRateLimit($key, $maxAttempts, $decayMinutes);
        }
        
        // Fallback to database
        return static::databaseRateLimit($key, $maxAttempts, $decayMinutes);
    }
    
    /**
     * Check if Redis is available.
     */
    protected static function isRedisAvailable(): bool
    {
        return false; // Force database fallback for now
    }
    
    /**
     * Database-based rate limiting.
     */
    protected static function databaseRateLimit(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        // Use cache as fallback
        $cacheKey = 'rate_limit_db:' . $key;
        $attempts = Cache::get($cacheKey, 0);
        
        if ($attempts >= $maxAttempts) {
            return true;
        }
        
        Cache::put($cacheKey, $attempts + 1, $decayMinutes * 60);
        return false;
    }
    
    /**
     * Clear rate limit for a specific key.
     */
    public static function clearRateLimit(string $key): void
    {
        Cache::forget('rate_limit_db:' . $key);
    }
    
    /**
     * Get remaining attempts.
     */
    public static function getRemainingAttempts(string $key, int $maxAttempts): int
    {
        $attempts = Cache::get('rate_limit_db:' . $key, 0);
        return max(0, $maxAttempts - $attempts);
    }
}
