<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\AuthService;
use Illuminate\Support\Facades\Log;
use App\Services\LoginAttemptService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService,
        protected LoginAttemptService $loginAttemptService,
        protected AuthService $authService
    ) {}
    /**
     * Register a new applicant account.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone'      => 'nullable|string|max:20',
            'locale'     => 'nullable|in:en,fr',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'phone'      => $validated['phone'] ?? null,
            'locale'     => $validated['locale'] ?? 'en',
        ]);
        $user->forceFill([
            'role' => 'applicant',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $token = $user->createToken('applicant-token')->plainTextToken;

        return response()->json([
            'message' => __('auth.registered'),
            'user'    => $this->userResource($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * Authenticate a user and return a token.
     * 
     * MODIFIED FOR 2FA:
     * - If user has 2FA enabled: returns two_factor_token (not full Sanctum token)
     * - If user doesn't have 2FA: returns full Sanctum token (backward compatible)
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $validated['email'];
        $password = $validated['password'];
        $ipAddress = $request->ip();

        // STEP 1: Check if login is locked due to brute force protection
        if ($this->loginAttemptService->isLocked($email, $ipAddress)) {
            $lockoutDuration = $this->loginAttemptService->getLockoutDuration($email, $ipAddress);
            
            $response = response()->json([
                'message' => 'Too many login attempts. Please try again later.',
            ], 429);

            // Add Retry-After header if not permanently locked
            if ($lockoutDuration > 0) {
                $response->header('Retry-After', $lockoutDuration);
            }

            return $response;
        }

        // STEP 2: Attempt authentication
        // Always perform hash check to prevent timing attacks (even for non-existent users)
        $user = User::where('email', $email)->first();
        $credentialsValid = false;

        if ($user) {
            $credentialsValid = Auth::attempt($validated);
        } else {
            // Perform dummy hash check to maintain consistent timing
            Hash::check($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
        }

        // STEP 3: Handle failed authentication
        if (!$credentialsValid) {
            // Track failed attempt
            $this->loginAttemptService->trackFailedAttempt($email, $ipAddress);
            
            // Get remaining attempts for header
            $remainingAttempts = $this->loginAttemptService->getRemainingAttempts($email, $ipAddress);
            
            $response = response()->json([
                'message' => 'Invalid credentials',
            ], 401);

            // Add attempt remaining header (helps legitimate users)
            if ($remainingAttempts > 0) {
                $response->header('X-Auth-Attempts-Remaining', $remainingAttempts);
            }

            return $response;
        }

        // STEP 4: Check if account is active
        if (!$user->is_active) {
            // Still track as failed attempt since account is unusable
            $this->loginAttemptService->trackFailedAttempt($email, $ipAddress);
            
            return response()->json([
                'message' => __('auth.account_deactivated'),
            ], 403);
        }

        // STEP 5: Clear failed attempts on successful authentication
        $this->loginAttemptService->clearAttempts($email, $ipAddress);

        // Log successful login (security audit)
        Log::info('Successful login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $ipAddress,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        // STEP 6: Handle 2FA flow
        // Check if user has 2FA enabled
        if ($this->twoFactorService->isEnabled($user)) {
            // Generate temporary two-factor token
            $twoFactorToken = $this->twoFactorService->createTwoFactorToken($user);

            return response()->json([
                'message' => '2FA verification required',
                'requires_2fa' => true,
                'two_factor_token' => $twoFactorToken,
                'user' => [
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]);
        }

        // Check if 2FA is required but not set up
        if ($this->twoFactorService->needsSetup($user)) {
            return response()->json([
                'message' => '2FA setup required',
                'requires_2fa_setup' => true,
                'setup_url' => '/api/auth/2fa/setup',
                'user' => $this->userResource($user),
                // Provide temporary token for 2FA setup only
                'setup_token' => $user->createToken(
                    name: '2fa-setup-token',
                    abilities: ['2fa:setup', '2fa:verify', 'profile:read'],
                    expiresAt: now()->addMinutes(30) // Short expiry for setup
                )->plainTextToken,
            ], 403);
        }

        // STEP 7: Create token with role-based abilities and expiration
        $token = $this->authService->createTokenForUser($user, 'auth_token');

        // Log token creation for audit
        Log::info('Authentication token created', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'token_abilities' => $this->authService->getAbilitiesForRole($user->role),
            'expires_at' => $this->authService->getTokenExpirationForRole($user->role)->toISOString(),
            'ip' => $ipAddress,
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => __('auth.login_success'),
            'user'    => $this->userResource($user),
            'token'   => $token,
            'expires_at' => $this->authService->getTokenExpirationForRole($user->role)->toISOString(),
            'abilities' => $this->authService->getAbilitiesForRole($user->role),
        ]);
    }

    /**
     * Revoke the current access token (logout).
     */
    /**
     * Logout user and revoke authentication tokens
     * 
     * SECURITY: Implements server-side token revocation to prevent token reuse.
     * Admin/super_admin users have all tokens revoked (single session policy).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        // Log logout attempt for audit trail
        Log::info('User logout initiated', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'token_id' => $currentToken?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        // Revoke the current token (standard logout)
        if ($currentToken) {
            $currentToken->delete();
        }

        // For admin/super_admin logout — revoke ALL tokens (security policy: one active session)
        if (in_array($user->role, ['admin', 'super_admin', 'SYSTEM_ADMIN'])) {
            $tokenCount = $user->tokens()->count();
            $user->tokens()->delete();
            
            Log::warning('Admin user - all tokens revoked on logout', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'tokens_revoked' => $tokenCount,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ]);
        }

        // Create audit log entry for logout event
        try {
            $now = now();
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.logout',
                'auditable_type' => 'User',
                'auditable_id' => $user->id,
                'old_values' => null,
                'new_values' => [
                    'logout_method' => 'manual',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'tokens_revoked' => in_array($user->role, ['admin', 'super_admin', 'SYSTEM_ADMIN']) ? 'all' : 'current',
                ],
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Exception $e) {
            // Don't fail logout if audit logging fails
            Log::error('Failed to create logout audit log', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => __('auth.logged_out'),
            'logged_out_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get current token information
     * 
     * Useful for debugging and client-side token management.
     */
    public function tokenInfo(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokenInfo = $this->authService->getTokenInfo($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $tokenInfo,
            'abilities' => $this->authService->getAbilitiesForRole($user->role),
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userResource($request->user()),
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'phone'      => 'nullable|string|max:20',
            'locale'     => 'sometimes|in:en,fr',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => __('auth.profile_updated'),
            'user'    => $this->userResource($request->user()),
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => __('auth.current_password_incorrect'),
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => __('auth.password_changed'),
        ]);
    }

    /**
     * Verify email address with token.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $user = User::where('email_verification_token', $validated['token'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired verification token',
            ], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ]);

        return response()->json([
            'message' => 'Email verified successfully. You can now create applications.',
        ]);
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified',
            ], 422);
        }

        $verificationToken = bin2hex(random_bytes(32));
        $user->update(['email_verification_token' => $verificationToken]);

        \App\Jobs\SendEmailVerification::dispatch($user);

        return response()->json([
            'message' => 'Verification email sent',
        ]);
    }

    private function userResource(User $user): array
    {
        // Map column-based role to frontend role format
        $roleMapping = [
            'gis_admin' => 'GIS_ADMIN',
            'gis_reviewer' => 'GIS_REVIEWING_OFFICER',
            'gis_approver' => 'GIS_APPROVAL_OFFICER',
            'gis_officer' => 'GIS_REVIEWING_OFFICER',
            'mfa_admin' => 'MFA_ADMIN',
            'mfa_reviewer' => 'MFA_REVIEWING_OFFICER',
            'mfa_approver' => 'MFA_APPROVAL_OFFICER',
            'admin' => 'SYSTEM_ADMIN',
            'applicant' => 'APPLICANT',
            'immigration_officer' => 'IMMIGRATION_OFFICER',
            'airline_staff' => 'AIRLINE_STAFF',
        ];

        $frontendRole = $roleMapping[$user->role] ?? strtoupper($user->role);

        // Build permissions array based on role and capabilities
        $permissions = [];
        
        // All officers can view applications
        if (in_array($user->role, ['gis_reviewer', 'gis_approver', 'gis_admin', 'mfa_reviewer', 'mfa_approver', 'mfa_admin', 'admin'])) {
            $permissions[] = 'applications.view';
            $permissions[] = 'documents.view';
            $permissions[] = 'notes.view';
            $permissions[] = 'notes.create';
        }
        
        // Review permissions
        if ($user->canReviewApplications()) {
            $permissions[] = 'applications.review';
            $permissions[] = 'applications.request_info';
            $permissions[] = 'documents.verify';
            $permissions[] = 'risk.view';
            $permissions[] = 'risk.assess';
        }
        
        // Approval permissions
        if ($user->canApproveApplications()) {
            $permissions[] = 'applications.approve';
            $permissions[] = 'applications.deny';
            $permissions[] = 'applications.escalate';
            $permissions[] = 'evisa.generate';
        }
        
        // Admin permissions
        if (in_array($user->role, ['gis_admin', 'mfa_admin', 'admin'])) {
            $permissions[] = 'reports.view';
            $permissions[] = 'reports.export';
        }

        return [
            'id'          => $user->id,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'full_name'   => $user->full_name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'role'        => $frontendRole,
            'agency'      => $user->agency,
            'locale'      => $user->locale,
            'permissions' => $permissions,
        ];
    }
}
