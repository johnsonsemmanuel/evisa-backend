<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtaApplication extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'reference_number',
        'taid',
        'user_id',
        'first_name_encrypted',
        'last_name_encrypted',
        'date_of_birth',
        'gender',
        'nationality_encrypted',
        'passport_number_encrypted',
        'passport_issue_date',
        'passport_expiry_date',
        'issuing_authority',
        'passport_scan_path',
        'photo_path',
        'email_encrypted',
        'phone_encrypted',
        'residential_address_encrypted',
        'intended_arrival_date',
        'port_of_entry',
        'airline',
        'flight_number',
        'address_in_ghana_encrypted',
        'host_name',
        'host_phone',
        'hotel_booking_path',
        'denied_entry_before',
        'criminal_conviction',
        'previous_ghana_visa',
        'travel_history',
        'eta_number',
        'qr_code',
        'status',
        'screening_notes',
        'validity_days',
        'entry_type',
        'fee_amount',
        'payment_status',
        'payment_reference',
        'approved_at',
        'expires_at',
        'valid_from',
        'valid_until',
        'entry_date',
        'port_of_entry_actual',
        'entry_officer_id',
        'passport_verification_status',
        'passport_verification_data',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
            'intended_arrival_date' => 'date',
            'denied_entry_before' => 'boolean',
            'criminal_conviction' => 'boolean',
            'previous_ghana_visa' => 'boolean',
            'fee_amount' => 'integer',  // pesewas (BIGINT)
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'entry_date' => 'datetime',
            'validity_days' => 'integer',
            'screening_notes' => 'array',
            'passport_verification_data' => 'array',
            'status' => \App\Enums\EtaStatus::class,
            'entry_type' => \App\Enums\EntryType::class,
            'sumsub_review_result' => \App\Enums\SumsubReviewResult::class,
            'sumsub_verification_status' => \App\Enums\SumsubVerificationStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the travel authorization (TAID) for this ETA
     */
    public function travelAuthorization(): BelongsTo
    {
        return $this->belongsTo(TravelAuthorization::class, 'taid', 'taid');
    }


    public function scopePending($query)
    {
        return $query->where('status', \App\Enums\EtaStatus::Pending->value);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', \App\Enums\EtaStatus::Approved->value);
    }

    /**
     * @deprecated Use isIssued() instead. Status changed from 'approved' to 'issued' per spec.
     */
    public function isApproved(): bool
    {
        return in_array($this->status, [
            \App\Enums\EtaStatus::Approved,
            \App\Enums\EtaStatus::Issued
        ]);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', \App\Enums\EtaStatus::Issued->value);
    }

    public function scopeFlagged($query)
    {
        return $query->where('status', \App\Enums\EtaStatus::Flagged->value);
    }

    /**
     * Scope: Filter active (issued/approved) ETAs that haven't expired
     */
    public function scopeActiveAuthorizations($query)
    {
        return $query->whereIn('status', [
                    \App\Enums\EtaStatus::Issued->value,
                    \App\Enums\EtaStatus::Approved->value
                ])
                     ->where(function($q) {
                         $q->where('expires_at', '>', now())
                           ->orWhereNull('expires_at');
                     });
    }

    public function isIssued(): bool
    {
        return $this->status === \App\Enums\EtaStatus::Issued;
    }

    public function isFlagged(): bool
    {
        return $this->status === \App\Enums\EtaStatus::Flagged;
    }

    public function isUsed(): bool
    {
        return $this->status === \App\Enums\EtaStatus::Used;
    }


    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Generate unique ETA reference number
     */
    public static function generateReferenceNumber(): string
    {
        $prefix = 'GH-ETA';
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "{$prefix}-{$year}-{$random}";
    }

    /**
     * Generate ETA number upon approval in format: GH-ETA-YYYYMMDD-XXXX
     */
    public function generateEtaNumber(): string
    {
        do {
            $date = date('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $etaNumber = "GH-ETA-{$date}-{$random}";
            
            // Ensure uniqueness
            $exists = self::where('eta_number', $etaNumber)->exists();
        } while ($exists);
        
        $this->eta_number = $etaNumber;
        $this->save();
        
        return $this->eta_number;
    }
}
