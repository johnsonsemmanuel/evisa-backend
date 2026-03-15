<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Notifications\AccountLockedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class LoginAttemptService
{
    private const IP_KEY_PREFIX = 'login_attempts:ip:';
    private const EMAIL_KEY_PREFIX = 'login_attempts:email:';
    private const LOCKOUT_KEY_PREFIX = 'login_lockout:account:';
    private const TTL_SECONDS = 3600; // 1 hour

    // Lockout policy thresholds and durations
    private const LOCKOUT_TIERS = [
        4 => 60,      // 4-5 attempts: 1 minute
        6 => 900,     // 6-8 attempts: 15 minutes  
        9 => 3600,    // 9-10 attempts: 1 hour
        11 => -1,     // 11+ attempts: permanent (requires admin unlock)
    ];

    /**
     * Track a failed login attempt for both IP and email
     */
    public function trackFailedAttempt(string $email, string $ipAddress): void
    {
        try {
            $emailHash = $this->hashEmail($email);
            $ipKey = self::IP_KEY_PREFIX . $ipAddress;
            $emailKey = self::EMAIL_KEY_PREFIX . $emailHash;

            // Increment counters with TTL
            $ipAttempts = Redis::incr($ipKey);
            $emailAttempts = Redis::incr($emailKey);

            // Set TTL on first attempt
            if ($ipAttempts === 1) {
                Redis::expire($ipKey, self::TTL_SECONDS);
            }
            if ($emailAttempts === 1) {
                Redis::expire($emailKey, self::TTL_SECONDS);
            }

            // Check for permanent lockout (11+ attempts)
            if ($emailAttempts >= 11) {
                $this->lockAccountPermanently($email, $emailHash, $ipAddress, $emailAttempts);
            }

            Log::warning('Failed login attempt tracked', [
                'email_hash' => $emailHash,
                'ip' => $ipAddress,
                'ip_attempts' => $ipAttempts,
                'email_attempts' => $emailAttempts,
            ]);

        } catch (\Exception $e) {
            // Graceful degradation - log error but don't fail the request
            Log::error('Failed to track login attempt in Redis', [
                'error' => $e->getMessage(),
                'email_hash' => $this->hashEmail($email),
                'ip' => $ipAddress,
            ]);
        }
    }

    /**
     * Check if login is locked for either IP or email
     */
    public function isLocked(string $email, string $ipAddress): bool
    {
        try {
            $emailHash = $this->hashEmail($email);
            
            // Check permanent account lockout first
            if (Redis::exists(self::LOCKOUT_KEY_PREFIX . $emailHash)) {
                return true;
            }

            $ipAttempts = (int) Redis::get(self::IP_KEY_PREFIX . $ipAddress) ?: 0;
            $emailAttempts = (int) Redis::get(self::EMAIL_KEY_PREFIX . $emailHash) ?: 0;

            return $this->shouldLockout($ipAttempts) || $this->shouldLockout($emailAttempts);

        } catch (\Exception $e) {
            // Graceful degradation - if Redis fails, allow login but log error
            Log::error('Failed to check lockout status in Redis', [
                'error' => $e->getMessage(),
                'email_hash' => $this->hashEmail($email),
                'ip' => $ipAddress,
            ]);
            return false;
        }
    }

    /**
     * Get lockout duration in seconds remaining
     */
    public function getLockoutDuration(string $email, string $ipAddress): int
    {
        try {
            $emailHash = $this->hashEmail($email);
            
            // Check permanent lockout
            if (Redis::exists(self::LOCKOUT_KEY_PREFIX . $emailHash)) {
                return -1; // Permanent
            }

            $ipAttempts = (int) Redis::get(self::IP_KEY_PREFIX . $ipAddress) ?: 0;
            $emailAttempts = (int) Redis::get(self::EMAIL_KEY_PREFIX . $emailHash) ?: 0;

            $ipDuration = $this->getLockoutDurationForAttempts($ipAttempts);
            $emailDuration = $this->getLockoutDurationForAttempts($emailAttempts);

            // Return the longer duration
            return max($ipDuration, $emailDuration);

        } catch (\Exception $e) {
            Log::error('Failed to get lockout duration from Redis', [
                'error' => $e->getMessage(),
                'email_hash' => $this->hashEmail($email),
                'ip' => $ipAddress,
            ]);
            return 0;
        }
    }

    /**
     * Clear attempts for both IP and email on successful login
     */
    public function clearAttempts(string $email, string $ipAddress): void
    {
        try {
            $emailHash = $this->hashEmail($email);
            $ipKey = self::IP_KEY_PREFIX . $ipAddress;
            $emailKey = self::EMAIL_KEY_PREFIX . $emailHash;

            Redis::del($ipKey);
            Redis::del($emailKey);

            Log::info('Login attempts cleared after successful login', [
                'email_hash' => $emailHash,
                'ip' => $ipAddress,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear login attempts in Redis', [
                'error' => $e->getMessage(),
                'email_hash' => $this->hashEmail($email),
                'ip' => $ipAddress,
            ]);
        }
    }

    /**
     * Get current attempt count for both IP and email
     */
    public function getAttemptCount(string $email, string $ipAddress): array
    {
        try {
            $emailHash = $this->hashEmail($email);
            $ipAttempts = (int) Redis::get(self::IP_KEY_PREFIX . $ipAddress) ?: 0;
            $emailAttempts = (int) Redis::get(self::EMAIL_KEY_PREFIX . $emailHash) ?: 0;

            return [
                'ip_attempts' => $ipAttempts,
                'email_attempts' => $emailAttempts,
                'max_attempts' => max($ipAttempts, $emailAttempts),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get attempt count from Redis', [
                'error' => $e->getMessage(),
                'email_hash' => $this->hashEmail($email),
                'ip' => $ipAddress,
            ]);
            return ['ip_attempts' => 0, 'email_attempts' => 0, 'max_attempts' => 0];
        }
    }

    /**
     * Get remaining attempts before next lockout tier
     */
    public function getRemainingAttempts(string $email, string $ipAddress): int
    {
        $counts = $this->getAttemptCount($email, $ipAddress);
        $maxAttempts = $counts['max_attempts'];

        foreach (self::LOCKOUT_TIERS as $threshold => $duration) {
            if ($maxAttempts < $threshold) {
                return $threshold - $maxAttempts;
            }
        }

        return 0; // Already at or past permanent lockout
    }

    /**
     * Unlock a permanently locked account (admin only)
     */
    public function unlockAccount(string $email): bool
    {
        try {
            $emailHash = $this->hashEmail($email);
            $lockoutKey = self::LOCKOUT_KEY_PREFIX . $emailHash;
            
            if (Redis::exists($lockoutKey)) {
                Redis::del($lockoutKey);
                
                // Also clear any remaining attempt counters
                Redis::del(self::EMAIL_KEY_PREFIX . $emailHash);
                
                Log::info('Account unlocked by admin', [
                    'email_hash' => $emailHash,
                ]);
                
                return true;
            }
            
            return false;

        } catch (\Exception $e) {
            Log::error('Failed to unlock account in Redis', [
                'error' => $e->getMessage(),
                'email_hash' => $this->hashEmail($email),
            ]);
            return false;
        }
    }

    /**
     * Check if account is permanently locked
     */
    public function isPermanentlyLocked(string $email): bool
    {
        try {
            $emailHash = $this->hashEmail($email);
            return Redis::exists(self::LOCKOUT_KEY_PREFIX . $emailHash);
        } catch (\Exception $e) {
            Log::error('Failed to check permanent lockout status', [
                'error' => $e->getMessage(),
                'email_hash' => $this->hashEmail($email),
            ]);
            return false;
        }
    }

    /**
     * Hash email for Redis key (privacy protection)
     */
    private function hashEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /**
     * Determine if attempts should trigger lockout
     */
    private function shouldLockout(int $attempts): bool
    {
        return $attempts >= 4; // Lockout starts at 4 attempts
    }

    /**
     * Get lockout duration for given attempt count
     */
    private function getLockoutDurationForAttempts(int $attempts): int
    {
        foreach (self::LOCKOUT_TIERS as $threshold => $duration) {
            if ($attempts >= $threshold) {
                if ($duration === -1) {
                    return -1; // Permanent
                }
                continue; // Check next tier
            }
            break;
        }

        // Find the appropriate tier
        $applicableDuration = 0;
        foreach (self::LOCKOUT_TIERS as $threshold => $duration) {
            if ($attempts >= $threshold) {
                $applicableDuration = $duration;
            }
        }

        return $applicableDuration;
    }

    /**
     * Lock account permanently and send notifications
     */
    private function lockAccountPermanently(string $email, string $emailHash, string $ipAddress, int $attempts): void
    {
        try {
            // Set permanent lockout flag
            Redis::set(self::LOCKOUT_KEY_PREFIX . $emailHash, time());

            // Find user and send notification to account owner
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->notify(new AccountLockedNotification($email, $ipAddress, $attempts));
            }

            // Notify super admins
            $superAdmins = User::where('role', 'super_admin')->get();
            Notification::send($superAdmins, new AccountLockedNotification($email, $ipAddress, $attempts, true));

            Log::critical('Account permanently locked due to excessive failed attempts', [
                'email_hash' => $emailHash,
                'ip' => $ipAddress,
                'attempts' => $attempts,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to lock account permanently', [
                'error' => $e->getMessage(),
                'email_hash' => $emailHash,
                'ip' => $ipAddress,
            ]);
        }
    }
}