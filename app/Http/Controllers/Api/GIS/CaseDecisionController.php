<?php

namespace App\Http\Controllers\Api\GIS;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\ApplicationService;
use App\Services\CaseApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseDecisionController extends Controller
{
    public function __construct(
        protected ApplicationService $applicationService,
        protected CaseApprovalService $approvalService,
    ) {}

    /**
     * Submit application for approval (two-step process).
     * Reviewer submits, then Approver approves.
     * Requires: applications.review permission
     */
    public function submitForApproval(Request $request, Application $application): JsonResponse
    {
        if (!$request->user()->canReviewApplications()) {
            return response()->json(['message' => 'You do not have permission to submit for approval'], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        if (!in_array($application->status, ['submitted', 'under_review', 'additional_info_requested'])) {
            return response()->json(['message' => __('case.invalid_status_for_approval')], 422);
        }

        $application->forceFill([
            'reviewed_by_id' => $request->user()->id,
            'reviewed_at' => now(),
            'current_queue' => 'approval_queue',
        ])->save();

        $this->applicationService->changeStatus(
            $application, 
            'pending_approval', 
            $validated['notes'] ?? 'Submitted for approval by reviewer'
        );

        return response()->json([
            'message'     => __('case.submitted_for_approval'),
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Approve an application (final approval).
     * Requires: applications.approve permission (Approval Officers only)
     */
    public function approve(Request $request, Application $application): JsonResponse
    {
        if (!$request->user()->canApproveApplications()) {
            return response()->json(['message' => 'You do not have permission to approve applications'], 403);
        }

        if ($application->reviewed_by_id === $request->user()->id
            || $application->reviewing_officer_id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot approve an application you reviewed.',
            ], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        if ($application->status !== 'pending_approval') {
            return response()->json(['message' => __('case.invalid_status_for_approval')], 422);
        }

        $application = $this->approvalService->approveApplication($application, $validated['notes']);

        return response()->json([
            'message'     => __('case.approved'),
            'application' => $application,
        ]);
    }

    /**
     * Deny an application.
     * Requires: applications.deny permission (Approval Officers only)
     */
    public function deny(Request $request, Application $application): JsonResponse
    {
        if (!$request->user()->canApproveApplications()) {
            return response()->json(['message' => 'You do not have permission to deny applications'], 403);
        }

        $validated = $request->validate([
            'denial_reason_codes' => 'required|array|min:1',
            'denial_reason_codes.*' => 'required|string|exists:reason_codes,code',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        // Only pending_approval applications can be denied (two-step enforcement)
        if ($application->status !== 'pending_approval') {
            return response()->json(['message' => 'Application must be in pending_approval status to deny'], 422);
        }

        try {
            $application = $this->approvalService->denyApplication(
                $application, 
                $validated['denial_reason_codes'], 
                $validated['notes']
            );

            return response()->json([
                'message'     => __('case.denied'),
                'application' => $application,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Issue a visa for an approved application.
     * Transition: APPROVED → ISSUED
     * Requires: applications.approve permission (Approval Officers only)
     */
    public function issueVisa(Request $request, Application $application): JsonResponse
    {
        if (!$request->user()->canApproveApplications()) {
            return response()->json(['message' => 'You do not have permission to issue visas'], 403);
        }

        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        if ($application->status !== 'approved') {
            return response()->json(['message' => 'Application must be approved before issuing visa'], 422);
        }

        $application = $this->approvalService->issueVisa($application);

        return response()->json([
            'message'     => 'Visa issued successfully',
            'application' => $application,
        ]);
    }

    /**
     * Reverse/revert an approved or denied decision.
     */
    public function revertDecision(Request $request, Application $application): JsonResponse
    {
        if (!$request->user()->canApproveApplications()) {
            return response()->json(['message' => 'Only approvers can revert decisions'], 403);
        }

        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        if (!in_array($application->status, ['approved', 'denied', 'pending_approval'])) {
            return response()->json(['message' => 'Can only revert approved, denied, or pending approval applications'], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $previousStatus = $application->status;
        
        $this->applicationService->changeStatus(
            $application,
            'under_review',
            "Decision reverted from {$previousStatus}: {$validated['reason']}"
        );

        $application->update([
            'decided_at' => null,
            'decision_notes' => null,
        ]);

        return response()->json([
            'message'     => 'Decision reverted successfully',
            'application' => $application->fresh(),
        ]);
    }
}