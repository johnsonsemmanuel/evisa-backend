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
            'additional_fee' => 'decimal:2',
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
     * Calculate the total fee for this service tier
     */
    public function calculateFee(float $baseFee): float
    {
        return ($baseFee * $this->fee_multiplier) + $this->additional_fee;
    }
}
