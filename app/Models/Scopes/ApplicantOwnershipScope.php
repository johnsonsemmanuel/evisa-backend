<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * ApplicantOwnershipScope - LAYER 3 Defense Against IDOR
 * 
 * Automatically filters Application queries to only return records owned by the authenticated applicant.
 * This provides defense-in-depth even if route binding or policy checks are bypassed.
 * 
 * SECURITY: This scope ONLY applies when the authenticated user has role 'applicant'.
 * Officers and admins are not restricted by this scope.
 */
class ApplicantOwnershipScope implements Scope
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

        // Only apply ownership filter for applicants
        // Officers and admins should see applications based on their agency/mission
        if ($user->role === 'applicant') {
            $builder->where('user_id', $user->id);
        }
    }
}
