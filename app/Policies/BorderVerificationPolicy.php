<?php

namespace App\Policies;

use App\Models\BoardingAuthorization;
use App\Models\User;

class BorderVerificationPolicy
{
    /**
     * Determine if the user can view any border verifications.
     */
    public function viewAny(User $user): bool
    {
        // Border officers, GIS officers, and admins can view verifications
        return in_array($user->role, [
            'border_officer',
            'immigration_officer',
            'gis_officer', 'gis_admin',
            'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN',
            'admin',
        ]);
    }

    /**
     * Determine if the user can view the border verification.
     */
    public function view(User $user, BoardingAuthorization $authorization): bool
    {
        // Border officers, GIS officers, and admins can view verifications
        return in_array($user->role, [
            'border_officer',
            'immigration_officer',
            'gis_officer', 'gis_admin',
            'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN',
            'admin',
        ]);
    }

    /**
     * Determine if the user can create border verifications.
     */
    public function create(User $user): bool
    {
        // Only border/immigration officers can create verifications
        return in_array($user->role, [
            'border_officer',
            'immigration_officer',
        ]);
    }

    /**
     * Determine if the user can update the border verification.
     */
    public function update(User $user, BoardingAuthorization $authorization): bool
    {
        // Border officers can update verifications they created
        if (in_array($user->role, ['border_officer', 'immigration_officer'])) {
            return $authorization->verified_by_user_id === $user->id;
        }

        // Admins can update any verification
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete the border verification.
     */
    public function delete(User $user, BoardingAuthorization $authorization): bool
    {
        // Only admins can delete border verifications
        return $user->isAdmin();
    }

    /**
     * Determine if the user can verify travel documents (generate BAC).
     */
    public function verifyTravel(User $user): bool
    {
        // Border/immigration officers can verify travel documents
        return in_array($user->role, [
            'border_officer',
            'immigration_officer',
        ]);
    }

    /**
     * Determine if the user can confirm entry at the border.
     */
    public function confirmEntry(User $user): bool
    {
        // Only immigration officers can confirm entry
        return in_array($user->role, [
            'immigration_officer',
            'admin',
        ]);
    }

    /**
     * Determine if the user can view border statistics.
     */
    public function viewStatistics(User $user): bool
    {
        // Border officers, GIS officers, and admins can view statistics
        return in_array($user->role, [
            'border_officer',
            'immigration_officer',
            'gis_officer', 'gis_admin',
            'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN',
            'admin',
        ]);
    }

    /**
     * Determine if the user can access offline cache for border verification.
     */
    public function accessOfflineCache(User $user): bool
    {
        // Border/immigration officers can access offline cache
        return in_array($user->role, [
            'border_officer',
            'immigration_officer',
        ]);
    }
}
