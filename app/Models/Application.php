<?php

namespace App\Models;

use App\Casts\EncryptedString;
use App\Models\Concerns\HasBlindIndex;
use App\Models\Scopes\AgencyOwnershipScope;
use App\Models\Scopes\ApplicantOwnershipScope;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Application extends Model
{
    use HasFactory, SoftDeletes, Auditable, HasBlindIndex;

    /**
     * The "booted" method of the model.
     * 
     * SECURITY: Register global scopes for IDOR protection (LAYER 3 & 4)
     */
    protected static function booted(): void
    {
        // LAYER 3: Applicant ownership enforcement
        static::addGlobalScope(new ApplicantOwnershipScope());
        
        // LAYER 4: Officer agency/mission isolation
        static::addGlobalScope(new AgencyOwnershipScope());
    }

    protected $fillable = [
        'reference_number',
        'taid',
        'user_id',
        'visa_type_id',
        'visa_channel',
        'entry_type',
        'mfa_mission_id',
        'current_queue',
        'first_name_encrypted',
        'last_name_encrypted',
        'date_of_birth_encrypted',
        'passport_number_encrypted',
        'nationality_encrypted',
        'email_encrypted',
        'phone_encrypted',
        'gender',
        'marital_status',
        'profession_encrypted',
        'country_of_birth',
        'passport_issue_date',
        'passport_expiry',
        'passport_issuing_authority',
        'intended_arrival',
        'duration_days',
        'address_in_ghana',
        'purpose_of_visit',
        'visited_other_countries',
        'visited_country_1',
        'visited_country_2',
        'visited_country_3',
        'current_step',
        'submitted_at',
        'sla_deadline',
        'decided_at',
        'decision_notes',
        'evisa_file_path',
        'processing_tier',
        'risk_screening_notes',
        'evisa_qr_code',
        'reviewed_at',
        'risk_reasons',
        'risk_last_updated',
        'risk_assessed_at',
        'service_tier_id',
        'total_fee',
        'government_fee',
        'platform_fee',
        'processing_fee',
        'health_good_condition',
        'health_recent_illness',
        'health_contact_infectious',
        'health_yellow_fever_vaccinated',
        'health_chronic_conditions',
        'health_condition_details',
        'health_declaration_travel_affected',
        'health_declaration_affected_countries',
        'owner_mission_id',
        'review_started_at',
        'review_completed_at',
        'approval_started_at',
        'approval_completed_at',
        'denial_reason_codes',
        'aeropass_transaction_ref',
        'aeropass_status',
        'aeropass_submitted_at',
        'aeropass_result_at',
        'aeropass_raw_result',
        'aeropass_retry_count',
    ];

    protected function casts(): array
    {
        return [
            // PII Encryption - AES-256 using EncryptedString cast
            'first_name_encrypted' => EncryptedString::class,
            'last_name_encrypted' => EncryptedString::class,
            'date_of_birth_encrypted' => EncryptedString::class,
            'passport_number_encrypted' => EncryptedString::class,
            'nationality_encrypted' => EncryptedString::class,
            'email_encrypted' => EncryptedString::class,
            'phone_encrypted' => EncryptedString::class,
            'profession_encrypted' => EncryptedString::class,
            
            'sumsub_review_result' => \App\Enums\SumsubReviewResult::class,
            'sumsub_verification_status' => \App\Enums\SumsubVerificationStatus::class,
            
            // Date/Time casts
            'intended_arrival' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry' => 'date',
            'submitted_at'     => 'datetime',
            'sla_deadline'     => 'datetime',
            'decided_at'       => 'datetime',
            'risk_assessed_at' => 'datetime',
            'risk_last_updated' => 'datetime',
            'review_started_at' => 'datetime',
            'review_completed_at' => 'datetime',
'approval_started_at' => 'datetime',
            'approval_completed_at' => 'datetime',
            'aeropass_submitted_at' => 'datetime',
            'aeropass_result_at' => 'datetime',

            // Other casts
            'watchlist_flagged' => 'boolean',
            'risk_reasons' => 'array',
            'denial_reason_codes' => 'array',
            'health_declaration_travel_affected' => 'boolean',
            'total_fee' => 'integer',        // pesewas (BIGINT)
            'government_fee' => 'integer',
            'platform_fee' => 'integer',
            'processing_fee' => 'integer',
        ];
    }

    protected $appends = [
        'first_name',
        'last_name',
        'date_of_birth',
        'passport_number',
        'nationality',
        'email',
        'phone',
        'profession',
    ];

    protected $hidden = [
        'first_name_encrypted',
        'last_name_encrypted',
        'date_of_birth_encrypted',
        'passport_number_encrypted',
        'nationality_encrypted',
        'email_encrypted',
        'phone_encrypted',
        'profession_encrypted',
    ];

    // ── Decrypted PII Accessors ──────────────────────────────
    // These accessors provide convenient access to encrypted fields
    // The EncryptedString cast handles the actual encryption/decryption

    public function getFirstNameAttribute(): ?string
    {
        return $this->first_name_encrypted;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->last_name_encrypted;
    }

    public function getDateOfBirthAttribute(): ?string
    {
        return $this->date_of_birth_encrypted;
    }

    public function getPassportNumberAttribute(): ?string
    {
        return $this->passport_number_encrypted;
    }

    public function getNationalityAttribute(): ?string
    {
        return $this->nationality_encrypted;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->email_encrypted;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->phone_encrypted;
    }

    public function getProfessionAttribute(): ?string
    {
        return $this->profession_encrypted;
    }

    // ── Relationships ─────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the travel authorization (TAID) for this application
     */
    public function travelAuthorization(): BelongsTo
    {
        return $this->belongsTo(TravelAuthorization::class, 'taid', 'taid');
    }


    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class, 'visa_type_id');
    }

    public function serviceTier(): BelongsTo
    {
        return $this->belongsTo(ServiceTier::class, 'service_tier_id');
    }

    public function riskAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class, 'application_id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function reviewingOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewing_officer_id');
    }

    public function approvalOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_officer_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(MfaMission::class, 'mfa_mission_id');
    }

    public function ownerMission(): BelongsTo
    {
        return $this->belongsTo(MfaMission::class, 'owner_mission_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'application_id');
    }

    /**
     * RELATIONSHIP 3: Get document by type (scoped query helper)
     */
    public function documentByType(string $type): ?ApplicationDocument
    {
        return $this->documents()->where('document_type', $type)->first();
    }

    /**
     * Get the denial reason codes for this application
     */
    public function denialReasons(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        // This is a pseudo-relationship since we store IDs in JSON
        // We'll use a helper method instead
        return $this->belongsToMany(ReasonCode::class, null, null, null, null, null, 'denial_reason_codes');
    }

    /**
     * Get the denial reason code objects
     */
    /**
     * Get the denial reason code objects
     */
    public function getDenialReasonObjects()
    {
        if (empty($this->denial_reason_codes)) {
            return collect();
        }

        return ReasonCode::whereIn('code', $this->denial_reason_codes)->get();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'application_id')->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'application_id');
    }

    /**
     * RELATIONSHIP 2: Get successful payment (status = 'paid')
     */
    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'application_id')->where('status', 'paid')->latestOfMany();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'application_id')->orderBy('created_at', 'desc');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class, 'application_id')->orderBy('created_at', 'desc');
    }

    public function interpolCheck(): HasOne
    {
        return $this->hasOne(InterpolCheck::class, 'application_id');
    }

    /**
     * RELATIONSHIP 4: Application → AuditLog (one-to-many)
     * Get audit logs specifically for this application
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'application_id')->orderBy('created_at', 'asc');
    }

    /**
     * RELATIONSHIP 5: Application → RiskScore (one-to-many with history)
     * Get all risk scores for this application (history)
     */
    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class, 'application_id')->orderBy('assessed_at', 'desc');
    }

    /**
     * RELATIONSHIP 5: Get current risk score
     */
    public function currentRiskScore(): HasOne
    {
        return $this->hasOne(RiskScore::class, 'application_id')->where('is_current', true);
    }

    /**
     * RELATIONSHIP 9: BorderVerification → Application (one-to-one)
     */
    public function borderVerification(): HasOne
    {
        return $this->hasOne(BorderVerification::class, 'application_id');
    }

    // ── State Machine Methods ────────────────────────────

    /**
     * Transition the application to a new status with validation.
     *
     * Accepts either an ApplicationStatus enum or a plain string.
     *
     * @throws \App\Exceptions\InvalidStatusTransitionException
     */
    public function transitionTo(\App\Enums\ApplicationStatus|string $newStatus): void
    {
        $newEnum = $newStatus instanceof \App\Enums\ApplicationStatus
            ? $newStatus
            : \App\Enums\ApplicationStatus::tryFrom($newStatus);

        if (!$newEnum) {
            throw new \InvalidArgumentException("Invalid application status: {$newStatus}");
        }

        $currentEnum = \App\Enums\ApplicationStatus::tryFrom($this->status);

        if (!$currentEnum || !$currentEnum->canTransitionTo($newEnum)) {
            throw new \App\Exceptions\InvalidStatusTransitionException(
                "Cannot transition from {$this->status} to {$newEnum->value}"
            );
        }

        $oldStatusValue = $this->status;
        $this->forceFill(['status' => $newEnum->value])->save();

        $this->statusHistory()->create([
            'from_status' => $oldStatusValue,
            'to_status' => $newEnum->value,
            'changed_by' => auth()->id(),
            'notes' => "Status changed from {$currentEnum->label()} to {$newEnum->label()}",
        ]);

        $this->handleStatusTransition($newEnum);
    }

    private function handleStatusTransition(\App\Enums\ApplicationStatus $to): void
    {
        match($to) {
            \App\Enums\ApplicationStatus::Submitted => $this->handleSubmission(),
            \App\Enums\ApplicationStatus::UnderReview => $this->handleReviewStart(),
            \App\Enums\ApplicationStatus::Approved => $this->handleApproval(),
            \App\Enums\ApplicationStatus::Rejected => $this->handleRejection(),
            \App\Enums\ApplicationStatus::VisaIssued => $this->handleVisaIssuance(),
            default => null,
        };
    }

    private function handleSubmission(): void
    {
        $this->update([
            'submitted_at' => now(),
            'sla_deadline' => now()->addBusinessDays($this->visaType->processing_days ?? 5),
        ]);

        // Trigger risk assessment
        if (config('services.risk_scoring.enabled')) {
            \App\Jobs\ProcessRiskAssessment::dispatch($this);
        }
    }

    private function handleReviewStart(): void
    {
        $this->update(['review_started_at' => now()]);
    }

    private function handleApproval(): void
    {
        $this->update([
            'decided_at' => now(),
            'approval_completed_at' => now(),
        ]);

        // Generate e-visa document
        \App\Jobs\GenerateEVisaPdf::dispatch($this);
    }

    private function handleRejection(): void
    {
        $this->update(['decided_at' => now()]);
        
        // Send rejection notification
        $this->user->notify(new \App\Notifications\ApplicationRejectedNotification($this));
    }

    private function handleVisaIssuance(): void
    {
        // Create travel authorization
        if (!$this->travelAuthorization) {
            \App\Models\TravelAuthorization::create([
                'taid' => $this->taid,
                'application_id' => $this->id,
                'authorization_type' => \App\Enums\AuthorizationType::VISA,
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => now()->addDays($this->visaType->validity_days ?? 90),
            ]);
        }
    }

    // ── Status Helper Methods ─────────────────────────────

    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status, ['submitted', 'pending_documents']);
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, ['under_review', 'approved_pending']);
    }

    public function canBeRejected(): bool
    {
        return !in_array($this->status, ['approved', 'rejected', 'withdrawn', 'visa_issued', 'expired']);
    }

    public function isInProgress(): bool
    {
        return !in_array($this->status, ['approved', 'rejected', 'withdrawn', 'visa_issued', 'expired', 'draft']);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['approved', 'rejected', 'withdrawn', 'visa_issued', 'expired']);
    }

    public static function generateReferenceNumber(): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        return "GH-EV-{$date}-{$random}";
    }

    public function isPaid(): bool
    {
        return $this->payment && $this->payment->status === 'paid';
    }

    public function isWithinSla(): bool
    {
        if (!$this->sla_deadline) {
            return true;
        }
        return now()->lt($this->sla_deadline);
    }

    public function slaHoursRemaining(): ?float
    {
        if (!$this->sla_deadline) {
            return null;
        }
        return max(0, now()->diffInHours($this->sla_deadline, false));
    }

    // ── Risk Scoring Helpers ──────────────────────────────

    public function getRiskLevelColorAttribute(): string
    {
        return match($this->risk_level) {
            'Low' => 'green',
            'Medium' => 'yellow',
            'High' => 'orange',
            'Critical' => 'red',
            default => 'gray'
        };
    }

    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, ['High', 'Critical']);
    }

    public function isCriticalRisk(): bool
    {
        return $this->risk_level === 'Critical';
    }

    public function needsRiskReassessment(): bool
    {
        if (!$this->risk_last_updated) {
            return true;
        }
        
        // Reassess if older than 24 hours
        return $this->risk_last_updated->lt(now()->subHours(24));
    }

    // ── Interpol Check Helpers ────────────────────────────

    public function hasInterpolCheck(): bool
    {
        return $this->interpolCheck !== null;
    }

    public function needsInterpolCheck(): bool
    {
        // Only check for submitted applications that don't have a check yet
        return in_array($this->status, ['submitted', 'under_review', 'pending_approval']) 
            && !$this->hasInterpolCheck();
    }

    public function isInterpolClear(): bool
    {
        return $this->interpolCheck && 
               $this->interpolCheck->isCompleted() && 
               !$this->interpolCheck->isMatched();
    }

    public function hasInterpolMatch(): bool
    {
        return $this->interpolCheck && 
               $this->interpolCheck->isCompleted() && 
               $this->interpolCheck->isMatched();
    }

    public function triggerInterpolCheck(): void
    {
        if (config('services.aeropass.enabled') && $this->needsInterpolCheck()) {
            \App\Jobs\ProcessInterpolCheck::dispatch($this->id);
        }
    }
    /**
     * Check if application was recently assigned to a specific agency.
     * Used for real-time dashboard updates.
     */
    public function wasRecentlyAssignedTo(string $agency): bool
    {
        return $this->statusHistory()
            ->where('created_at', '>=', now()->subMinutes(5))
            ->where('notes', 'like', "%assigned to {$agency}%")
            ->exists();
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter pending applications (submitted status)
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope: Filter applications for a specific agency
     */
    public function scopeForAgency(Builder $query, int $agencyId): Builder
    {
        return $query->where('agency_id', $agencyId);
    }

    /**
     * Scope: Filter applications for a specific applicant
     */
    public function scopeForApplicant(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter applications requiring payment
     */
    public function scopePaymentRequired(Builder $query): Builder
    {
        return $query->where('status', 'pending_payment');
    }

    /**
     * Scope: Filter high-risk applications
     */
    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->whereHas('currentRiskScore', function($q) {
            $q->whereIn('risk_level', ['high', 'critical']);
        });
    }

    /**
     * Scope: Filter applications assigned to GIS agency
     */
    public function scopeAssignedToGis($query)
    {
        return $query->where('assigned_agency', 'gis');
    }

    /**
     * Scope: Filter applications that are approved or issued
     */
    public function scopeApprovedOrIssued($query)
    {
        return $query->whereIn('status', ['approved', 'issued']);
    }

    /**
     * Scope: Filter completed applications (approved or denied)
     */
    public function scopeApprovedOrDenied($query)
    {
        return $query->whereIn('status', ['approved', 'denied']);
    }

    /**
     * Scope: Filter applications in review (submitted or under_review)
     */
    public function scopeInReview($query)
    {
        return $query->whereIn('status', ['submitted', 'under_review']);
    }

    /**
     * Scope: Filter applications pending decision (escalated or under_review)
     */
    public function scopePendingDecision($query)
    {
        return $query->whereIn('status', ['escalated', 'under_review']);
    }

    /**
     * Scope: Eager load commonly used relations (visaType, payment)
     */
    public function scopeWithBasicRelations($query)
    {
        return $query->with(['visaType', 'payment']);
    }

    /**
     * Scope: Eager load visa type and assigned officer details
     */
    public function scopeWithOfficerDetails($query)
    {
        return $query->with(['visaType', 'assignedOfficer:id,first_name,last_name']);
    }

    /**
     * Scope: Eager load visa type and workflow officers (reviewing, approval)
     */
    public function scopeWithWorkflowDetails($query)
    {
        return $query->with(['visaType', 'reviewingOfficer', 'approvalOfficer']);
    }

    /**
     * Scope: Eager load visa type and assigned officer for SLA monitoring
     */
    public function scopeWithSlaDetails($query)
    {
        return $query->with(['visaType', 'assignedOfficer']);
    }

    /**
     * Dispatch async Aeropass nominal check (202 + callback). Use during review workflow.
     */
    public function dispatchAeropassCheck(): void
    {
        \App\Jobs\ProcessAeropassNominalCheck::dispatch($this)->onQueue('critical');
    }
}

