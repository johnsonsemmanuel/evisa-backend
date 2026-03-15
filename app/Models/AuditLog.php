<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * AuditLog Model
 * 
 * Immutable audit log for government compliance.
 * Implements ISO 27001 A.8.15 (Logging) - audit logs cannot be modified or deleted.
 * 
 * SECURITY: Uses restricted database connection with INSERT + SELECT only.
 * 
 * @package App\Models
 */
class AuditLog extends Model
{
    use HasFactory;

    /**
     * Use the restricted audit database connection (or default in testing so in-memory SQLite has the table).
     */
    public function getConnectionName(): ?string
    {
        return app()->environment('testing') ? config('database.default') : 'audit';
    }

    /**
     * Disable updated_at - audit logs are write-once
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'application_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Prevent audit log deletion
     * 
     * @throws \LogicException
     */
    public function delete(): bool
    {
        throw new \LogicException('Audit logs cannot be deleted. They are immutable for compliance.');
    }

    /**
     * Prevent audit log force deletion
     * 
     * @throws \LogicException
     */
    public function forceDelete(): bool
    {
        throw new \LogicException('Audit logs cannot be deleted. They are immutable for compliance.');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * RELATIONSHIP 4: AuditLog → Application (many-to-one)
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter audit logs for a specific user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter audit logs for a specific application
     */
    public function scopeForApplication(Builder $query, int $applicationId): Builder
    {
        return $query->where('application_id', $applicationId);
    }

    /**
     * Scope: Filter audit logs by action type
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Filter recent audit logs
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Order by most recent first
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}

