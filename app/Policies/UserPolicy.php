<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        // Only admins can view user lists
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view the user profile.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can view any user profile
        return $user->isAdmin();
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        // Only admins can create new users
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile (limited fields)
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can update any user
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Only admins can delete users
        return $user->isAdmin();
    }

    /**
     * Determine if the user can activate/deactivate users.
     */
    public function toggleActive(User $user, User $model): bool
    {
        // Users cannot deactivate themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Only admins can activate/deactivate users
        return $user->isAdmin();
    }

    /**
     * Determine if the user can change user roles.
     */
    public function changeRole(User $user, User $model): bool
    {
        // Users cannot change their own role
        if ($user->id === $model->id) {
            return false;
        }

        // Only admins can change user roles
        return $user->isAdmin();
    }

    /**
     * Determine if the user can assign missions to MFA officers.
     */
    public function assignMission(User $user, User $model): bool
    {
        // Only admins can assign missions
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update their own password.
     */
    public function updatePassword(User $user, User $model): bool
    {
        // Users can only update their own password
        return $user->id === $model->id;
    }
}
