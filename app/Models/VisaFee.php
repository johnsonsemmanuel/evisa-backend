<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'visa_type_id',
        'nationality_category',
        'processing_tier',
        'amount',
        'currency',
        'is_active',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the visa type this fee applies to.
     */
    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    /**
     * Get the user who created this fee.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the amount in currency units (e.g., GHS, USD).
     */
    public function getAmountInCurrencyAttribute(): float
    {
        return $this->amount / 100;
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter active fees.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter fees effective on a given date.
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
     * Scope: Filter by visa type.
     */
    public function scopeForVisaType($query, int $visaTypeId)
    {
        return $query->where('visa_type_id', $visaTypeId);
    }

    /**
     * Scope: Filter by processing tier.
     */
    public function scopeForTier($query, string $tier)
    {
        return $query->where('processing_tier', $tier);
    }

    /**
     * Scope: Filter by nationality category.
     */
    public function scopeForNationality($query, string $category)
    {
        return $query->where(function ($q) use ($category) {
            $q->where('nationality_category', $category)
              ->orWhere('nationality_category', 'all');
        });
    }

    /**
     * Scope: Get currently active and effective fees.
     */
    public function scopeCurrent($query)
    {
        return $query->active()->effectiveOn();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if this fee is currently effective.
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
     * Deactivate this fee (soft deactivation).
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'effective_until' => now()->toDateString(),
        ]);
    }
}
