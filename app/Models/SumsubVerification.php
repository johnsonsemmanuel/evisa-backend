<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SumsubVerification extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'application_id',
        'eta_application_id',
        'applicant_id',
        'external_user_id',
        'verification_status',
        'review_result',
        'review_reject_type',
        'review_reject_details',
        'verification_data',
        'submitted_at',
        'reviewed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'review_reject_details' => 'array',
        'verification_data' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the visa application associated with this verification.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the ETA application associated with this verification.
     */
    public function etaApplication(): BelongsTo
    {
        return $this->belongsTo(EtaApplication::class);
    }

    /**
     * Check if verification is completed.
     */
    public function isCompleted(): bool
    {
        return $this->verification_status === 'completed';
    }

    /**
     * Check if verification is approved.
     */
    public function isApproved(): bool
    {
        return $this->review_result === 'approved';
    }

    /**
     * Check if verification is rejected.
     */
    public function isRejected(): bool
    {
        return $this->review_result === 'rejected';
    }

    /**
     * Check if verification is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->verification_status, ['pending', 'queued']) || 
               in_array($this->review_result, ['pending', 'init']);
    }

    /**
     * Get the related application (visa or ETA).
     */
    public function getRelatedApplication()
    {
        if ($this->application_id) {
            return $this->application;
        }
        
        if ($this->eta_application_id) {
            return $this->etaApplication;
        }
        
        return null;
    }

    /**
     * Scope to get verifications for a specific application type.
     */
    public function scopeForApplicationType($query, string $type)
    {
        if ($type === 'visa') {
            return $query->whereNotNull('application_id');
        }
        
        if ($type === 'eta') {
            return $query->whereNotNull('eta_application_id');
        }
        
        return $query;
    }

    /**
     * Scope to get pending verifications.
     */
    public function scopePending($query)
    {
        return $query->whereIn('verification_status', ['pending', 'queued'])
                    ->orWhereIn('review_result', ['pending', 'init']);
    }

    /**
     * Scope to get completed verifications.
     */
    public function scopeCompleted($query)
    {
        return $query->where('verification_status', 'completed');
    }

    /**
     * Scope to get approved verifications.
     */
    public function scopeApproved($query)
    {
        return $query->where('review_result', 'approved');
    }

    /**
     * Scope to get rejected verifications.
     */
    public function scopeRejected($query)
    {
        return $query->where('review_result', 'rejected');
    }
}