<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeWaiver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'nationality_codes',
        'visa_type_id',
        'waiver_type',
        'waiver_value',
        'is_active',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'nationality_codes' => 'array',
            'waiver_value' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the visa type this waiver applies to (null = all types).
     */
    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    /**
     * Get the user who created this waiver.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter active waivers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter waivers effective on a given date.
     */
    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ?? now();
        
        return $query->where('effective_from', '<=', $date)
                     ->where(function ($q) use ($date) {
                         $q->whereNull('effective_until')
                           ->orWhere('effective_until', '>=', $date);
                     });
    }

    /**
     * Scope: Filter by nationality code.
     */
    public function scopeForNationality($query, string $nationalityCode)
    {
        return $query->whereJsonContains('nationality_codes', $nationalityCode);
    }

    /**
     * Scope: Filter by visa type (including null = all types).
     */
    public function scopeForVisaType($query, ?int $visaTypeId)
    {
        return $query->where(function ($q) use ($visaTypeId) {
            $q->whereNull('visa_type_id')
              ->orWhere('visa_type_id', $visaTypeId);
        });
    }

    /**
     * Scope: Get currently active and effective waivers.
     */
    public function scopeCurrent($query)
    {
        return $query->active()->effectiveOn();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if this waiver applies to a given nationality.
     */
    public function appliesTo(string $nationalityCode): bool
    {
        return in_array(strtoupper($nationalityCode), array_map('strtoupper', $this->nationality_codes));
    }

    /**
     * Calculate waiver amount for a given fee.
     * 
     * @param int $originalAmount Amount in pesewas
     * @return int Waiver amount in pesewas
     */
    public function calculateWaiverAmount(int $originalAmount): int
    {
        return match ($this->waiver_type) {
            'full' => $originalAmount, // 100% waiver
            'percentage' => (int) round(($originalAmount * $this->waiver_value) / 10000),
            'fixed_reduction' => min($this->waiver_value, $originalAmount), // Can't reduce below 0
            default => 0,
        };
    }

    /**
     * Calculate final amount after waiver.
     * 
     * @param int $originalAmount Amount in pesewas
     * @return int Final amount in pesewas
     */
    public function applyWaiver(int $originalAmount): int
    {
        $waiverAmount = $this->calculateWaiverAmount($originalAmount);
        return max(0, $originalAmount - $waiverAmount);
    }

    /**
     * Check if this waiver is currently effective.
     */
    public function isEffective(?string $date = null): bool
    {
        $date = $date ?? now()->toDateString();
        
        if (!$this->is_active) {
            return false;
        }

        if ($this->effective_from > $date) {
            return false;
        }

        if ($this->effective_until && $this->effective_until < $date) {
            return false;
        }

        return true;
    }

    /**
     * Deactivate this waiver.
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'effective_until' => now()->toDateString(),
        ]);
    }
}
