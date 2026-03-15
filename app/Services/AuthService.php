<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

/**
 * AuthService
 * 
 * Handles authentication token management with role-based abilities and expiration.
 * Implements OWASP A07:2021 — Identification and Authentication Failures mitigation.
 * 
 * SECURITY FEATURES:
 * - Role-based token abilities (principle of least privilege)
 * - Differentiated token expiration (officers vs applicants)
 * - Comprehensive ability scoping for sensitive operations
 */
class AuthService
{
    /**
     * Get token abilities based on user role
     * 
     * Implements principle of least privilege - each role gets only the minimum
     * abilities required for their function.
     */
    public function getAbilitiesForRole(string $role): array
    {
        return match($role) {
            // Applicants: Limited to their own application management
            'applicant' => [
                'application:read',
                'application:create', 
                'application:update',
                'application:submit',
                'payment:initiate',
                'payment:view',
                'document:upload',
                'document:view',
                'profile:read',
                'profile:update',
            ],

            // GIS Officers: Case processing and management
            'gis_officer', 'gis_reviewer', 'gis_approver', 'gis_admin',
            'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN' => [
                'application:read',
                'application:process',
                'application:review',
                'application:approve',
                'application:deny',
                'case:manage',
                'case:assign',
                'case:escalate',
                'document:view',
                'note:create',
                'note:read',
                'report:read',
                'queue:manage',
            ],

            // MFA Officers: Mission-specific processing
            'mfa_reviewer', 'mfa_approver', 'mfa_admin',
            'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER', 'MFA_ADMIN' => [
                'application:read',
                'application:process',
                'application:review',
                'application:approve',
                'application:deny',
                'case:manage',
                'case:assign',
                'document:view',
                'note:create',
                'note:read',
                'report:read',
                'mission:manage',
            ],

            // Finance Officers: Payment and financial operations
            'finance_officer' => [
                'payment:read',
                'payment:process',
                'payment:refund',
                'payment:reconcile',
                'payment:audit',
                'report:read',
                'report:financial',
                'application:read', // Needed for payment context
                'refund:create',
                'refund:approve',
                'refund:process',
            ],

            // Border Officers: Entry verification only
            'border_officer' => [
                'application:read',
                'application:verify',
                'entry:confirm',
                'entry:deny',
                'document:view',
                'verification:create',
            ],

            // Visa Officers: Visa issuance
            'visa_officer' => [
                'application:read',
                'application:process',
                'visa:issue',
                'visa:revoke',
                'document:view',
                'note:create',
                'note:read',
            ],

            // Airline Staff: Boarding verification
            'airline_staff' => [
                'application:read',
                'application:verify',
                'boarding:verify',
                'document:view',
            ],

            // Admins: Full system access
            'admin', 'super_admin', 'SYSTEM_ADMIN' => ['*'],

            // 2FA Setup: Limited abilities for initial setup
            '2fa:setup' => [
                '2fa:setup',
                '2fa:verify',
                'profile:read',
            ],

            // Unknown roles: No abilities
            default => [],
        };
    }

    /**
     * Get token expiration time based on user role
     * 
     * Officers get shorter expiration due to sensitive data access.
     * Applicants get longer expiration for better user experience.
     */
    public function getTokenExpirationForRole(string $role): Carbon
    {
        $isOfficerRole = in_array($role, [
            'gis_officer', 'gis_reviewer', 'gis_approver', 'gis_admin',
            'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN',
            'mfa_reviewer', 'mfa_approver', 'mfa_admin',
            'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER', 'MFA_ADMIN',
            'finance_officer', 'border_officer', 'visa_officer',
            'admin', 'super_admin', 'SYSTEM_ADMIN',
        ]);

        if ($isOfficerRole) {
            // Officers: 4 hours (sensitive government work)
            return now()->addMinutes(config('sanctum.officer_expiration', 240));
        } else {
            // Applicants: 24 hours (may need to return and complete application)
            return now()->addMinutes(config('sanctum.expiration', 1440));
        }
    }

    /**
     * Create a token with role-based abilities and expiration
     */
    public function createTokenForUser(User $user, string $tokenName = 'auth_token'): string
    {
        $abilities = $this->getAbilitiesForRole($user->role);
        $expiresAt = $this->getTokenExpirationForRole($user->role);

        return $user->createToken(
            name: $tokenName,
            abilities: $abilities,
            expiresAt: $expiresAt
        )->plainTextToken;
    }

    /**
     * Check if a user's token has a specific ability
     */
    public function tokenHasAbility(User $user, string $ability): bool
    {
        $token = $user->currentAccessToken();
        
        if (!$token) {
            return false;
        }

        return $token->can($ability);
    }

    /**
     * Get human-readable ability descriptions
     */
    public function getAbilityDescriptions(): array
    {
        return [
            // Application abilities
            'application:read' => 'View applications',
            'application:create' => 'Create new applications',
            'application:update' => 'Update application details',
            'application:submit' => 'Submit applications for processing',
            'application:process' => 'Process applications',
            'application:review' => 'Review applications',
            'application:approve' => 'Approve applications',
            'application:deny' => 'Deny applications',
            'application:verify' => 'Verify application status',

            // Payment abilities
            'payment:initiate' => 'Initiate payments',
            'payment:view' => 'View payment details',
            'payment:read' => 'Read payment information',
            'payment:process' => 'Process payments',
            'payment:refund' => 'Process refunds',
            'payment:reconcile' => 'Reconcile payments',
            'payment:audit' => 'Audit payment records',

            // Case management abilities
            'case:manage' => 'Manage cases',
            'case:assign' => 'Assign cases to officers',
            'case:escalate' => 'Escalate cases',

            // Document abilities
            'document:upload' => 'Upload documents',
            'document:view' => 'View documents',

            // Note abilities
            'note:create' => 'Create internal notes',
            'note:read' => 'Read internal notes',

            // Profile abilities
            'profile:read' => 'View profile',
            'profile:update' => 'Update profile',

            // Report abilities
            'report:read' => 'View reports',
            'report:financial' => 'View financial reports',

            // Specialized abilities
            'entry:confirm' => 'Confirm entry at border',
            'entry:deny' => 'Deny entry at border',
            'visa:issue' => 'Issue visas',
            'visa:revoke' => 'Revoke visas',
            'boarding:verify' => 'Verify boarding eligibility',
            'verification:create' => 'Create verification records',
            'mission:manage' => 'Manage mission operations',
            'queue:manage' => 'Manage processing queues',
            'refund:create' => 'Create refund requests',
            'refund:approve' => 'Approve refund requests',
            'refund:process' => 'Process refunds',

            // 2FA abilities
            '2fa:setup' => 'Set up two-factor authentication',
            '2fa:verify' => 'Verify two-factor authentication',

            // Admin abilities
            '*' => 'Full system access',
        ];
    }

    /**
     * Validate that a token has required ability, throw 403 if not
     */
    public function requireAbility(User $user, string $ability): void
    {
        if (!$this->tokenHasAbility($user, $ability)) {
            abort(403, "Insufficient permissions. Required ability: {$ability}");
        }
    }

    /**
     * Get token information for debugging/auditing
     */
    public function getTokenInfo(User $user): array
    {
        $token = $user->currentAccessToken();
        
        if (!$token) {
            return ['error' => 'No active token'];
        }

        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'expires_at' => $token->expires_at?->toISOString(),
            'created_at' => $token->created_at->toISOString(),
            'last_used_at' => $token->last_used_at?->toISOString(),
        ];
    }
}