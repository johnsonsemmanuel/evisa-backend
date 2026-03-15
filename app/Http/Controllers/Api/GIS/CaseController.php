<?php

namespace App\Http\Controllers\Api\GIS;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\InternalNote;
use App\Models\ReasonCode;
use App\Services\ApplicationRoutingService;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function __construct(
        protected ApplicationService $applicationService,
        protected ApplicationRoutingService $routingService,
    ) {}

    /**
     * Get the GIS case queue with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Application::where('assigned_agency', 'gis')
            ->with([
                'visaType:id,name', 
                'assignedOfficer:id,first_name,last_name',
                'reviewingOfficer:id,first_name,last_name',
                'approvalOfficer:id,first_name,last_name',
            ]);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($queue = $request->query('queue')) {
            $query->where('current_queue', $queue);
        }

        if ($tier = $request->query('tier')) {
            $query->where('tier', $tier);
        }

        if ($search = $request->query('search')) {
            $query->where('reference_number', 'like', "%{$search}%");
        }

        $applications = $query->orderByRaw("
            CASE
                WHEN status = 'escalated' THEN 1
                WHEN status = 'pending_approval' THEN 2
                WHEN status = 'under_review' THEN 3
                WHEN status = 'submitted' THEN 4
                ELSE 5
            END
        ")->orderBy('sla_deadline', 'asc')
          ->paginate(20);

        return response()->json([
            'data' => \App\Http\Resources\GIS\CaseListResource::collection($applications->items()),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * Get a single case for review with all details.
     * SECURITY: Verify application belongs to GIS queue.
     */
    public function show(Application $application): JsonResponse
    {
        // IDOR Protection: Ensure application is in GIS queue
        if ($application->assigned_agency !== 'gis') {
            abort(403, 'This application is not assigned to GIS');
        }

        $application->load([
            'visaType:id,name,processing_time_days',
            'documents:id,application_id,document_type,verification_status,created_at',
            'statusHistory.changedByUser:id,first_name,last_name',
            'internalNotes.user:id,first_name,last_name',
            'payment:id,application_id,amount,status,gateway,paid_at',
            'user:id,first_name,last_name,email',
            'riskAssessment:id,application_id,risk_score,risk_level,risk_reasons',
            'assignedOfficer:id,first_name,last_name',
            'reviewingOfficer:id,first_name,last_name',
            'approvalOfficer:id,first_name,last_name',
        ]);

        return response()->json(new \App\Http\Resources\GIS\CaseDetailResource($application));
    }

    /**
     * Assign a case to the current officer.
     */
    public function assignToSelf(Request $request, Application $application): JsonResponse
    {
        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        $application->update(['assigned_officer_id' => $request->user()->id]);
        broadcast(new \App\Events\OfficerAssigned($application->fresh(), $request->user()));

        return response()->json([
            'message'     => __('case.assigned'),
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Escalate a case to MFA for further review.
     */
    public function escalate(Request $request, Application $application): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        $this->routingService->escalateToMfa($application, $validated['reason']);

        // Log the escalation reason as an internal note
        InternalNote::create([
            'application_id' => $application->id,
            'user_id'        => $request->user()->id,
            'content'        => 'Escalated to MFA: ' . $validated['reason'],
            'is_private'     => false,
        ]);

        $this->applicationService->changeStatus(
            $application, 'escalated', 'Escalated to MFA by GIS officer'
        );

        return response()->json([
            'message'     => __('case.escalated'),
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Add an internal note to a case.
     * SECURITY: Verify application belongs to GIS queue.
     */
    public function addNote(Request $request, Application $application): JsonResponse
    {
        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        $validated = $request->validate([
            'content'    => 'required|string|max:2000',
            'is_private' => 'nullable|boolean',
        ]);

        $note = InternalNote::create([
            'application_id' => $application->id,
            'user_id'        => $request->user()->id,
            'content'        => $validated['content'],
            'is_private'     => $validated['is_private'] ?? false,
        ]);

        return response()->json([
            'message' => __('case.note_added'),
            'note'    => $note->load('user:id,first_name,last_name'),
        ], 201);
    }

    /**
     * Request additional information from applicant.
     * SECURITY: Verify application belongs to GIS queue.
     */
    public function requestInfo(Request $request, Application $application): JsonResponse
    {
        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'reason_code' => 'nullable|string|exists:reason_codes,code',
        ]);

        // Build the notes with reason code if provided
        $notes = $validated['message'];
        if (!empty($validated['reason_code'])) {
            $reasonCode = \App\Models\ReasonCode::where('code', $validated['reason_code'])->first();
            if ($reasonCode) {
                $notes = "[{$reasonCode->code}] {$reasonCode->reason}\n\n{$notes}";
            }
        }

        $this->applicationService->changeStatus(
            $application,
            'additional_info_requested',
            $notes
        );

        return response()->json([
            'message'     => __('case.info_requested'),
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Get dashboard metrics for GIS officers.
     */
    public function metrics(): JsonResponse
    {
        $realTimeDashboard = app(\App\Services\RealTimeDashboardService::class);
        $metrics = $realTimeDashboard->getCachedMetrics('gis');

        return response()->json($metrics);
    }

    /**
     * Get reason codes for officer decisions.
     */
    public function reasonCodes(Request $request): JsonResponse
    {
        $query = ReasonCode::active();

        if ($actionType = $request->query('action_type')) {
            $query->forAction($actionType);
        }

        return response()->json([
            'reason_codes' => $query->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Verify or reject a document.
     */
    public function verifyDocument(Request $request, Application $application, ApplicationDocument $document): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:verified,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($document->application_id !== $application->id) {
            return response()->json(['message' => __('case.document_not_found')], 404);
        }

        $document->update([
            'verification_status' => $validated['status'],
            'rejection_reason'    => $validated['status'] === 'rejected' ? ($validated['reason'] ?? 'Document rejected by officer') : null,
        ]);

        // Log the action
        InternalNote::create([
            'application_id' => $application->id,
            'user_id'        => $request->user()->id,
            'content'        => "Document '{$document->document_type}' marked as {$validated['status']}" . 
                               ($validated['reason'] ? ": {$validated['reason']}" : ''),
            'is_private'     => true,
        ]);

        return response()->json([
            'message'  => __('case.document_verified'),
            'document' => $document->fresh(),
        ]);
    }

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

        $application->update([
            'reviewed_by_id' => $request->user()->id,
            'reviewed_at' => now(),
            'current_queue' => 'approval_queue',
        ]);

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

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        // Only pending_approval applications can be approved (two-step enforcement)
        if ($application->status !== 'pending_approval') {
            return response()->json(['message' => __('case.invalid_status_for_approval')], 422);
        }

        $this->applicationService->changeStatus($application, 'approved', $validated['notes'] ?? 'Approved');

        // Generate eVisa PDF
        app(\App\Services\EVisaPdfService::class)->generate($application);

        // Notification is already dispatched by changeStatus()

        return response()->json([
            'message'     => __('case.approved'),
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Deny an application.
     * Requires: applications.deny permission (Approval Officers only)
     */
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

        // Validate all reason codes are of type 'reject'
        $reasonCodes = ReasonCode::whereIn('code', $validated['denial_reason_codes'])
            ->where('action_type', 'reject')
            ->where('is_active', true)
            ->get();

        if ($reasonCodes->count() !== count($validated['denial_reason_codes'])) {
            return response()->json(['message' => 'All denial reason codes must be valid and of type reject'], 422);
        }

        // Build denial notes with all reasons
        $denialNotes = "Application denied for the following reasons:\n\n";
        foreach ($reasonCodes as $reasonCode) {
            $denialNotes .= "• [{$reasonCode->code}] {$reasonCode->reason}\n";
        }

        if (!empty($validated['notes'])) {
            $denialNotes .= "\nAdditional Notes:\n{$validated['notes']}";
        }

        // Store denial reason codes in the application
        $application->update([
            'denial_reason_codes' => $validated['denial_reason_codes'],
        ]);

        $this->applicationService->changeStatus($application, 'denied', $denialNotes);

        return response()->json([
            'message'     => __('case.denied'),
            'application' => $application->fresh(),
        ]);
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

        // Generate eVisa PDF if not already generated
        if (!$application->evisa_file_path) {
            app(\App\Services\EVisaPdfService::class)->generate($application);
        }

        $this->applicationService->changeStatus($application, 'issued', 'Visa issued');

        return response()->json([
            'message'     => 'Visa issued successfully',
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Download/preview a document for an application.
     */
    public function downloadDocument(Application $application, ApplicationDocument $document): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 403);
        }

        if ($document->application_id !== $application->id) {
            return response()->json(['message' => __('case.document_not_found')], 404);
        }

        $path = storage_path('app/' . $document->stored_path);
        
        if (!file_exists($path)) {
            return response()->json(['message' => 'Document file not found'], 404);
        }

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"',
        ]);
    }

    /**
     * Reverse/revert an approved or denied decision.
     */
    public function revertDecision(Request $request, Application $application): JsonResponse
    {
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

    /**
     * Batch assign applications to an officer.
     */
    public function batchAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application_ids' => 'required|array|min:1|max:50',
            'application_ids.*' => 'integer|exists:applications,id',
            'officer_id' => 'required|integer|exists:users,id',
        ]);

        $applications = Application::whereIn('id', $validated['application_ids'])
            ->where('assigned_agency', 'gis')
            ->whereIn('status', ['submitted', 'under_review'])
            ->get();
        $officer = \App\Models\User::find($validated['officer_id']);
        foreach ($applications as $app) {
            $app->update(['assigned_officer_id' => $validated['officer_id']]);
            if ($officer) {
                broadcast(new \App\Events\OfficerAssigned($app->fresh(), $officer));
            }
        }
        $updated = $applications->count();

        return response()->json([
            'message' => "{$updated} applications assigned successfully",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Batch update application status.
     */
    public function batchUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application_ids' => 'required|array|min:1|max:50',
            'application_ids.*' => 'integer|exists:applications,id',
            'status' => 'required|in:under_review,escalated',
            'notes' => 'nullable|string|max:2000',
        ]);

        $applications = Application::whereIn('id', $validated['application_ids'])
            ->where('assigned_agency', 'gis')
            ->get();

        $updated = 0;
        $errors = [];
        foreach ($applications as $application) {
            try {
                if ($validated['status'] === 'escalated') {
                    $this->routingService->escalateToMfa($application, $validated['notes'] ?? 'Batch escalation');
                } else {
                    $this->applicationService->changeStatus(
                        $application,
                        $validated['status'],
                        $validated['notes'] ?? 'Batch status update'
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

        return response()->json([
            'message' => "{$updated} applications updated successfully",
            'updated_count' => $updated,
            'errors' => $errors,
        ]);
    }

    /**
     * Batch approve applications.
     */
    public function batchApprove(Request $request): JsonResponse
    {
        if (!$request->user()->canApproveApplications()) {
            return response()->json(['message' => 'You do not have permission to approve applications'], 403);
        }

        $validated = $request->validate([
            'application_ids' => 'required|array|min:1|max:20',
            'application_ids.*' => 'integer|exists:applications,id',
            'reason_code' => 'nullable|string|exists:reason_codes,code',
            'notes' => 'nullable|string|max:2000',
        ]);

        $applications = Application::whereIn('id', $validated['application_ids'])
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
                    $validated['notes'] ?? 'Batch approval'
                );

                $application->update([
                    'decided_at' => now(),
                    'decision_notes' => $validated['notes'] ?? null,
                ]);

                $approved++;
            } catch (\Exception $e) {
                $errors[] = [
                    'reference_number' => $application->reference_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$approved} applications approved",
            'approved_count' => $approved,
            'errors' => $errors,
        ]);
    }

    /**
     * Batch request additional information.
     */
    public function batchRequestInfo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application_ids' => 'required|array|min:1|max:50',
            'application_ids.*' => 'integer|exists:applications,id',
            'reason_code' => 'required|string|exists:reason_codes,code',
            'notes' => 'required|string|max:2000',
        ]);

        $applications = Application::whereIn('id', $validated['application_ids'])
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
                    $validated['notes']
                );
                $updated++;
            } catch (\Exception $e) {
                $errors[] = [
                    'reference_number' => $application->reference_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$updated} applications updated - additional info requested",
            'updated_count' => $updated,
            'errors' => $errors,
        ]);
    }

    /**
     * Get batch processing statistics.
     */
    public function batchStats(): JsonResponse
    {
        $stats = [
            'available_for_batch' => Application::where('assigned_agency', 'gis')
                ->whereIn('status', ['submitted', 'under_review'])
                ->count(),
            'by_status' => Application::where('assigned_agency', 'gis')
                ->whereIn('status', ['submitted', 'under_review', 'pending_approval'])
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'unassigned' => Application::where('assigned_agency', 'gis')
                ->whereNull('assigned_officer_id')
                ->whereIn('status', ['submitted', 'under_review'])
                ->count(),
        ];

        return response()->json(['stats' => $stats]);
    }
}
