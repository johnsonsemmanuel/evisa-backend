<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InternalNote;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TwoStageWorkflowService
{
    /**
     * Move application to review queue after payment.
     */
    public function moveToReviewQueue(Application $application): void
    {
        $application->forceFill([
            'current_queue' => 'REVIEW_QUEUE',
            'status' => 'IN_REVIEW',
        ])->save();

        Log::info('Application moved to review queue', [
            'application_id' => $application->id,
            'reference_number' => $application->reference_number,
        ]);
    }

    /**
     * Assign application to reviewing officer.
     */
    public function assignToReviewingOfficer(Application $application, User $officer): void
    {
        if (!$this->canUserReviewApplication($officer, $application)) {
            throw new \Exception('Officer cannot review this application');
        }

        $application->forceFill([
            'reviewing_officer_id' => $officer->id,
            'review_started_at' => now(),
        ])->save();

        $this->addInternalNote($application, $officer, 'Application assigned for review');

        Log::info('Application assigned to reviewing officer', [
            'application_id' => $application->id,
            'officer_id' => $officer->id,
            'officer_name' => $officer->full_name,
        ]);
    }

    /**
     * Complete review and move to approval queue.
     */
    public function completeReview(Application $application, User $reviewingOfficer, string $notes = null): void
    {
        if ($application->reviewing_officer_id !== $reviewingOfficer->id) {
            throw new \Exception('Only the assigned reviewing officer can complete this review');
        }

        $application->forceFill([
            'current_queue' => 'APPROVAL_QUEUE',
            'status' => 'PENDING_APPROVAL',
            'review_completed_at' => now(),
        ])->save();

        if ($notes) {
            $this->addInternalNote($application, $reviewingOfficer, $notes, 'review_completion');
        }

        Log::info('Review completed, moved to approval queue', [
            'application_id' => $application->id,
            'reviewing_officer_id' => $reviewingOfficer->id,
        ]);
    }

    /**
     * Assign application to approval officer.
     */
    public function assignToApprovalOfficer(Application $application, User $officer): void
    {
        if (!$this->canUserApproveApplication($officer, $application)) {
            throw new \Exception('Officer cannot approve this application');
        }

        $application->forceFill([
            'approval_officer_id' => $officer->id,
            'approval_started_at' => now(),
        ])->save();

        $this->addInternalNote($application, $officer, 'Application assigned for approval');

        Log::info('Application assigned to approval officer', [
            'application_id' => $application->id,
            'officer_id' => $officer->id,
            'officer_name' => $officer->full_name,
        ]);
    }

    /**
     * Make final approval decision.
     */
    public function makeApprovalDecision(
        Application $application, 
        User $approvalOfficer, 
        string $decision, 
        ?array $denialReasonCodes = null,
        string $notes = null
    ): void {
        if ($application->approval_officer_id !== $approvalOfficer->id) {
            throw new \Exception('Only the assigned approval officer can make this decision');
        }

        if (!in_array($decision, ['APPROVED', 'DENIED'])) {
            throw new \Exception('Invalid decision. Must be APPROVED or DENIED');
        }

        // If denying, validate that at least one reason code is provided
        if ($decision === 'DENIED') {
            if (empty($denialReasonCodes)) {
                throw new \Exception('At least one denial reason must be provided when denying an application');
            }

            // Validate that all reason codes exist and are of type 'reject'
            $validReasonCodes = \App\Models\ReasonCode::whereIn('id', $denialReasonCodes)
                ->where('action_type', 'reject')
                ->pluck('id')
                ->toArray();

            if (count($validReasonCodes) !== count($denialReasonCodes)) {
                throw new \Exception('Invalid denial reason codes provided');
            }
        }

        $statusMap = ['APPROVED' => 'approved', 'DENIED' => 'denied'];
        $newStatus = $statusMap[$decision];

        $application->forceFill([
            'approval_completed_at' => now(),
            'decided_at' => now(),
            'decision_notes' => $notes,
        ])->save();

        if ($decision === 'DENIED' && !empty($denialReasonCodes)) {
            $application->forceFill([
                'denial_reason_codes' => $denialReasonCodes,
            ])->save();
        }

        $appService = app(\App\Services\ApplicationService::class);
        $appService->changeStatus($application, $newStatus, $notes ?? "Application {$decision}");

        $noteContent = "Application {$decision}";
        if ($decision === 'DENIED' && !empty($denialReasonCodes)) {
            $reasons = \App\Models\ReasonCode::whereIn('id', $denialReasonCodes)
                ->pluck('reason')
                ->toArray();
            $noteContent .= "\nDenial Reasons: " . implode(', ', $reasons);
        }
        if ($notes) {
            $noteContent .= "\nAdditional Notes: {$notes}";
        }

        $this->addInternalNote($application, $approvalOfficer, $noteContent, 'final_decision');

        Log::info('Final approval decision made', [
            'application_id' => $application->id,
            'decision' => $decision,
            'denial_reason_codes' => $denialReasonCodes ?? [],
            'approval_officer_id' => $approvalOfficer->id,
        ]);
    }

    /**
     * Return application to review queue for additional information.
     */
    public function requestAdditionalInfo(
        Application $application, 
        User $officer, 
        string $reason
    ): void {
        $application->forceFill([
            'current_queue' => 'REVIEW_QUEUE',
            'status' => 'REQUEST_INFO',
        ])->save();

        $this->addInternalNote($application, $officer, $reason, 'additional_info_request');

        Log::info('Application returned for additional information', [
            'application_id' => $application->id,
            'officer_id' => $officer->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Get applications in review queue for officer.
     */
    public function getReviewQueueForOfficer(User $officer): \Illuminate\Database\Eloquent\Collection
    {
        $query = Application::where('current_queue', 'REVIEW_QUEUE')
            ->where('status', 'IN_REVIEW');

        // Apply access control based on officer role and agency
        if ($officer->agency === 'GIS') {
            $query->where('owner_agency', 'GIS');
        } elseif ($officer->agency === 'MFA') {
            $query->where('owner_agency', 'MFA')
                  ->where('owner_mission_id', $officer->mission_id);
        }

        return $query->with(['visaType', 'reviewingOfficer', 'approvalOfficer'])
                    ->orderBy('risk_score', 'desc') // High risk first
                    ->orderBy('sla_deadline', 'asc') // SLA deadline priority
                    ->get();
    }

    /**
     * Get applications in approval queue for officer.
     */
    public function getApprovalQueueForOfficer(User $officer): \Illuminate\Database\Eloquent\Collection
    {
        $query = Application::where('current_queue', 'APPROVAL_QUEUE')
            ->where('status', 'PENDING_APPROVAL');

        // Apply access control based on officer role and agency
        if ($officer->agency === 'GIS') {
            $query->where('owner_agency', 'GIS');
        } elseif ($officer->agency === 'MFA') {
            $query->where('owner_agency', 'MFA')
                  ->where('owner_mission_id', $officer->mission_id);
        }

        return $query->with(['visaType', 'reviewingOfficer', 'approvalOfficer'])
                    ->orderBy('risk_score', 'desc') // High risk first
                    ->orderBy('sla_deadline', 'asc') // SLA deadline priority
                    ->get();
    }

    /**
     * Check if user can review application.
     */
    public function canUserReviewApplication(User $user, Application $application): bool
    {
        // Must be a reviewing officer
        if (!in_array($user->role, ['gis_reviewer', 'gis_officer', 'mfa_reviewer', 'mfa_officer'])) {
            return false;
        }

        // Agency must match
        if ($user->agency !== $application->owner_agency) {
            return false;
        }

        // For MFA officers, mission must match
        if ($user->agency === 'MFA' && $user->mission_id !== $application->owner_mission_id) {
            return false;
        }

        return true;
    }

    /**
     * Check if user can approve application.
     */
    public function canUserApproveApplication(User $user, Application $application): bool
    {
        // Must be an approval officer
        if (!in_array($user->role, ['gis_approver', 'gis_officer', 'mfa_approver', 'mfa_officer'])) {
            return false;
        }

        // Agency must match
        if ($user->agency !== $application->owner_agency) {
            return false;
        }

        // For MFA officers, mission must match
        if ($user->agency === 'MFA' && $user->mission_id !== $application->owner_mission_id) {
            return false;
        }

        // Cannot approve own review
        if ($application->reviewing_officer_id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Add internal note to application.
     */
    protected function addInternalNote(
        Application $application, 
        User $user, 
        string $content, 
        string $type = 'general'
    ): InternalNote {
        return InternalNote::create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'content' => $content,
            'note_type' => $type,
        ]);
    }

    /**
     * Trigger visa issuance process.
     */
    protected function triggerVisaIssuance(Application $application): void
    {
        // This would trigger the visa PDF generation and email sending
        // For now, just update status
        $application->forceFill([
            'status' => 'ISSUED',
        ])->save();

        Log::info('Visa issuance triggered', [
            'application_id' => $application->id,
        ]);
    }

    /**
     * Get workflow statistics for dashboard.
     */
    public function getWorkflowStatistics(User $user): array
    {
        $baseQuery = Application::query();

        // Apply access control
        if ($user->agency === 'GIS') {
            $baseQuery->where('owner_agency', 'GIS');
        } elseif ($user->agency === 'MFA') {
            $baseQuery->where('owner_agency', 'MFA')
                     ->where('owner_mission_id', $user->mission_id);
        }

        return [
            'review_queue_count' => (clone $baseQuery)->where('current_queue', 'REVIEW_QUEUE')->count(),
            'approval_queue_count' => (clone $baseQuery)->where('current_queue', 'APPROVAL_QUEUE')->count(),
            'high_risk_count' => (clone $baseQuery)->whereIn('risk_level', ['High', 'Critical'])->count(),
            'sla_breaches' => (clone $baseQuery)->where('sla_deadline', '<', now())->count(),
            'my_reviews' => (clone $baseQuery)->where('reviewing_officer_id', $user->id)->count(),
            'my_approvals' => (clone $baseQuery)->where('approval_officer_id', $user->id)->count(),
        ];
    }
}