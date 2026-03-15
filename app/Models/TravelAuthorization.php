<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelAuthorization extends Model
{
    use HasFactory;

    protected $primaryKey = 'taid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'taid',
        'passport_number',
        'nationality',
        'authorization_type',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'authorization_type' => \App\Enums\AuthorizationType::class,
    ];

    /**
     * Generate unique TAID in format: GH-TA-YYYYMMDD-XXXX
     * 
     * @return string
     */
    public static function generateTaid(): string
    {
        do {
            $date = date('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $taid = "GH-TA-{$date}-{$random}";
            
            // Ensure uniqueness
            $exists = self::where('taid', $taid)->exists();
        } while ($exists);
        
        return $taid;
    }

    /**
     * Create a new TAID record
     * 
     * @param string $passportNumber
     * @param string $nationality
     * @param string $authorizationType 'ETA' or 'VISA'
     * @return self
     */
    public static function createTaid(
        string $passportNumber,
        string $nationality,
        string $authorizationType
    ): self {
        return self::create([
            'taid' => self::generateTaid(),
            'passport_number' => $passportNumber,
            'nationality' => strtoupper($nationality),
            'authorization_type' => strtoupper($authorizationType),
            'status' => 'active',
        ]);
    }

    /**
     * Get all ETA applications linked to this TAID
     */
    public function etaApplications(): HasMany
    {
        return $this->hasMany(EtaApplication::class, 'taid', 'taid');
    }

    /**
     * Get all visa applications linked to this TAID
     */
    public function visaApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'taid', 'taid');
    }

    /**
     * Mark TAID as used (when traveler enters Ghana)
     */
    public function markAsUsed(): void
    {
        $this->update(['status' => 'used']);
    }

    /**
     * Mark TAID as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Check if TAID is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if authorization is for ETA
     */
    public function isEta(): bool
    {
        return $this->authorization_type === \App\Enums\AuthorizationType::ETA;
    }

    /**
     * Check if authorization is for VISA
     */
    public function isVisa(): bool
    {
        return $this->authorization_type === \App\Enums\AuthorizationType::VISA;
    }
}
