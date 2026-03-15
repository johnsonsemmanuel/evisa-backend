<?php

namespace App\Http\Controllers\Api\GIS;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\InternalNote;
use App\Services\ApplicationRoutingService;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseReviewController extends Controller
{
    public function __construct(
        protected ApplicationService $applicationService,
        protected ApplicationRoutingService $routingService,
    ) {}

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
}