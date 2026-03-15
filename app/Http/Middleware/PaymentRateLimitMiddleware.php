<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Notifications\PaymentSuspensionNotification;
use Symfony\Component\HttpFoundation\Response;

class PaymentRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }

        $userId = Auth::id();
        $hourKey = now()->format('Y-m-d-H');
        
        // Redis keys for tracking
        $failedAttemptsKey = "payment_failed_attempts:{$userId}:{$hourKey}";
        $suspensionKey = "payment_suspended:{$userId}";
        $flaggedKey = "payment_flagged:{$userId}:{$hourKey}";

        try {
            // Check if user is currently suspended
            if (Cache::has($suspensionKey)) {
                Log::warning('Payment attempt by suspended user', [
                    'user_id' => $userId,
                    'ip' => $request->ip(),
                    'route' => $request->route()->getName(),
                ]);

                return response()->json([
                    'error' => 'Payment access temporarily suspended due to multiple failed attempts. Please contact support.',
                    'code' => 'PAYMENT_SUSPENDED',
                    'retry_after' => Cache::get($suspensionKey . '_expires_at'),
                ], 429);
            }

            // Process the request
            $response = $next($request);

            // Check if this was a failed payment attempt
            if ($this->isFailedPaymentResponse($response)) {
                $this->recordFailedAttempt($userId, $hourKey, $failedAttemptsKey, $flaggedKey, $suspensionKey, $request);
            }

            return $response;

        } catch (\Exception $e) {
            // Gracefully degrade if Redis is unavailable
            Log::warning('PaymentRateLimitMiddleware Redis error - allowing request', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return $next($request);
        }
    }

    /**
     * Determine if the response indicates a failed payment.
     */
    protected function isFailedPaymentResponse(Response $response): bool
    {
        // Check for HTTP error status codes
        if ($response->getStatusCode() >= 400) {
            return true;
        }

        // Check for JSON response with payment failure indicators
        $content = $response->getContent();
        if ($content && is_string($content)) {
            $data = json_decode($content, true);
            
            if (is_array($data)) {
                // Check for common payment failure indicators
                $failureIndicators = [
                    'payment_failed',
                    'transaction_failed',
                    'insufficient_funds',
                    'card_declined',
                    'payment_error',
                    'gateway_error',
                ];

                $responseText = strtolower(json_encode($data));
                foreach ($failureIndicators as $indicator) {
                    if (strpos($responseText, $indicator) !== false) {
                        return true;
                    }
                }

                // Check for specific error codes or status fields
                if (isset($data['success']) && $data['success'] === false) {
                    return true;
                }

                if (isset($data['status']) && in_array($data['status'], ['failed', 'error', 'declined'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Record a failed payment attempt and take appropriate action.
     */
    protected function recordFailedAttempt(
        int $userId,
        string $hourKey,
        string $failedAttemptsKey,
        string $flaggedKey,
        string $suspensionKey,
        Request $request
    ): void {
        try {
            // Increment failed attempts counter
            $failedAttempts = Cache::increment($failedAttemptsKey, 1);
            
            // Set expiration for the counter (1 hour)
            if ($failedAttempts === 1) {
                Cache::put($failedAttemptsKey, 1, now()->addHour());
            }

            Log::info('Payment failure recorded', [
                'user_id' => $userId,
                'failed_attempts' => $failedAttempts,
                'hour_key' => $hourKey,
                'ip' => $request->ip(),
                'route' => $request->route()->getName(),
            ]);

            // Take action based on failure count
            if ($failedAttempts >= 10) {
                // Suspend payment access for 24 hours
                $this->suspendPaymentAccess($userId, $suspensionKey, $request);
            } elseif ($failedAttempts >= 3 && !Cache::has($flaggedKey)) {
                // Flag account for review
                $this->flagAccountForReview($userId, $flaggedKey, $request);
            }

        } catch (\Exception $e) {
            Log::error('Failed to record payment attempt', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Suspend payment access for the user.
     */
    protected function suspendPaymentAccess(int $userId, string $suspensionKey, Request $request): void
    {
        $expiresAt = now()->addHours(24);
        
        // Set suspension flag
        Cache::put($suspensionKey, true, $expiresAt);
        Cache::put($suspensionKey . '_expires_at', $expiresAt->toISOString(), $expiresAt);

        // Log the suspension
        Log::critical('User payment access suspended', [
            'user_id' => $userId,
            'suspended_until' => $expiresAt,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Notify administrators
        $this->notifyAdministrators($userId, 'suspended', [
            'reason' => '10 failed payment attempts in 1 hour',
            'suspended_until' => $expiresAt,
            'ip' => $request->ip(),
        ]);

        // Notify the user
        try {
            $user = User::find($userId);
            if ($user) {
                $user->notify(new PaymentSuspensionNotification($expiresAt));
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify user of payment suspension', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Flag account for review.
     */
    protected function flagAccountForReview(int $userId, string $flaggedKey, Request $request): void
    {
        // Set flagged status for this hour
        Cache::put($flaggedKey, true, now()->addHour());

        // Log the flagging
        Log::warning('User account flagged for payment review', [
            'user_id' => $userId,
            'reason' => '3 failed payment attempts in 1 hour',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Notify administrators
        $this->notifyAdministrators($userId, 'flagged', [
            'reason' => '3 failed payment attempts in 1 hour',
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Notify administrators of payment security events.
     */
    protected function notifyAdministrators(int $userId, string $action, array $details): void
    {
        try {
            // Get admin users
            $admins = User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->get();

            if ($admins->isEmpty()) {
                Log::warning('No admin users found to notify about payment security event', [
                    'user_id' => $userId,
                    'action' => $action,
                ]);
                return;
            }

            // Send notification to admins
            Notification::send($admins, new \App\Notifications\PaymentSecurityAlertNotification(
                $userId,
                $action,
                $details
            ));

        } catch (\Exception $e) {
            Log::error('Failed to notify administrators of payment security event', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}