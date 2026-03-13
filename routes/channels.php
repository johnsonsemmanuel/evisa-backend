<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to control if a user can listen to the channel.
|
*/

// User-specific channel for applicants
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// GIS dashboard channel
Broadcast::channel('dashboard.gis', function ($user) {
    return $user->hasRole(['gis_officer', 'gis_admin', 'admin']);
});

// MFA dashboard channel
Broadcast::channel('dashboard.mfa', function ($user) {
    return $user->hasRole(['mfa_reviewer', 'mfa_admin', 'admin']);
});

// MFA mission-specific dashboard channel
Broadcast::channel('dashboard.mfa.mission.{missionId}', function ($user, $missionId) {
    return $user->hasRole(['mfa_reviewer', 'mfa_admin', 'admin']) && 
           ($user->isAdmin() || $user->mfa_mission_id == $missionId);
});

// Admin dashboard channel
Broadcast::channel('dashboard.admin', function ($user) {
    return $user->hasRole(['admin']);
});

// Admin payments channel
Broadcast::channel('payments.admin', function ($user) {
    return $user->hasRole(['admin']);
});