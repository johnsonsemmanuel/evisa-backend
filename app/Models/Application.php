<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\EncryptsPii;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes, Auditable, EncryptsPii;

    protected $encryptedFields = [
        'first_name_encrypted',
        'last_name_encrypted',
        'date_of_birth_encrypted',
        'passport_number_encrypted',
        'nationality_encrypted',
        'email_encrypted',
        'phone_encrypted',
        'profession_encrypted',
    ];

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
        'status',
        'tier',
        'assigned_agency',
        'assigned_officer_id',
        'current_step',
        'submitted_at',
        'sla_deadline',
        'decided_at',
        'decision_notes',
        'evisa_file_path',
        'processing_tier',
        'risk_screening_status',
        'risk_screening_notes',
        'evisa_qr_code',
        'reviewed_by_id',
        'reviewed_at',
        'risk_score',
        'risk_level',
        'risk_reasons',
        'risk_last_updated',
        'watchlist_flagged',
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
        'owner_mission_id',
        'current_queue',
        'reviewing_officer_id',
        'approval_officer_id',
        'review_started_at',
        'review_completed_at',
        'approval_started_at',
        'approval_completed_at',
        'denial_reason_codes',
    ];

    protected function casts(): array
    {
        return [
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
            'watchlist_flagged' => 'boolean',
            'risk_reasons' => 'array',
            'denial_reason_codes' => 'array',
            'total_fee' => 'decimal:2',
            'government_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'processing_fee' => 'decimal:2',
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

    public function getFirstNameAttribute(): ?string
    {
        return $this->getAttribute('first_name_encrypted');
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->getAttribute('last_name_encrypted');
    }

    public function getDateOfBirthAttribute(): ?string
    {
        return $this->getAttribute('date_of_birth_encrypted');
    }

    public function getPassportNumberAttribute(): ?string
    {
        return $this->getAttribute('passport_number_encrypted');
    }

    public function getNationalityAttribute(): ?string
    {
        return $this->getAttribute('nationality_encrypted');
    }

    public function getEmailAttribute(): ?string
    {
        return $this->getAttribute('email_encrypted');
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->getAttribute('phone_encrypted');
    }

    public function getProfessionAttribute(): ?string
    {
        return $this->getAttribute('profession_encrypted');
    }

    // ── Relationships ─────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->belongsTo(VisaType::class);
    }

    public function serviceTier(): BelongsTo
    {
        return $this->belongsTo(ServiceTier::class);
    }

    public function riskAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class);
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
        return $this->hasMany(ApplicationDocument::class);
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
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class)->orderBy('created_at', 'desc');
    }

    public function interpolCheck(): HasOne
    {
        return $this->hasOne(InterpolCheck::class);
    }

    // ── Helpers ───────────────────────────────────────────

    public static function generateReferenceNumber(): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        return "GH-EV-{$date}-{$random}";
    }

    public function isPaid(): bool
    {
        return $this->payment && $this->payment->status === 'completed';
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
}
