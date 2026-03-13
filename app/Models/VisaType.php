<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisaType extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'base_fee',
        'multiple_entry_fee',
        'government_fee',
        'platform_fee',
        'entry_type',
        'validity_period',
        'category',
        'max_duration_days',
        'is_active',
        'sort_order',
        'required_documents',
        'required_fields',
        'optional_fields',
        'default_processing_days',
        'default_route_to',
        'eligible_nationalities',
        'blacklisted_nationalities',
    ];

    protected function casts(): array
    {
        return [
            'base_fee'                  => 'decimal:2',
            'multiple_entry_fee'        => 'decimal:2',
            'government_fee'            => 'decimal:2',
            'platform_fee'              => 'decimal:2',
            'is_active'                 => 'boolean',
            'sort_order'                => 'integer',
            'default_processing_days'   => 'integer',
            'required_documents'        => 'array',
            'required_fields'           => 'array',
            'optional_fields'           => 'array',
            'eligible_nationalities'    => 'array',
            'blacklisted_nationalities' => 'array',
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

    public function scopeVisas($query)
    {
        return $query->where('category', 'visa');
    }

    public function scopeEta($query)
    {
        return $query->where('category', 'eta');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function tierRules(): HasMany
    {
        return $this->hasMany(TierRule::class);
    }
}
