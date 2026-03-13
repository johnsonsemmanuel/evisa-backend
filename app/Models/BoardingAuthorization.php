<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingAuthorization extends Model
{
    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'authorization_code';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'authorization_code',
        'passport_number',
        'nationality',
        'authorization_type',
        'eta_number',
        'visa_id',
        'verification_timestamp',
        'expiry_timestamp',
        'verified_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'verification_timestamp' => 'datetime',
        'expiry_timestamp' => 'datetime',
    ];

    /**
     * Generate a unique boarding authorization code.
     */
    public static function generateCode(): string
    {
        do {
            $date = date('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $code = "GH-BA-{$date}-{$random}";
        } while (self::where('authorization_code', $code)->exists());

        return $code;
    }

    /**
     * Check if the BAC is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_timestamp->isPast();
    }

    /**
     * Check if the BAC is valid (not expired).
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Get the user who verified this authorization.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Get the ETA application if this is an ETA authorization.
     */
    public function etaApplication(): BelongsTo
    {
        return $this->belongsTo(EtaApplication::class, 'eta_number', 'eta_number');
    }

    /**
     * Get the visa application if this is a VISA authorization.
     */
    public function visaApplication(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'visa_id', 'reference_number');
    }

    /**
     * Scope to get only valid (non-expired) authorizations.
     */
    public function scopeValid($query)
    {
        return $query->where('expiry_timestamp', '>', now());
    }

    /**
     * Scope to get expired authorizations.
     */
    public function scopeExpired($query)
    {
        return $query->where('expiry_timestamp', '<=', now());
    }
}
