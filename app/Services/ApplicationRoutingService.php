<?php

namespace App\Services;

use App\Models\Application;
use App\Models\MfaMission;
use App\Models\MissionCountryMapping;
use App\Models\TierRule;

class ApplicationRoutingService
{
    public function __construct(
        protected TierClassificationService $tierService,
        protected SlaService $slaService,
        protected RiskScreeningService $riskScreeningService,
    ) {}

    /**
     * Route a submitted application through the CPH pipeline:
     * 1. Classify processing tier
     * 2. Determine agency routing based on visa_channel + processing_tier:
     *    - visa_channel = regular       → MFA (always)
     *    - visa_channel = e-visa AND processing_tier = standard → MFA
     *    - visa_channel = e-visa AND processing_tier ≠ standard → GIS
     * 3. Calculate SLA deadline
     * 4. Set current_queue = review_queue
     * 5. Set status = under_review
     */
    public function route(Application $application): Application
    {
        $matchedRule = $this->tierService->classify($application);

        if ($matchedRule) {
            $application->tier = $matchedRule->tier;
            $application->processing_tier = $matchedRule->processing_tier ?? $this->mapTierToProcessingTier($matchedRule->tier);
            $application->sla_deadline = $this->slaService->calculateDeadline($matchedRule->sla_hours);
        } else {
            // Default: Standard tier, 120-hour SLA (per spec section 5.1)
            $application->tier = 'tier_1';
            $application->processing_tier = 'standard';
            $application->sla_deadline = $this->slaService->calculateDeadline(120);
        }

        // Determine agency based on visa_channel + processing_tier (spec rules)
        $application->assigned_agency = $this->determineAgency($application);

        // If MFA, assign to appropriate mission based on applicant's country
        if ($application->assigned_agency === 'mfa') {
            $application->owner_mission_id = $this->determineMission($application);
        } else {
            $application->owner_mission_id = null;
        }

        // Initialize risk screening status and queue
        $application->risk_screening_status = 'pending';
        $application->current_queue = 'review';
        $application->status = 'under_review';
        $application->save();

        // Perform risk assessment immediately
        try {
            $this->riskScreeningService->performRiskAssessment($application);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to perform initial risk assessment for {$application->reference_number}: " . $e->getMessage());
        }

        return $application;
    }

    /**
     * Determine which MFA mission should handle this application.
     * Based on applicant's nationality/country of residence.
     */
    protected function determineMission(Application $application): ?int
    {
        // Get applicant's nationality
        $nationality = $application->nationality_encrypted ?? $application->nationality;
        
        if (!$nationality) {
            return null;
        }

        // Look up mission mapping for this country
        $mapping = MissionCountryMapping::where('country_code', strtoupper($nationality))
            ->whereHas('mission', function ($query) {
                $query->where('is_active', true);
            })
            ->first();

        if ($mapping) {
            return $mapping->mfa_mission_id;
        }

        // Fallback: try to find mission by country_code in mfa_missions table
        $mission = MfaMission::where('country_code', strtoupper($nationality))
            ->where('is_active', true)
            ->first();

        return $mission?->id;
    }

    /**
     * Determine agency based on visa_channel and processing_tier.
     *
     * Spec rules (Section 6.1):
     *   IF visa_channel = regular           → MFA
     *   ELSE IF visa_channel = e-visa AND processing_tier = standard → MFA
     *   ELSE                                → GIS (priority, express)
     */
    protected function determineAgency(Application $application): string
    {
        $channel = $application->visa_channel ?? 'e-visa';
        $tier = $application->processing_tier ?? 'standard';

        // Also resolve tier from service_tier relation if processing_tier not set
        if (!$application->processing_tier && $application->serviceTier) {
            $tier = $application->serviceTier->code ?? 'standard';
        }

        // Rule 1: Regular visa → always MFA
        if ($channel === 'regular') {
            return 'mfa';
        }

        // Rule 2: E-Visa + Standard tier → MFA
        if ($channel === 'e-visa' && $tier === 'standard') {
            return 'mfa';
        }

        // Rule 3: E-Visa + Priority or Express → GIS
        return 'gis';
    }

    /**
     * Map legacy tier to processing tier.
     * Provides backward compatibility for old tier names.
     */
    protected function mapTierToProcessingTier(string $tier): string
    {
        return match ($tier) {
            'tier_1' => 'priority',
            'tier_2' => 'standard',
            default => 'standard',
        };
    }

    /**
     * Escalate an application from GIS to MFA.
     */
    public function escalateToMfa(Application $application, string $reason = null): Application
    {
        $oldAgency = $application->assigned_agency;

        $application->assigned_agency = 'mfa';
        $application->status = 'escalated';
        $application->assigned_officer_id = null;
        $application->reviewing_officer_id = null;
        $application->approval_officer_id = null;
        $application->current_queue = 'review';

        // Assign to appropriate mission
        $application->owner_mission_id = $this->determineMission($application);

        // Extend SLA by 48 hours on escalation
        $application->sla_deadline = $this->slaService->extendDeadline($application->sla_deadline, 48);
        $application->save();

        return $application;
    }

    /**
     * Return an escalated application back to GIS from MFA.
     */
    public function returnToGis(Application $application): Application
    {
        $application->assigned_agency = 'gis';
        $application->status = 'under_review';
        $application->assigned_officer_id = null;
        $application->reviewing_officer_id = null;
        $application->approval_officer_id = null;
        $application->owner_mission_id = null;
        $application->current_queue = 'review';
        $application->save();

        return $application;
    }

    /**
     * Assign a reviewing officer to an application.
     */
    public function assignReviewer(Application $application, int $officerId): Application
    {
        $application->reviewing_officer_id = $officerId;
        $application->review_started_at = now();
        $application->save();

        return $application;
    }

    /**
     * Forward application from review queue to approval queue.
     */
    public function forwardToApproval(Application $application): Application
    {
        $application->current_queue = 'approval';
        $application->status = 'pending_approval';
        $application->review_completed_at = now();
        $application->save();

        return $application;
    }

    /**
     * Assign an approval officer to an application.
     */
    public function assignApprover(Application $application, int $officerId): Application
    {
        $application->approval_officer_id = $officerId;
        $application->approval_started_at = now();
        $application->save();

        return $application;
    }

    /**
     * Mark application as approved.
     */
    public function approve(Application $application): Application
    {
        $application->status = 'approved';
        $application->current_queue = 'completed';
        $application->approval_completed_at = now();
        $application->decided_at = now();
        $application->save();

        return $application;
    }

    /**
     * Mark application as denied.
     */
    public function deny(Application $application): Application
    {
        $application->status = 'denied';
        $application->current_queue = 'completed';
        $application->approval_completed_at = now();
        $application->decided_at = now();
        $application->save();

        return $application;
    }

    /**
     * Return application to review queue (from approval).
     */
    public function returnToReview(Application $application): Application
    {
        $application->current_queue = 'review';
        $application->status = 'under_review';
        $application->approval_officer_id = null;
        $application->approval_started_at = null;
        $application->save();

        return $application;
    }
}
