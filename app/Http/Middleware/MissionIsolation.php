<?php

namespace App\Http\Middleware;

use App\Models\Application;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Government-Grade Mission Isolation Middleware
 * Ensures officers can only access applications assigned to their mission.
 * Implements Zero Trust principle for data access.
 */
class MissionIsolation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }

        // Only apply to officer roles
        if (!$this->isOfficerRole($user->role)) {
            return $next($request);
        }

        // Get application from route
        $application = $request->route('application');
        
        if ($application instanceof Application) {
            // Verify mission isolation
            if (!$this->canAccessApplication($user, $application)) {
                \Log::channel('security')->warning('Mission isolation violation attempted', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'user_mission_id' => $user->mfa_mission_id,
                    'application_id' => $application->id,
                    'application_mission_id' => $application->mfa_mission_id,
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Access denied. This application is not assigned to your mission.',
                    'error' => 'MISSION_ISOLATION_VIOLATION',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Check if user role is an officer role.
     */
    protected function isStaffRole(string $role): bool
    {
        return in_array($role, [
            'gis_officer',
            'gis_reviewer',
            'gis_approver',
            'gis_admin',
            'mfa_reviewer',
            'mfa_approver',
            'mfa_admin',
            'admin',
        ]);
    }

    /**
     * Check if user can access the application.
     */
    protected function canAccessApplication($user, Application $application): bool
    {
        // Admins can access all applications
        if (in_array($user->role, ['admin', 'super_admin', 'gis_admin', 'mfa_admin'])) {
            return true;
        }

        // GIS officers can access GIS-assigned applications
        if (str_starts_with($user->role, 'gis_')) {
            return $application->assigned_agency === 'gis';
        }

        // MFA officers can only access applications assigned to their mission
        if (str_starts_with($user->role, 'mfa_')) {
            // If user has a mission assignment, enforce it
            if ($user->mfa_mission_id) {
                return $application->mfa_mission_id === $user->mfa_mission_id;
            }
            // MFA HQ officers can access all MFA applications
            return $application->assigned_agency === 'mfa';
        }

        return false;
    }
}
