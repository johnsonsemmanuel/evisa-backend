<?php

use App\Models\Application;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| CRITICAL: Channel authorization prevents any authenticated user from
| listening to private channels they are not allowed to access.
|
*/

// Private channel for application — only the applicant OR assigned officers (CRITICAL: prevents unauthorized listeners)
Broadcast::channel('application.{applicationId}', function (User $user, int $applicationId) {
    $application = Application::withoutGlobalScopes()->find($applicationId);

    if (!$application) {
        return false;
    }

    $roleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

    // Applicant can listen to their own application (Application uses user_id for applicant)
    if ($roleValue === 'applicant' && $application->user_id === $user->id) {
        return ['id' => $user->id, 'role' => 'applicant'];
    }

    // Assigned officer can listen
    if ($application->assigned_officer_id === $user->id) {
        return ['id' => $user->id, 'role' => $roleValue];
    }

    // Reviewing/approval officer can listen
    if (($application->reviewing_officer_id !== null && $application->reviewing_officer_id === $user->id)
        || ($application->approval_officer_id !== null && $application->approval_officer_id === $user->id)) {
        return ['id' => $user->id, 'role' => $roleValue];
    }

    // GIS/MFA officers can listen to applications in their agency scope
    $agency = $application->assigned_agency;
    $agencyValue = $agency && method_exists($agency, 'value') ? $agency->value : (string) $agency;
    if (in_array($roleValue, ['gis_officer', 'gis_reviewer', 'gis_approver', 'gis_admin', 'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN'], true) && $agencyValue === 'gis') {
        return ['id' => $user->id, 'role' => $roleValue];
    }
    if (in_array($roleValue, ['mfa_reviewer', 'mfa_approver', 'mfa_admin', 'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER', 'MFA_ADMIN'], true) && $agencyValue === 'mfa') {
        if ($user->mfa_mission_id && $application->owner_mission_id && $user->mfa_mission_id !== $application->owner_mission_id) {
            return false;
        }
        return ['id' => $user->id, 'role' => $roleValue];
    }

    // Admin can listen to any application
    if ($roleValue === 'admin') {
        return ['id' => $user->id, 'role' => $roleValue];
    }

    return false;
});

// Private channel for an officer — only that officer
Broadcast::channel('officer.{officerId}', function (User $user, int $officerId) {
    return (int) $user->id === (int) $officerId;
});

// Presence channel for border station — officers at that station (station_id on user if present)
Broadcast::channel('border-station.{stationId}', function (User $user, int $stationId) {
    $roleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    if (!in_array($roleValue, ['immigration_officer', 'border_officer', 'airline_staff', 'admin'], true)) {
        return false;
    }
    $userStationId = $user->station_id ?? $user->border_station_id ?? null;
    if ($userStationId !== null && (int) $userStationId !== (int) $stationId) {
        return false;
    }
    return ['id' => $user->id, 'name' => $user->full_name ?? $user->first_name . ' ' . $user->last_name];
});

// Legacy dashboard channels (keep for existing listeners)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('dashboard.gis', function ($user) {
    return $user->hasRole(['gis_officer', 'gis_admin', 'admin']);
});

Broadcast::channel('dashboard.mfa', function ($user) {
    return $user->hasRole(['mfa_reviewer', 'mfa_admin', 'admin']);
});

Broadcast::channel('dashboard.mfa.mission.{missionId}', function ($user, $missionId) {
    return $user->hasRole(['mfa_reviewer', 'mfa_admin', 'admin'])
        && ($user->isAdmin() || $user->mfa_mission_id == $missionId);
});

Broadcast::channel('dashboard.admin', function ($user) {
    return $user->hasRole(['admin']);
});

Broadcast::channel('payments.admin', function ($user) {
    return $user->hasRole(['admin']);
});
