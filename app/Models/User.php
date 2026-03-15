<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Casts\EncryptedString;
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
        'locale',
        'sumsub_applicant_id',
        'kyc_status',
        'kyc_completed_at',
        'kyc_rejection_labels',
        'kyc_level',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verification_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'is_active'               => 'boolean',
            'can_review'              => 'boolean',
            'can_approve'             => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_required'     => 'boolean',
            'sumsub_applicant_id'     => EncryptedString::class,
            'kyc_status'              => \App\Enums\KycStatus::class,
            'kyc_completed_at'        => 'datetime',
            'kyc_rejection_labels'    => 'array',
        ];
    }

    // ── Relationships ─────────────────────────────────────

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'user_id');
    }

    public function assignedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'assigned_officer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class, 'user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(MfaMission::class, 'mfa_mission_id');
    }

    /**
     * RELATIONSHIP 7: User → Role (many-to-many RBAC)
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('assigned_by', 'assigned_at', 'expires_at')
            ->withTimestamps();
    }

    /**
     * RELATIONSHIP 8: User → Agency (many-to-one)
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    /**
     * RELATIONSHIP 8: User → Mission (many-to-one) - for MFA officers
     * Note: This is an alias for the existing mission() relationship
     */
    public function assignedMission(): BelongsTo
    {
        // Check if missions table exists, otherwise use mfa_missions
        $missionClass = class_exists(Mission::class) ? Mission::class : MfaMission::class;
        return $this->belongsTo($missionClass, 'mission_id');
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
        return in_array($this->role, [
            'gis_officer', 'gis_reviewer', 'gis_approver',
            'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER'
        ]);
    }

    public function isGisReviewer(): bool
    {
        return in_array($this->role, ['gis_reviewer', 'GIS_REVIEWING_OFFICER']) 
            || ($this->role === 'gis_officer' && $this->can_review);
    }

    public function isGisApprover(): bool
    {
        return in_array($this->role, ['gis_approver', 'GIS_APPROVAL_OFFICER']) 
            || ($this->role === 'gis_officer' && $this->can_approve);
    }

    public function isGisAdmin(): bool
    {
        return in_array($this->role, ['gis_admin', 'GIS_ADMIN']);
    }

    public function isMfaReviewer(): bool
    {
        return in_array($this->role, ['mfa_reviewer', 'MFA_REVIEWING_OFFICER']) 
            || ($this->isMfaOfficer() && $this->can_review);
    }

    public function isMfaApprover(): bool
    {
        return in_array($this->role, ['mfa_approver', 'MFA_APPROVAL_OFFICER']) 
            || ($this->isMfaOfficer() && $this->can_approve);
    }

    public function isMfaOfficer(): bool
    {
        return in_array($this->role, [
            'mfa_reviewer', 'mfa_approver',
            'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER'
        ]);
    }

    public function isMfaAdmin(): bool
    {
        return in_array($this->role, ['mfa_admin', 'MFA_ADMIN']);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canReviewApplications(): bool
    {
        return $this->can_review || in_array($this->role, [
            'gis_admin', 'gis_reviewer', 'gis_officer',
            'GIS_ADMIN', 'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER',
            'mfa_admin', 'mfa_reviewer',
            'MFA_ADMIN', 'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER',
            'admin'
        ]);
    }

    public function canApproveApplications(): bool
    {
        return $this->can_approve || in_array($this->role, [
            'gis_admin', 'gis_approver',
            'GIS_ADMIN', 'GIS_APPROVAL_OFFICER',
            'mfa_admin', 'mfa_approver',
            'MFA_ADMIN', 'MFA_APPROVAL_OFFICER',
            'admin'
        ]);
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

    /**
     * RBAC: Check if user has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('permissions.name', $permissionName)
                    ->where('permissions.is_active', true);
            })
            ->exists();
    }

    /**
     * RBAC: Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * RBAC: Check if user has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * RBAC: Assign role to user
     */
    public function assignRole(Role $role, ?int $assignedBy = null): void
    {
        if (!$this->roles()->where('role_id', $role->id)->exists()) {
            $this->roles()->attach($role->id, [
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]);
        }
    }

    /**
     * RBAC: Remove role from user
     */
    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
    }

    /**
     * RBAC: Sync user roles
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
    }
}
