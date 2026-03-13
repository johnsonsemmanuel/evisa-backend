<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Government-Grade Brute Force Protection Middleware
 * Implements progressive lockout and security monitoring.
 */
class BruteForceProtection
{
    protected RateLimiter $limiter;

    /**
     * Lockout thresholds (attempts => lockout minutes)
     */
    protected array $lockoutThresholds = [
        5 => 1,      // 5 attempts = 1 minute lockout
        10 => 5,     // 10 attempts = 5 minute lockout
        15 => 15,    // 15 attempts = 15 minute lockout
        20 => 60,    // 20 attempts = 1 hour lockout
        30 => 1440,  // 30 attempts = 24 hour lockout (account lockout)
    ];

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $endpoint = 'login'): Response
    {
        $key = $this->resolveKey($request, $endpoint);
        $attempts = $this->getAttempts($key);

        // Check if currently locked out
        $lockoutMinutes = $this->getLockoutDuration($attempts);
        if ($lockoutMinutes > 0 && $this->isLockedOut($key)) {
            $this->logSuspiciousActivity($request, $endpoint, $attempts);
            
            return response()->json([
                'message' => 'Too many failed attempts. Account temporarily locked.',
                'locked_until' => now()->addMinutes($lockoutMinutes)->toIso8601String(),
                'retry_after' => $lockoutMinutes * 60,
            ], 429);
        }

        $response = $next($request);

        // Track failed attempts (401 or 422 responses)
        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 422) {
            $this->incrementAttempts($key, $endpoint);
            $newAttempts = $this->getAttempts($key);
            
            // Log escalating attempts
            if ($newAttempts >= 5) {
                $this->logSuspiciousActivity($request, $endpoint, $newAttempts);
            }

            // Apply lockout if threshold reached
            $lockoutMinutes = $this->getLockoutDuration($newAttempts);
            if ($lockoutMinutes > 0) {
                $this->applyLockout($key, $lockoutMinutes);
            }
        } elseif ($response->getStatusCode() === 200) {
            // Clear attempts on successful authentication
            $this->clearAttempts($key);
        }

        return $response;
    }

    /**
     * Generate unique key for rate limiting.
     */
    protected function resolveKey(Request $request, string $endpoint): string
    {
        $identifier = $request->input('email') ?? $request->ip();
        return 'brute_force:' . $endpoint . ':' . sha1($identifier);
    }

    /**
     * Get current attempt count.
     */
    protected function getAttempts(string $key): int
    {
        return (int) cache()->get($key . ':attempts', 0);
    }

    /**
     * Increment attempt counter.
     */
    protected function incrementAttempts(string $key, string $endpoint): void
    {
        $attempts = $this->getAttempts($key) + 1;
        cache()->put($key . ':attempts', $attempts, now()->addHours(24));
    }

    /**
     * Clear attempts on successful login.
     */
    protected function clearAttempts(string $key): void
    {
        cache()->forget($key . ':attempts');
        cache()->forget($key . ':lockout');
    }

    /**
     * Get lockout duration based on attempts.
     */
    protected function getLockoutDuration(int $attempts): int
    {
        $lockout = 0;
        foreach ($this->lockoutThresholds as $threshold => $minutes) {
            if ($attempts >= $threshold) {
                $lockout = $minutes;
            }
        }
        return $lockout;
    }

    /**
     * Check if currently locked out.
     */
    protected function isLockedOut(string $key): bool
    {
        return cache()->has($key . ':lockout');
    }

    /**
     * Apply lockout.
     */
    protected function applyLockout(string $key, int $minutes): void
    {
        cache()->put($key . ':lockout', true, now()->addMinutes($minutes));
    }

    /**
     * Log suspicious activity for security monitoring.
     */
    protected function logSuspiciousActivity(Request $request, string $endpoint, int $attempts): void
    {
        Log::channel('security')->warning('Brute force attempt detected', [
            'endpoint' => $endpoint,
            'attempts' => $attempts,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'email' => $request->input('email'),
            'timestamp' => now()->toIso8601String(),
            'severity' => $this->getSeverity($attempts),
        ]);

        // Trigger alert for critical attempts
        if ($attempts >= 20) {
            $this->triggerSecurityAlert($request, $endpoint, $attempts);
        }
    }

    /**
     * Get severity level based on attempts.
     */
    protected function getSeverity(int $attempts): string
    {
        if ($attempts >= 30) return 'CRITICAL';
        if ($attempts >= 20) return 'HIGH';
        if ($attempts >= 10) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Trigger security alert for critical attempts.
     */
    protected function triggerSecurityAlert(Request $request, string $endpoint, int $attempts): void
    {
        // In production, this would send alerts to security team
        Log::channel('security')->critical('SECURITY ALERT: Potential brute force attack', [
            'endpoint' => $endpoint,
            'attempts' => $attempts,
            'ip_address' => $request->ip(),
            'action_required' => 'Review and potentially block IP',
        ]);
    }
}
