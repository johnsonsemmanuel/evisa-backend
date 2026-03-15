<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureAgencyAssigned Middleware
 * 
 * SECURITY: Ensures officer-role users have proper agency/mission assignments
 * before accessing protected resources.
 * 
 * NIST SP 800-53 AC-3 (Access Enforcement) compliance:
 * - Verifies organizational affiliation before granting access
 * - Prevents orphaned accounts from accessing sensitive data
 * - Enforces data isolation at the authentication layer
 */
class EnsureAgencyAssigned
{
    /**
     * Handle an incoming request.
     * 
     * Validates that officer-role users have the required agency/mission assignments.
     * Blocks access if assignments are missing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip check for unauthenticated users (let auth middleware handle)
        if (!$user) {
            return $next($request);
        }

        // Skip check for applicants (they don't need agency assignment)
        if ($user->role === 'applicant') {
            return $next($request);
        }

        // Skip check for admins and super_admins (they have unrestricted access)
        if (in_array($user->role, ['admin', 'super_admin', 'SYSTEM_ADMIN'])) {
            return $next($request);
        }

        // Check GIS officers have agency assignment
        if ($this->isGisOfficer($user)) {
            if (empty($user->agency) || $user->agency !== 'GIS') {
                Log::warning('GIS officer attempted access without agency assignment', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'agency' => $user->agency,
                    'ip_address' => $request->ip(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'Your account has not been assigned to an agency. Please contact your administrator.',
                    'error_code' => 'AGENCY_NOT_ASSIGNED',
                ], 403);
            }
        }

        // Check MFA officers have both agency AND mission assignment
        if ($this->isMfaOfficer($user)) {
            if (empty($user->agency) || $user->agency !== 'MFA') {
                Log::warning('MFA officer attempted access without agency assignment', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'agency' => $user->agency,
                    'ip_address' => $request->ip(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'Your account has not been assigned to an agency. Please contact your administrator.',
                    'error_code' => 'AGENCY_NOT_ASSIGNED',
                ], 403);
            }

            // MFA officers MUST have a mission assignment (unless they're MFA admin)
            if (!$this->isMfaAdmin($user) && empty($user->mfa_mission_id)) {
                Log::warning('MFA officer attempted access without mission assignment', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'agency' => $user->agency,
                    'mfa_mission_id' => $user->mfa_mission_id,
                    'ip_address' => $request->ip(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'Your account has not been assigned to a mission/embassy. Please contact your administrator.',
                    'error_code' => 'MISSION_NOT_ASSIGNED',
                ], 403);
            }
        }

        // Check finance officers have agency assignment
        if ($user->role === 'finance_officer') {
            if (empty($user->agency)) {
                Log::warning('Finance officer attempted access without agency assignment', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'agency' => $user->agency,
                    'ip_address' => $request->ip(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'Your account has not been assigned to an agency. Please contact your administrator.',
                    'error_code' => 'AGENCY_NOT_ASSIGNED',
                ], 403);
            }
        }

        // Check border officers have agency assignment
        if ($user->role === 'border_officer') {
            if (empty($user->agency)) {
                Log::warning('Border officer attempted access without agency assignment', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'agency' => $user->agency,
                    'ip_address' => $request->ip(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'Your account has not been assigned to an agency. Please contact your administrator.',
                    'error_code' => 'AGENCY_NOT_ASSIGNED',
                ], 403);
            }
        }

        // Check visa officers have agency assignment
        if ($user->role === 'visa_officer') {
            if (empty($user->agency)) {
                Log::warning('Visa officer attempted access without agency assignment', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'agency' => $user->agency,
                    'ip_address' => $request->ip(),
                    'route' => $request->route()?->getName(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'Your account has not been assigned to an agency. Please contact your administrator.',
                    'error_code' => 'AGENCY_NOT_ASSIGNED',
                ], 403);
            }
        }

        // All checks passed
        return $next($request);
    }

    /**
     * Check if user is a GIS officer
     */
    private function isGisOfficer($user): bool
    {
        return in_array($user->role, [
            'gis_officer',
            'gis_reviewer',
            'gis_approver',
            'gis_admin',
            'GIS_REVIEWING_OFFICER',
            'GIS_APPROVAL_OFFICER',
            'GIS_ADMIN',
        ]);
    }

    /**
     * Check if user is an MFA officer
     */
    private function isMfaOfficer($user): bool
    {
        return in_array($user->role, [
            'mfa_reviewer',
            'mfa_approver',
            'mfa_admin',
            'MFA_REVIEWING_OFFICER',
            'MFA_APPROVAL_OFFICER',
            'MFA_ADMIN',
        ]);
    }

    /**
     * Check if user is an MFA admin (doesn't need mission assignment)
     */
    private function isMfaAdmin($user): bool
    {
        return in_array($user->role, ['mfa_admin', 'MFA_ADMIN']);
    }
}
