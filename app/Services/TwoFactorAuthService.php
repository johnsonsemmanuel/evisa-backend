<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TwoFactorAuthService
 * 
 * Handles TOTP-based Multi-Factor Authentication logic.
 * Implements NIST SP 800-53 IA-2 requirements for government systems.
 * 
 * SECURITY FEATURES:
 * - TOTP secrets encrypted at rest
 * - Recovery codes hashed (bcrypt)
 * - Rate limiting on verification attempts
 * - Single-use recovery codes
 * - Short-lived two-factor tokens (5 minutes)
 */
class TwoFactorAuthService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Check if user's role requires 2FA.
     */
    public function isRoleRequired(User $user): bool
    {
        return in_array($user->role, config('two-factor.required_roles', []));
    }

    /**
     * Check if user has 2FA enabled and confirmed.
     */
    public function isEnabled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    /**
     * Check if user needs to set up 2FA.
     */
    public function needsSetup(User $user): bool
    {
        return $user->two_factor_required && !$this->isEnabled($user);
    }

    /**
     * Generate a new TOTP secret for the user.
     * 
     * @return array ['secret' => string, 'qr_code_url' => string]
     */
    public function generateSecret(User $user): array
    {
        // Generate secret
        $secret = $this->google2fa->generateSecretKey(
            config('two-factor.totp.secret_length', 16)
        );

        // Store encrypted secret (not yet confirmed)
        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->two_factor_confirmed_at = null; // Reset confirmation
        $user->save();

        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('two-factor.totp.issuer', config('app.name')),
            $user->email,
            $secret
        );

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    /**
     * Verify TOTP code and confirm 2FA setup.
     * 
     * @param User $user
     * @param string $code
     * @return bool
     */
    /**
     * @return array|false Plaintext recovery codes on success, false on failure
     */
    public function confirmSetup(User $user, string $code): array|false
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        $valid = $this->google2fa->verifyKey(
            $secret,
            $code,
            config('two-factor.totp.tolerance', 1)
        );

        if ($valid) {
            $user->two_factor_confirmed_at = now();

            $recoveryCodes = $this->generateRecoveryCodes();
            $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($recoveryCodes['hashed']));

            $user->save();

            return $recoveryCodes['plaintext'];
        }

        return false;
    }

    /**
     * Verify TOTP code during login.
     * 
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function verifyCode(User $user, string $code): bool
    {
        if (!$this->isEnabled($user)) {
            return false;
        }

        // Check rate limiting
        if ($this->isRateLimited($user)) {
            return false;
        }

        // Decrypt secret
        $secret = Crypt::decryptString($user->two_factor_secret);

        // Verify code
        $valid = $this->google2fa->verifyKey(
            $secret,
            $code,
            config('two-factor.totp.tolerance', 1)
        );

        if (!$valid) {
            $this->incrementAttempts($user);
        } else {
            $this->clearAttempts($user);
        }

        return $valid;
    }

    /**
     * Verify recovery code.
     * 
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (!$this->isEnabled($user) || !$user->two_factor_recovery_codes) {
            return false;
        }

        // Check rate limiting
        if ($this->isRateLimited($user)) {
            return false;
        }

        // Decrypt recovery codes
        $recoveryCodes = json_decode(
            Crypt::decryptString($user->two_factor_recovery_codes),
            true
        );

        // Normalize input code
        $code = strtoupper(str_replace('-', '', $code));

        // Check each recovery code
        foreach ($recoveryCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                // Remove used code
                unset($recoveryCodes[$index]);
                
                // Save updated codes
                $user->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($recoveryCodes)));
                $user->save();

                $this->clearAttempts($user);
                
                return true;
            }
        }

        $this->incrementAttempts($user);
        return false;
    }

    /**
     * Generate recovery codes.
     * 
     * @return array ['plaintext' => array, 'hashed' => array]
     */
    public function generateRecoveryCodes(): array
    {
        $count = config('two-factor.recovery_codes.count', 8);
        $length = config('two-factor.recovery_codes.length', 12);
        $characters = config('two-factor.recovery_codes.characters', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');

        $plaintext = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate random code
            $code = '';
            for ($j = 0; $j < $length; $j++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }

            // Format: XXXX-XXXX-XXXX
            $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
            
            $plaintext[] = $formatted;
            $hashed[] = Hash::make(str_replace('-', '', $code));
        }

        return [
            'plaintext' => $plaintext,
            'hashed' => $hashed,
        ];
    }

    /**
     * Regenerate recovery codes.
     * 
     * @param User $user
     * @return array Plaintext recovery codes
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (!$this->isEnabled($user)) {
            throw new \RuntimeException('2FA must be enabled to regenerate recovery codes');
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($recoveryCodes['hashed']));
        $user->save();

        return $recoveryCodes['plaintext'];
    }

    /**
     * Get recovery codes (decrypt and return plaintext).
     * 
     * SECURITY: This should only be called once after setup or regeneration.
     * 
     * @param User $user
     * @return array|null
     */
    public function getRecoveryCodes(User $user): ?array
    {
        if (!$user->two_factor_recovery_codes) {
            return null;
        }

        // This returns hashed codes - we can't decrypt them
        // Recovery codes are only shown once at generation time
        return null;
    }

    /**
     * Disable 2FA for a user.
     * 
     * @param User $user
     * @return void
     */
    public function disable(User $user): void
    {
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        $this->clearAttempts($user);
    }

    /**
     * Create a temporary two-factor token for login flow.
     * 
     * @param User $user
     * @return string
     */
    public function createTwoFactorToken(User $user): string
    {
        $token = Str::random(config('two-factor.token.length', 64));
        
        $key = config('two-factor.token.prefix', '2fa_token:') . $token;
        $expiresIn = config('two-factor.token.expires_in', 300);

        // Store user ID in Redis with expiration
        Cache::put($key, $user->id, $expiresIn);

        return $token;
    }

    /**
     * Verify and consume a two-factor token.
     * 
     * @param string $token
     * @return User|null
     */
    public function verifyTwoFactorToken(string $token): ?User
    {
        $key = config('two-factor.token.prefix', '2fa_token:') . $token;
        
        $userId = Cache::get($key);

        if (!$userId) {
            return null;
        }

        // Delete token (single-use)
        Cache::forget($key);

        return User::find($userId);
    }

    /**
     * Check if user is rate limited.
     */
    protected function isRateLimited(User $user): bool
    {
        $key = "2fa_attempts:{$user->id}";
        $attempts = Cache::get($key, 0);
        $maxAttempts = config('two-factor.rate_limit.max_attempts', 5);

        return $attempts >= $maxAttempts;
    }

    /**
     * Increment failed attempts counter.
     */
    protected function incrementAttempts(User $user): void
    {
        $key = "2fa_attempts:{$user->id}";
        $lockoutDuration = config('two-factor.rate_limit.lockout_duration', 60) * 60; // Convert to seconds
        
        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, $lockoutDuration);
    }

    /**
     * Clear failed attempts counter.
     */
    protected function clearAttempts(User $user): void
    {
        $key = "2fa_attempts:{$user->id}";
        Cache::forget($key);
    }

    /**
     * Get remaining attempts before lockout.
     */
    public function getRemainingAttempts(User $user): int
    {
        $key = "2fa_attempts:{$user->id}";
        $attempts = Cache::get($key, 0);
        $maxAttempts = config('two-factor.rate_limit.max_attempts', 5);

        return max(0, $maxAttempts - $attempts);
    }
}
