<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierRule extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'visa_type_id',
        'tier',
        'processing_tier',
        'name',
        'description',
        'conditions',
        'route_to',
        'sla_hours',
        'priority',
        'is_active',
        'price_multiplier',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active'  => 'boolean',
        ];
    }

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }
}
