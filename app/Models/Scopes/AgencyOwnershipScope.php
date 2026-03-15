<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * AgencyOwnershipScope - LAYER 4 Defense: Officer Data Isolation
 * 
 * Automatically filters Application queries based on officer role and agency/mission assignment.
 * Ensures officers only see applications within their jurisdiction.
 * 
 * SECURITY RULES:
 * - GIS officers: Only applications where assigned_agency = 'gis'
 * - MFA officers: Only applications where assigned_agency = 'mfa' AND owner_mission_id = user's mission
 * - Finance officers: All applications (payment access only)
 * - Border officers: Only applications with status IN ('approved', 'issued')
 * - Admins: No restrictions
 */
class AgencyOwnershipScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply to authenticated users
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // GIS Officers: Only see GIS-assigned applications
        if (in_array($user->role, ['gis_officer', 'gis_reviewer', 'gis_approver', 'gis_admin', 'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN'])) {
            $builder->where('assigned_agency', 'gis');
            return;
        }

        // MFA Officers: Only see applications assigned to their mission
        if (in_array($user->role, ['mfa_reviewer', 'mfa_approver', 'mfa_admin', 'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER', 'MFA_ADMIN'])) {
            $builder->where('assigned_agency', 'mfa');
            
            // Further restrict to their specific mission (unless they're MFA admin)
            if (!in_array($user->role, ['mfa_admin', 'MFA_ADMIN']) && $user->mfa_mission_id) {
                $builder->where('owner_mission_id', $user->mfa_mission_id);
            }
            return;
        }

        // Border Officers: Only see approved/issued applications
        if ($user->role === 'border_officer') {
            $builder->whereIn('status', ['approved', 'issued']);
            return;
        }

        // Finance Officers: Can see all applications (for payment reconciliation)
        // But they should use PaymentPolicy to restrict actual payment operations
        if ($user->role === 'finance_officer') {
            // No restrictions - they need to see all applications for payment purposes
            return;
        }

        // Airline Staff: Only see approved/issued applications (for boarding verification)
        if ($user->role === 'airline_staff') {
            $builder->whereIn('status', ['approved', 'issued']);
            return;
        }

        // Admins and other roles: No restrictions
    }
}
