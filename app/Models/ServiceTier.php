<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'processing_hours',
        'processing_time_display',
        'fee_multiplier',
        'additional_fee',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'processing_hours' => 'integer',
            'fee_multiplier' => 'decimal:2',
            'additional_fee' => 'integer',  // pesewas (BIGINT)
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Calculate the total fee in pesewas for this service tier.
     *
     * @param int $baseFeePesewas Base fee in pesewas (minor units)
     * @return int Total fee in pesewas
     */
    public function calculateFee(int $baseFeePesewas): int
    {
        return (int) round($baseFeePesewas * (float) $this->fee_multiplier) + (int) $this->additional_fee;
    }
}
