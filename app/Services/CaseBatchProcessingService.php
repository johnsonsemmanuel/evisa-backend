<?php

namespace App\Services;

use App\Models\Application;
use App\Services\ApplicationService;
use App\Services\ApplicationRoutingService;
use Illuminate\Support\Collection;

class CaseBatchProcessingService
{
    public function __construct(
        protected ApplicationService $applicationService,
        protected ApplicationRoutingService $routingService,
    ) {}

    /**
     * Batch assign applications to an officer.
     */
    public function batchAssign(array $applicationIds, int $officerId): array
    {
        $updated = Application::whereIn('id', $applicationIds)
            ->where('assigned_agency', 'gis')
            ->whereIn('status', ['submitted', 'under_review'])
            ->update(['assigned_officer_id' => $officerId]);

        return [
            'updated_count' => $updated,
            'errors' => [],
        ];
    }

    /**
     * Batch update application status with error handling.
     */
    public function batchUpdateStatus(array $applicationIds, string $status, ?string $notes = null): array
    {
        $applications = Application::whereIn('id', $applicationIds)
            ->where('assigned_agency', 'gis')
            ->get();

        $updated = 0;
        $errors = [];

        foreach ($applications as $application) {
            try {
                if ($status === 'escalated') {
                    $this->routingService->escalateToMfa($application, $notes ?? 'Batch escalation');
                } else {
                    $this->applicationService->changeStatus(
                        $application,
                        $status,
                        $notes ?? 'Batch status update'
                    );
                }
                $updated++;
            } catch (\Exception $e) {
                $errors[] = [
                    'reference_number' => $application->reference_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'updated_count' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Batch approve applications with error handling.
     */
    public function batchApprove(array $applicationIds, ?string $notes = null): array
    {
        $applications = Application::whereIn('id', $applicationIds)
            ->where('assigned_agency', 'gis')
            ->where('status', 'pending_approval')
            ->get();

        $approved = 0;
        $errors = [];

        foreach ($applications as $application) {
            try {
                $this->applicationService->changeStatus(
                    $application,
                    'approved',
                    $notes ?? 'Batch approval'
                );

                $application->update([
                    'decided_at' => now(),
                    'decision_notes' => $notes ?? null,
                ]);

                $approved++;
            } catch (\Exception $e) {
                $errors[] = [
                    'reference_number' => $application->reference_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'approved_count' => $approved,
            'errors' => $errors,
        ];
    }

    /**
     * Batch request additional information with error handling.
     */
    public function batchRequestInfo(array $applicationIds, string $notes): array
    {
        $applications = Application::whereIn('id', $applicationIds)
            ->where('assigned_agency', 'gis')
            ->whereIn('status', ['submitted', 'under_review'])
            ->get();

        $updated = 0;
        $errors = [];

        foreach ($applications as $application) {
            try {
                $this->applicationService->changeStatus(
                    $application,
                    'additional_info_requested',
                    $notes
                );
                $updated++;
            } catch (\Exception $e) {
                $errors[] = [
                    'reference_number' => $application->reference_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'updated_count' => $updated,
            'errors' => $errors,
        ];
    }
}