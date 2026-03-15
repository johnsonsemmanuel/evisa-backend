<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'parent_id',
        'address',
        'phone',
        'email',
        'city',
        'region',
        'country_code',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Agency::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'agency_id');
    }

    public function missions(): HasMany
    {
        // Check if missions table exists, otherwise use mfa_missions
        $missionClass = class_exists(Mission::class) ? Mission::class : MfaMission::class;
        return $this->hasMany($missionClass, 'agency_id');
    }

    // ── Scopes ────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeHeadquarters($query)
    {
        return $query->whereIn('type', ['gis_hq', 'mfa_hq']);
    }

    public function scopeFieldOffices($query)
    {
        return $query->whereIn('type', ['gis_field', 'mfa_mission']);
    }

    // ── Helpers ───────────────────────────────────────────

    public function isHeadquarters(): bool
    {
        return in_array($this->type, ['gis_hq', 'mfa_hq']);
    }

    public function isFieldOffice(): bool
    {
        return in_array($this->type, ['gis_field', 'mfa_mission']);
    }

    /**
     * Get full hierarchical path (e.g., "MFA HQ > Ghana Embassy London")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }
        
        return implode(' > ', $path);
    }
}
