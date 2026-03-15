<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorderVerification extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'application_id',
        'verified_by',
        'verified_at',
        'verification_method',
        'aeropass_checked',
        'aeropass_result',
        'aeropass_details',
        'entry_status',
        'port_of_entry',
        'port_code',
        'biometric_verified',
        'document_verified',
        'notes',
        'refusal_reason',
        'refusal_code',
        'granted_duration_days',
        'authorized_until',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'aeropass_checked' => 'boolean',
            'biometric_verified' => 'boolean',
            'document_verified' => 'boolean',
            'granted_duration_days' => 'integer',
            'authorized_until' => 'date',
        ];
    }

    // ── Relationships ─────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ── Scopes ────────────────────────────────────────────

    public function scopeAdmitted($query)
    {
        return $query->where('entry_status', 'admitted');
    }

    public function scopeRefused($query)
    {
        return $query->where('entry_status', 'refused');
    }

    public function scopeByPort($query, string $port)
    {
        return $query->where('port_of_entry', $port);
    }

    public function scopeAeropassHits($query)
    {
        return $query->where('aeropass_checked', true)
            ->where('aeropass_result', 'hit');
    }

    public function scopeRecentVerifications($query, int $days = 7)
    {
        return $query->where('verified_at', '>=', now()->subDays($days));
    }

    // ── Helpers ───────────────────────────────────────────

    public function wasAdmitted(): bool
    {
        return $this->entry_status === 'admitted';
    }

    public function wasRefused(): bool
    {
        return $this->entry_status === 'refused';
    }

    public function hasAeropassHit(): bool
    {
        return $this->aeropass_checked && $this->aeropass_result === 'hit';
    }

    public function isFullyVerified(): bool
    {
        return $this->biometric_verified && $this->document_verified;
    }

    /**
     * Get verification status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->entry_status) {
            'admitted' => 'green',
            'refused' => 'red',
            'further_examination' => 'yellow',
            'deferred' => 'orange',
            default => 'gray'
        };
    }
}
