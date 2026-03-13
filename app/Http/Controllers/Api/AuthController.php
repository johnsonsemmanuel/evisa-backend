<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
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
            'role'       => 'applicant',
            'locale'     => $validated['locale'] ?? 'en',
            'email_verified_at' => now(), // Auto-verify
        ]);

        $token = $user->createToken('applicant-token')->plainTextToken;

        return response()->json([
            'message' => __('auth.registered'),
            'user'    => $this->userResource($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * Authenticate a user and return a token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validated)) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user->is_active) {
            return response()->json([
                'message' => __('auth.account_deactivated'),
            ], 403);
        }

        $primaryRole = $user->roles->first()?->name ?? 'user';
        $token = $user->createToken($primaryRole . '-token')->plainTextToken;

        return response()->json([
            'message' => __('auth.login_success'),
            'user'    => $this->userResource($user),
            'token'   => $token,
        ]);
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('auth.logged_out'),
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
