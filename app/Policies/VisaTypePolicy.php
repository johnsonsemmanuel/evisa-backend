<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisaType;

class VisaTypePolicy
{
    /**
     * Determine if the user can view any visa types.
     */
    public function viewAny(User $user): bool
    {
        // Everyone can view visa types (public information)
        return true;
    }

    /**
     * Determine if the user can view the visa type.
     */
    public function view(User $user, VisaType $visaType): bool
    {
        // Everyone can view visa types (public information)
        return true;
    }

    /**
     * Determine if the user can create visa types.
     */
    public function create(User $user): bool
    {
        // Only admins can create visa types
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update the visa type.
     */
    public function update(User $user, VisaType $visaType): bool
    {
        // Only admins can update visa types
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete the visa type.
     */
    public function delete(User $user, VisaType $visaType): bool
    {
        // Only admins can delete visa types
        return $user->isAdmin();
    }

    /**
     * Determine if the user can activate/deactivate visa types.
     */
    public function toggleActive(User $user, VisaType $visaType): bool
    {
        // Only admins can activate/deactivate visa types
        return $user->isAdmin();
    }
}
