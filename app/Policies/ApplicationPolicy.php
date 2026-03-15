<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Determine if the user can view any applications.
     */
    public function viewAny(User $user): bool
    {
        // Applicants can view their own applications
        // Officers and admins can view applications in their queue
        return in_array($user->role, [
            'applicant',
            'gis_officer', 'gis_reviewer', 'gis_approver', 'gis_admin',
            'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN',
            'mfa_reviewer', 'mfa_approver', 'mfa_admin',
            'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER', 'MFA_ADMIN',
            'visa_officer',
            'admin',
        ]);
    }

    /**
     * Determine if the user can view the application.
     */
    public function view(User $user, Application $application): bool
    {
        // Applicants can only view their own applications
        if ($user->isApplicant()) {
            return $application->user_id === $user->id;
        }

        // GIS officers can view applications assigned to GIS
        if ($user->isGisOfficer() || $user->isGisAdmin()) {
            return $application->assigned_agency === 'gis';
        }

        // MFA officers can only view applications assigned to their mission
        if ($user->isMfaOfficer() || $user->isMfaAdmin()) {
            return $application->assigned_agency === 'mfa' 
                && ($user->isAdmin() || $user->isMfaAdmin() || $application->mfa_mission_id === $user->mfa_mission_id);
        }

        // Admins can view all applications
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create applications.
     */
    public function create(User $user): bool
    {
        // Only applicants can create new applications
        return $user->isApplicant();
    }

    /**
     * Determine if the user can update the application.
     */
    public function update(User $user, Application $application): bool
    {
        // Applicants can only update their own draft or additional_info_requested applications
        if ($user->isApplicant()) {
            return $application->user_id === $user->id 
                && in_array($application->status, ['draft', 'additional_info_requested']);
        }

        // Officers cannot update application data directly
        return false;
    }

    /**
     * Determine if the user can delete the application.
     */
    public function delete(User $user, Application $application): bool
    {
        // Only applicants can delete their own draft applications
        if ($user->isApplicant()) {
            return $application->user_id === $user->id 
                && $application->status === 'draft';
        }

        // Admins can soft-delete any application
        return $user->isAdmin();
    }

    /**
     * Determine if the user can submit the application for review.
     */
    public function submit(User $user, Application $application): bool
    {
        // Only the applicant who owns the application can submit it
        return $user->isApplicant() 
            && $application->user_id === $user->id 
            && in_array($application->status, ['draft', 'additional_info_requested']);
    }

    /**
     * Determine if the user can review the application.
     */
    public function review(User $user, Application $application): bool
    {
        // GIS reviewers can review applications assigned to GIS
        if ($user->isGisReviewer() || $user->isGisAdmin()) {
            return $application->assigned_agency === 'gis' 
                && in_array($application->status, ['under_review', 'pending_review']);
        }

        // MFA reviewers can review applications assigned to their mission
        if ($user->isMfaReviewer() || $user->isMfaAdmin()) {
            return $application->assigned_agency === 'mfa' 
                && ($user->isAdmin() || $user->isMfaAdmin() || $application->mfa_mission_id === $user->mfa_mission_id)
                && in_array($application->status, ['escalated', 'under_review']);
        }

        return $user->isAdmin();
    }

    /**
     * Determine if the user can approve the application.
     */
    public function approve(User $user, Application $application): bool
    {
        // GIS approvers can approve applications in pending_approval status
        if ($user->isGisApprover() || $user->isGisAdmin()) {
            return $application->assigned_agency === 'gis' 
                && $application->status === 'pending_approval';
        }

        // MFA approvers can approve escalated applications
        if ($user->isMfaApprover() || $user->isMfaAdmin()) {
            return $application->assigned_agency === 'mfa' 
                && ($user->isAdmin() || $user->isMfaAdmin() || $application->mfa_mission_id === $user->mfa_mission_id)
                && $application->status === 'pending_approval';
        }

        return $user->isAdmin();
    }

    /**
     * Determine if the user can deny the application.
     */
    public function deny(User $user, Application $application): bool
    {
        // Same permissions as approve
        return $this->approve($user, $application);
    }

    /**
     * Determine if the user can escalate the application to MFA.
     */
    public function escalate(User $user, Application $application): bool
    {
        // GIS officers can escalate applications assigned to GIS
        if ($user->isGisOfficer() || $user->isGisAdmin()) {
            return $application->assigned_agency === 'gis' 
                && in_array($application->status, ['under_review', 'pending_approval']);
        }

        return $user->isAdmin();
    }

    /**
     * Determine if the user can assign the application to themselves.
     */
    public function assign(User $user, Application $application): bool
    {
        // GIS officers can assign GIS applications to themselves
        if ($user->isGisOfficer() || $user->isGisAdmin()) {
            return $application->assigned_agency === 'gis' 
                && $application->status === 'pending_review';
        }

        // MFA officers can assign MFA applications to themselves
        if ($user->isMfaOfficer() || $user->isMfaAdmin()) {
            return $application->assigned_agency === 'mfa' 
                && ($user->isAdmin() || $user->isMfaAdmin() || $application->mfa_mission_id === $user->mfa_mission_id)
                && $application->status === 'escalated';
        }

        return $user->isAdmin();
    }

    /**
     * Determine if the user can add internal notes to the application.
     */
    public function addNotes(User $user, Application $application): bool
    {
        // Officers can add notes to applications they can view
        return $this->view($user, $application) && !$user->isApplicant();
    }

    /**
     * Determine if the user can request additional information.
     */
    public function requestInfo(User $user, Application $application): bool
    {
        // Officers reviewing the application can request additional info
        return $this->review($user, $application);
    }

    /**
     * Determine if the user can download the eVisa document.
     */
    public function downloadEvisa(User $user, Application $application): bool
    {
        // Applicant can download their own issued eVisa
        if ($user->isApplicant()) {
            return $application->user_id === $user->id 
                && $application->status === 'issued';
        }

        // Officers and admins can download any issued eVisa
        return !$user->isApplicant() && $application->status === 'issued';
    }
}
