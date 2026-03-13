<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'rule_type',
        'visa_type_id',
        'nationalities',
        'conditions',
        'route_to',
        'mfa_mission_id',
        'priority',
        'sla_hours',
        'requires_interview',
        'requires_security_clearance',
        'is_active',
    ];

    protected $casts = [
        'nationalities' => 'array',
        'conditions' => 'array',
        'requires_interview' => 'boolean',
        'requires_security_clearance' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    public function mfaMission(): BelongsTo
    {
        return $this->belongsTo(MfaMission::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('rule_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    public function matchesApplication(Application $application): bool
    {
        // Check visa type match
        if ($this->visa_type_id && $this->visa_type_id !== $application->visa_type_id) {
            return false;
        }

        // Check nationality match
        if (!empty($this->nationalities)) {
            $nationality = strtoupper($application->nationality ?? '');
            if (!in_array($nationality, $this->nationalities)) {
                return false;
            }
        }

        // Check custom conditions
        if (!empty($this->conditions)) {
            return $this->evaluateConditions($application);
        }

        return true;
    }

    protected function evaluateConditions(Application $application): bool
    {
        $conditions = $this->conditions;

        // Service type condition
        if (isset($conditions['service_type'])) {
            $serviceType = $this->getServiceType($application);
            $requiredTypes = (array) $conditions['service_type'];
            if (!in_array($serviceType, $requiredTypes)) {
                return false;
            }
        }

        // Risk level condition
        if (isset($conditions['risk_level'])) {
            $requiredLevels = (array) $conditions['risk_level'];
            if (!in_array($application->risk_level, $requiredLevels)) {
                return false;
            }
        }

        // Purpose of visit condition
        if (isset($conditions['purpose'])) {
            $purposes = (array) $conditions['purpose'];
            if (!in_array($application->purpose_of_visit, $purposes)) {
                return false;
            }
        }

        // Visa type condition
        if (isset($conditions['visa_type'])) {
            $visaType = $application->visaType?->slug;
            $requiredTypes = (array) $conditions['visa_type'];
            if (!$visaType || !in_array($visaType, $requiredTypes)) {
                return false;
            }
        }

        // Duration condition
        if (isset($conditions['min_duration'])) {
            if (($application->duration_days ?? 0) < $conditions['min_duration']) {
                return false;
            }
        }

        // Diplomatic condition
        if (isset($conditions['is_diplomatic']) && $conditions['is_diplomatic']) {
            $visaType = $application->visaType;
            if (!$visaType || $visaType->slug !== 'diplomatic') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get service type from application (same logic as AdvancedRoutingService).
     */
    protected function getServiceType(Application $application): string
    {
        $serviceTier = $application->serviceTier;
        
        if (!$serviceTier) {
            return 'standard'; // Default
        }
        
        // Map service tier codes to routing service types
        return match (strtolower($serviceTier->code)) {
            'standard' => 'standard',
            'express' => 'express',
            'premium', 'vip' => 'premium',
            'emergency' => 'emergency',
            default => 'standard',
        };
    }
}
