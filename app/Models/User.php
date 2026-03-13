<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, Auditable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'role',
        'agency',
        'mfa_mission_id',
        'can_review',
        'can_approve',
        'is_active',
        'locale',
        'email_verified_at',
        'email_verification_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'can_review'        => 'boolean',
            'can_approve'       => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function assignedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'assigned_officer_id');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(MfaMission::class, 'mfa_mission_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable_id')
            ->where('notifiable_type', \App\Models\User::class);
    }

    // ── Helpers ───────────────────────────────────────────

    public function isApplicant(): bool
    {
        return $this->role === 'applicant';
    }

    public function isGisOfficer(): bool
    {
        return in_array($this->role, ['gis_officer', 'gis_reviewer', 'gis_approver']);
    }

    public function isGisReviewer(): bool
    {
        return $this->role === 'gis_reviewer' || ($this->role === 'gis_officer' && $this->can_review);
    }

    public function isGisApprover(): bool
    {
        return $this->role === 'gis_approver' || ($this->role === 'gis_officer' && $this->can_approve);
    }

    public function isGisAdmin(): bool
    {
        return $this->role === 'gis_admin';
    }

    public function isMfaReviewer(): bool
    {
        return $this->role === 'mfa_reviewer' || ($this->isMfaOfficer() && $this->can_review);
    }

    public function isMfaApprover(): bool
    {
        return $this->role === 'mfa_approver' || ($this->isMfaOfficer() && $this->can_approve);
    }

    public function isMfaOfficer(): bool
    {
        return in_array($this->role, ['mfa_reviewer', 'mfa_approver']);
    }

    public function isMfaAdmin(): bool
    {
        return $this->role === 'mfa_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canReviewApplications(): bool
    {
        return $this->can_review || in_array($this->role, ['gis_admin', 'gis_reviewer', 'mfa_admin', 'mfa_reviewer', 'gis_officer', 'admin']);
    }

    public function canApproveApplications(): bool
    {
        return $this->can_approve || in_array($this->role, ['gis_admin', 'gis_approver', 'mfa_admin', 'mfa_approver', 'admin']);
    }

    public function canAccessMission(int $missionId): bool
    {
        // Admins can access all missions
        if ($this->isAdmin() || $this->isMfaAdmin()) {
            return true;
        }

        // MFA officers can only access their assigned mission
        if ($this->isMfaOfficer() || $this->isMfaReviewer() || $this->isMfaApprover()) {
            return $this->mfa_mission_id === $missionId;
        }

        return false;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
    /**
     * Check if user has any of the given roles.
     */
    public function hasRole($roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return in_array($this->role, $roles);
    }
}
