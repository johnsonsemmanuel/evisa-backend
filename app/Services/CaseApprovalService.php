<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ReasonCode;
use App\Services\ApplicationService;
use App\Services\EVisaPdfService;
use App\Services\RiskScreeningService;

class CaseApprovalService
{
    public function __construct(
        protected ApplicationService $applicationService,
        protected EVisaPdfService $eVisaPdfService,
    ) {}

    public function approveApplication(Application $application, ?string $notes = null): Application
    {
        if ($application->risk_screening_status === 'flagged') {
            throw new \InvalidArgumentException('Cannot approve: application is flagged by risk screening');
        }
        if ($application->watchlist_flagged) {
            throw new \InvalidArgumentException('Cannot approve: applicant is flagged on watchlist');
        }

        $riskScreening = app(RiskScreeningService::class);
        if (!$riskScreening->canApprove($application)) {
            throw new \InvalidArgumentException('Cannot approve: risk screening has not cleared this application');
        }

        $this->applicationService->changeStatus($application, 'approved', $notes ?? 'Approved');

        $this->eVisaPdfService->generate($application);

        return $application->fresh();
    }

    /**
     * Deny an application with multiple reason codes.
     */
    public function denyApplication(Application $application, array $denialReasonCodes, ?string $notes = null): Application
    {
        // Validate all reason codes are of type 'reject'
        $reasonCodes = ReasonCode::whereIn('code', $denialReasonCodes)
            ->where('action_type', 'reject')
            ->where('is_active', true)
            ->get();

        if ($reasonCodes->count() !== count($denialReasonCodes)) {
            throw new \InvalidArgumentException('All denial reason codes must be valid and of type reject');
        }

        // Build denial notes with all reasons
        $denialNotes = "Application denied for the following reasons:\n\n";
        foreach ($reasonCodes as $reasonCode) {
            $denialNotes .= "• [{$reasonCode->code}] {$reasonCode->reason}\n";
        }

        if (!empty($notes)) {
            $denialNotes .= "\nAdditional Notes:\n{$notes}";
        }

        // Store denial reason codes in the application
        $application->update([
            'denial_reason_codes' => $denialReasonCodes,
        ]);

        $this->applicationService->changeStatus($application, 'denied', $denialNotes);

        return $application->fresh();
    }

    public function issueVisa(Application $application): Application
    {
        if ($application->watchlist_flagged) {
            throw new \InvalidArgumentException('Cannot issue visa: applicant flagged on Interpol watchlist');
        }

        $payment = $application->payments()->where('status', 'paid')->first();
        if (!$payment) {
            throw new \InvalidArgumentException('Cannot issue visa: no verified payment found');
        }

        if (!$application->evisa_file_path) {
            $this->eVisaPdfService->generate($application);
        }

        $this->applicationService->changeStatus($application, 'issued', 'Visa issued');

        return $application->fresh();
    }
}