<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MfaMission extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city',
        'country_code',
        'country_name',
        'region',
        'mission_type',
        'address',
        'phone',
        'email',
        'timezone',
        'covered_nationalities',
        'visa_types_handled',
        'can_issue_visa',
        'requires_interview',
        'default_sla_hours',
        'is_active',
    ];

    protected $casts = [
        'covered_nationalities' => 'array',
        'visa_types_handled' => 'array',
        'can_issue_visa' => 'boolean',
        'requires_interview' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'mfa_mission_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'mfa_mission_id');
    }

    public function countryMappings(): HasMany
    {
        return $this->hasMany(MissionCountryMapping::class, 'mfa_mission_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    public function scopeCanIssueVisa($query)
    {
        return $query->where('can_issue_visa', true);
    }

    public function handlesNationality(string $nationality): bool
    {
        if (empty($this->covered_nationalities)) {
            return true; // Handles all if not specified
        }
        return in_array(strtoupper($nationality), $this->covered_nationalities);
    }

    public function handlesVisaType(int $visaTypeId): bool
    {
        if (empty($this->visa_types_handled)) {
            return true; // Handles all if not specified
        }
        return in_array($visaTypeId, $this->visa_types_handled);
    }
}
