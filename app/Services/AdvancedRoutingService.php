<?php

namespace App\Services;

use App\Models\Application;
use App\Models\MfaMission;
use App\Models\RoutingRule;
use Illuminate\Support\Facades\Log;

class AdvancedRoutingService
{
    protected array $defaultRouting = [
        'gis_hq' => ['sla_hours' => 72, 'agency' => 'gis'],
        'mfa_hq' => ['sla_hours' => 120, 'agency' => 'mfa'],
        'mfa_mission' => ['sla_hours' => 168, 'agency' => 'mfa'],
    ];

    /**
     * Determine the optimal routing for an application.
     *
     * Spec rules (checked first):
     *   IF visa_channel = regular           → MFA
     *   ELSE IF visa_channel = e-visa AND processing_tier = standard → MFA
     *   ELSE (e-visa + non-standard)        → GIS
     *
     * Within the GIS/MFA decision, sub-routing refines the destination.
     */
    public function determineRouting(Application $application): array
    {
        $channel = $application->visa_channel ?? 'e-visa';
        $serviceType = $this->getServiceType($application);
        $visaType = $application->visaType?->slug;

        // ── Spec Rule 1: Regular visa → always MFA ──
        if ($channel === 'regular') {
            return $this->routeToMfaMission($application);
        }

        // ── Spec Rule 2: E-Visa + Standard → MFA ──
        if ($channel === 'e-visa' && $serviceType === 'standard') {
            return $this->routeToMfaMission($application);
        }

        // ── Spec Rule 3: E-Visa + non-standard → GIS (with sub-routing) ──

        // Express / Premium → GIS HQ Processing Unit
        if (in_array($serviceType, ['express', 'premium'])) {
            return $this->routeToGisHq($application, $serviceType);
        }

        // Emergency → GIS HQ + Notify MFA Mission
        if ($serviceType === 'emergency') {
            return $this->routeEmergency($application);
        }

        // Special visa-type overrides
        if ($visaType === 'diplomatic') {
            return $this->routeToMfaDiplomatic($application);
        }

        if ($visaType === 'transit') {
            return $this->routeToGisBorderControl($application);
        }

        // Check custom routing rules
        $rules = RoutingRule::active()->ordered()->get();
        foreach ($rules as $rule) {
            if ($rule->matchesApplication($application)) {
                return $this->buildRoutingResult($rule, $application);
            }
        }

        // Default routing
        return $this->getDefaultRouting($application);
    }

    /**
     * Build routing result from matched rule.
     */
    protected function buildRoutingResult(RoutingRule $rule, Application $application): array
    {
        $result = [
            'route_to' => $rule->route_to,
            'agency' => $rule->route_to === 'gis_hq' ? 'gis' : 'mfa',
            'sla_hours' => $rule->sla_hours,
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'requires_interview' => $rule->requires_interview,
            'requires_security_clearance' => $rule->requires_security_clearance,
        ];

        // If routing to MFA mission, include mission details
        if ($rule->route_to === 'mfa_mission' && $rule->mfa_mission_id) {
            $mission = $rule->mfaMission;
            if ($mission) {
                $result['mission'] = [
                    'id' => $mission->id,
                    'code' => $mission->code,
                    'name' => $mission->name,
                    'city' => $mission->city,
                    'country' => $mission->country_name,
                ];
            }
        }

        Log::info("Application {$application->reference_number} routed by rule: {$rule->name}", $result);

        return $result;
    }

    /**
     * Get default routing based on visa type.
     */
    protected function getDefaultRouting(Application $application): array
    {
        $visaType = $application->visaType;
        $defaultRoute = $visaType?->default_route_to ?? 'gis';

        if ($defaultRoute === 'mfa') {
            return [
                'route_to' => 'mfa_hq',
                'agency' => 'mfa',
                'sla_hours' => 120,
                'rule_id' => null,
                'rule_name' => 'Default MFA routing',
                'requires_interview' => false,
                'requires_security_clearance' => false,
            ];
        }

        return [
            'route_to' => 'gis_hq',
            'agency' => 'gis',
            'sla_hours' => 72,
            'rule_id' => null,
            'rule_name' => 'Default GIS routing',
            'requires_interview' => false,
            'requires_security_clearance' => false,
        ];
    }

    /**
     * Get service type from application.
     */
    protected function getServiceType(Application $application): string
    {
        $serviceTier = $application->serviceTier;
        
        if (!$serviceTier) {
            return 'standard'; // Default
        }
        
        // Map service tier codes to routing service types
        // Tier codes: standard, fast_track, express, ultra_express
        return match (strtolower($serviceTier->code)) {
            'standard' => 'standard',
            'fast_track' => 'express',
            'express' => 'premium',
            'ultra_express' => 'emergency',
            default => 'standard',
        };
    }

    /**
     * Route to MFA Mission based on origin country.
     */
    protected function routeToMfaMission(Application $application): array
    {
        $mission = $this->findMfaMission($application);
        
        if ($mission) {
            return [
                'route_to' => 'mfa_mission',
                'agency' => 'mfa',
                'sla_hours' => $this->getStandardProcessingSla($application),
                'rule_id' => null,
                'rule_name' => 'Standard processing - MFA Mission',
                'requires_interview' => $mission->requires_interview,
                'requires_security_clearance' => false,
                'mission' => [
                    'id' => $mission->id,
                    'code' => $mission->code,
                    'name' => $mission->name,
                    'city' => $mission->city,
                    'country' => $mission->country_name,
                ],
                'processing_location' => "Ghana {$mission->mission_type} - {$mission->city}",
            ];
        }
        
        // Fallback to MFA HQ if no mission found
        return [
            'route_to' => 'mfa_hq',
            'agency' => 'mfa',
            'sla_hours' => 120,
            'rule_id' => null,
            'rule_name' => 'Standard processing - MFA HQ (no mission)',
            'requires_interview' => false,
            'requires_security_clearance' => false,
            'processing_location' => 'MFA HQ - Accra',
        ];
    }

    /**
     * Route to GIS HQ for express/premium processing.
     */
    protected function routeToGisHq(Application $application, string $serviceType): array
    {
        $slaHours = match ($serviceType) {
            'express' => 48, // 24-48 hours
            'premium' => 24, // Same day
            default => 72,
        };
        
        $processingUnit = match ($serviceType) {
            'express' => 'GIS Central Processing Unit',
            'premium' => 'GIS Fast Track Unit',
            default => 'GIS Central Processing Unit',
        };
        
        return [
            'route_to' => 'gis_hq',
            'agency' => 'gis',
            'sla_hours' => $slaHours,
            'rule_id' => null,
            'rule_name' => ucfirst($serviceType) . ' processing - GIS HQ',
            'requires_interview' => false,
            'requires_security_clearance' => false,
            'processing_location' => $processingUnit,
        ];
    }

    /**
     * Route emergency visa processing.
     */
    protected function routeEmergency(Application $application): array
    {
        return [
            'route_to' => 'gis_hq',
            'agency' => 'gis',
            'sla_hours' => 12, // Rapid override
            'rule_id' => null,
            'rule_name' => 'Emergency processing - GIS HQ + MFA Alert',
            'requires_interview' => false,
            'requires_security_clearance' => false,
            'processing_location' => 'GIS Emergency Processing Unit',
            'notify_mfa_mission' => true,
        ];
    }

    /**
     * Route to MFA Diplomatic Desk.
     */
    protected function routeToMfaDiplomatic(Application $application): array
    {
        return [
            'route_to' => 'mfa_hq',
            'agency' => 'mfa',
            'sla_hours' => 72,
            'rule_id' => null,
            'rule_name' => 'Diplomatic processing - MFA Diplomatic Desk',
            'requires_interview' => false,
            'requires_security_clearance' => true,
            'processing_location' => 'MFA Diplomatic Desk - Accra',
            'government_to_government' => true,
        ];
    }

    /**
     * Route to GIS Border Control for transit visas.
     */
    protected function routeToGisBorderControl(Application $application): array
    {
        return [
            'route_to' => 'gis_hq',
            'agency' => 'gis',
            'sla_hours' => 24,
            'rule_id' => null,
            'rule_name' => 'Transit processing - GIS Border Control Unit',
            'requires_interview' => false,
            'requires_security_clearance' => false,
            'processing_location' => 'GIS Border Control Unit',
        ];
    }

    /**
     * Get SLA for standard processing based on visa type and country.
     */
    protected function getStandardProcessingSla(Application $application): int
    {
        $visaType = $application->visaType?->slug;
        $nationality = strtoupper($application->nationality ?? '');
        
        // SLA matrix for standard processing
        $slaMatrix = [
            'tourist' => [
                'US', 'DE', 'GB', 'FR' => 72, // 3-5 days
                'default' => 120,
            ],
            'business' => [
                'GB' => 72, // 3-5 days
                'default' => 120,
            ],
            'student' => [
                'IN' => 168, // 5-7 days
                'default' => 120,
            ],
            'work' => [
                'NG' => 168, // 5-7 days
                'default' => 120,
            ],
            'medical' => 24, // 24 hours
            'conference' => 24, // Same day (but handled by premium tier)
        ];
        
        if (isset($slaMatrix[$visaType])) {
            $countrySlas = $slaMatrix[$visaType];
            return $countrySlas[$nationality] ?? $countrySlas['default'] ?? 120;
        }
        
        return 120; // Default 5 days
    }

    /**
     * Find appropriate MFA mission for an application.
     */
    public function findMfaMission(Application $application): ?MfaMission
    {
        $nationality = strtoupper($application->nationality ?? '');

        // First try to find mission in applicant's country
        $mission = MfaMission::active()
            ->where('country_code', $nationality)
            ->canIssueVisa()
            ->first();

        if ($mission && $mission->handlesVisaType($application->visa_type_id)) {
            return $mission;
        }

        // Find any mission that handles this nationality
        $missions = MfaMission::active()->canIssueVisa()->get();
        
        foreach ($missions as $m) {
            if ($m->handlesNationality($nationality) && $m->handlesVisaType($application->visa_type_id)) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Route application and update its status.
     */
    public function routeApplication(Application $application): Application
    {
        $routing = $this->determineRouting($application);

        $updateData = [
            'assigned_agency' => $routing['agency'],
            'current_queue' => 'review_queue',
            'sla_deadline' => now()->addHours($routing['sla_hours']),
        ];

        // Persist mission assignment if routing to MFA mission
        if (isset($routing['mission']['id'])) {
            $updateData['mfa_mission_id'] = $routing['mission']['id'];
        }

        $application->update($updateData);

        // Create routing log
        $application->statusHistory()->create([
            'to_status' => $application->status,
            'notes' => "Routed to {$routing['route_to']}: {$routing['rule_name']}",
            'changed_by_id' => null,
        ]);

        return $application;
    }

    /**
     * Re-route application based on new information.
     */
    public function reRouteApplication(Application $application, string $reason): Application
    {
        $previousAgency = $application->assigned_agency;
        $routing = $this->determineRouting($application);

        if ($routing['agency'] !== $previousAgency) {
            $application->forceFill([
                'assigned_agency' => $routing['agency'],
                'assigned_officer_id' => null,
                'sla_deadline' => now()->addHours($routing['sla_hours']),
            ])->save();

            $application->statusHistory()->create([
                'to_status' => $application->status,
                'notes' => "Re-routed from {$previousAgency} to {$routing['agency']}: {$reason}",
                'changed_by_id' => auth()->id(),
            ]);

            Log::info("Application {$application->reference_number} re-routed from {$previousAgency} to {$routing['agency']}");
        }

        return $application;
    }

    /**
     * Get routing statistics.
     */
    public function getRoutingStatistics(): array
    {
        $activeApps = \App\Models\Application::whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft']);

        return [
            'gis_hq' => (clone $activeApps)->where('assigned_agency', 'gis')->count(),
            'mfa_hq' => (clone $activeApps)->where('assigned_agency', 'mfa')->count(),
            'by_rule' => RoutingRule::active()
                ->withCount(['applications' => function ($q) {
                    $q->whereNotIn('status', ['approved', 'denied', 'cancelled']);
                }])
                ->get()
                ->map(fn($r) => ['rule' => $r->name, 'count' => $r->applications_count ?? 0]),
        ];
    }

    /**
     * Suggest routing for an application (preview without applying).
     */
    public function suggestRouting(Application $application): array
    {
        $routing = $this->determineRouting($application);
        
        // Add recommendations
        $routing['recommendations'] = [];

        if ($application->risk_level === 'high' || $application->risk_level === 'critical') {
            $routing['recommendations'][] = 'High risk - consider MFA escalation';
        }

        if ($application->visaType?->slug === 'diplomatic') {
            $routing['recommendations'][] = 'Diplomatic visa - MFA HQ recommended';
        }

        $mission = $this->findMfaMission($application);
        if ($mission) {
            $routing['recommendations'][] = "MFA Mission available: {$mission->name} ({$mission->city})";
        }

        return $routing;
    }
}
