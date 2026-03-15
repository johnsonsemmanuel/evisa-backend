<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TwoFactorAuthController
 * 
 * Handles TOTP-based Multi-Factor Authentication endpoints.
 * Implements NIST SP 800-53 IA-2 requirements.
 */
class TwoFactorAuthController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService
    ) {}

    /**
     * Generate 2FA secret and QR code for setup.
     * 
     * POST /api/auth/2fa/setup
     * 
     * @return JsonResponse
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if 2FA is already enabled
        if ($this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'message' => '2FA is already enabled. Disable it first to set up again.',
            ], 422);
        }

        try {
            $setup = $this->twoFactorService->generateSecret($user);

            Log::info('2FA setup initiated', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Scan the QR code with your authenticator app',
                'secret' => $setup['secret'],
                'qr_code_url' => $setup['qr_code_url'],
                'manual_entry' => [
                    'issuer' => config('two-factor.totp.issuer'),
                    'account' => $user->email,
                    'secret' => $setup['secret'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('2FA setup failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => '2FA setup failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Confirm 2FA setup by verifying first TOTP code.
     * 
     * POST /api/auth/2fa/confirm
     * Body: { "code": "123456" }
     * 
     * @return JsonResponse
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        // Check if already confirmed
        if ($this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'message' => '2FA is already confirmed',
            ], 422);
        }

        // Check if secret exists
        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Please set up 2FA first',
                'setup_url' => '/api/auth/2fa/setup',
            ], 422);
        }

        $recoveryCodes = $this->twoFactorService->confirmSetup($user, $validated['code']);

        if ($recoveryCodes === false) {
            Log::warning('2FA confirmation failed - invalid code', [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Invalid verification code. Please try again.',
            ], 422);
        }

        Log::info('2FA confirmed successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => '2FA enabled successfully',
            'recovery_codes' => $recoveryCodes,
            'warning' => 'Save these recovery codes in a secure location. They will not be shown again.',
        ]);
    }

    /**
     * Verify TOTP code during login flow.
     * 
     * POST /api/auth/2fa/verify
     * Body: { "two_factor_token": "...", "code": "123456" }
     * 
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'two_factor_token' => 'required|string',
            'code' => 'required|string',
        ]);

        // Verify two-factor token
        $user = $this->twoFactorService->verifyTwoFactorToken($validated['two_factor_token']);

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired two-factor token. Please log in again.',
            ], 401);
        }

        // Check if code is a recovery code (format: XXXX-XXXX-XXXX)
        $isRecoveryCode = strlen($validated['code']) > 6;

        if ($isRecoveryCode) {
            $valid = $this->twoFactorService->verifyRecoveryCode($user, $validated['code']);
            
            if ($valid) {
                Log::warning('2FA verified using recovery code', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        } else {
            $valid = $this->twoFactorService->verifyCode($user, $validated['code']);
        }

        if (!$valid) {
            $remainingAttempts = $this->twoFactorService->getRemainingAttempts($user);

            Log::warning('2FA verification failed', [
                'user_id' => $user->id,
                'remaining_attempts' => $remainingAttempts,
            ]);

            if ($remainingAttempts === 0) {
                return response()->json([
                    'message' => 'Too many failed attempts. Account temporarily locked.',
                    'locked_until' => now()->addMinutes(config('two-factor.rate_limit.lockout_duration', 60))->toISOString(),
                ], 429);
            }

            return response()->json([
                'message' => 'Invalid verification code',
                'remaining_attempts' => $remainingAttempts,
            ], 422);
        }

        $token = app(\App\Services\AuthService::class)->createTokenForUser($user);

        Log::info('2FA verification successful', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Get recovery codes (one-time view).
     * 
     * GET /api/auth/2fa/recovery-codes
     * 
     * @return JsonResponse
     */
    public function getRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'message' => '2FA is not enabled',
            ], 422);
        }

        // Recovery codes are hashed and cannot be retrieved
        // They are only shown once at setup/regeneration time
        return response()->json([
            'message' => 'Recovery codes cannot be viewed after initial setup',
            'action' => 'Use /api/auth/2fa/recovery-codes/regenerate to generate new codes',
        ], 422);
    }

    /**
     * Regenerate recovery codes.
     * 
     * POST /api/auth/2fa/recovery-codes/regenerate
     * 
     * @return JsonResponse
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'message' => '2FA is not enabled',
            ], 422);
        }

        try {
            $recoveryCodes = $this->twoFactorService->regenerateRecoveryCodes($user);

            Log::info('2FA recovery codes regenerated', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Recovery codes regenerated successfully',
                'recovery_codes' => $recoveryCodes,
                'warning' => 'Save these recovery codes in a secure location. Old codes are now invalid.',
            ]);

        } catch (\Exception $e) {
            Log::error('Recovery code regeneration failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to regenerate recovery codes',
            ], 500);
        }
    }

    /**
     * Disable 2FA for a user (admin only).
     * 
     * POST /api/auth/2fa/disable
     * Body: { "user_id": 123 } (optional, defaults to authenticated user)
     * 
     * @return JsonResponse
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if disabling for another user (admin only)
        if ($request->has('user_id')) {
            if (!in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can disable 2FA for other users.',
                ], 403);
            }

            $targetUser = \App\Models\User::find($request->input('user_id'));
            
            if (!$targetUser) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }
        } else {
            $targetUser = $user;
        }

        // Check if 2FA is required for this role
        if ($this->twoFactorService->isRoleRequired($targetUser)) {
            return response()->json([
                'message' => '2FA cannot be disabled for this role. It is mandatory for security compliance.',
            ], 422);
        }

        $this->twoFactorService->disable($targetUser);

        Log::warning('2FA disabled', [
            'target_user_id' => $targetUser->id,
            'disabled_by_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => '2FA disabled successfully',
        ]);
    }

    /**
     * Get 2FA status for authenticated user.
     * 
     * GET /api/auth/2fa/status
     * 
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $this->twoFactorService->isEnabled($user),
            'required' => $this->twoFactorService->isRoleRequired($user),
            'needs_setup' => $this->twoFactorService->needsSetup($user),
            'confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
        ]);
    }
}
