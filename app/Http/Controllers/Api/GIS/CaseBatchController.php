<?php

namespace App\Http\Controllers\Api\GIS;

use App\Http\Controllers\Controller;
use App\Services\CaseBatchProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseBatchController extends Controller
{
    public function __construct(
        protected CaseBatchProcessingService $batchService,
    ) {}

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

        $result = $this->batchService->batchAssign(
            $validated['application_ids'],
            $validated['officer_id']
        );

        return response()->json([
            'message' => "{$result['updated_count']} applications assigned successfully",
            'updated_count' => $result['updated_count'],
            'errors' => $result['errors'],
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

        $result = $this->batchService->batchUpdateStatus(
            $validated['application_ids'],
            $validated['status'],
            $validated['notes']
        );

        return response()->json([
            'message' => "{$result['updated_count']} applications updated successfully",
            'updated_count' => $result['updated_count'],
            'errors' => $result['errors'],
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

        $result = $this->batchService->batchApprove(
            $validated['application_ids'],
            $validated['notes']
        );

        return response()->json([
            'message' => "{$result['approved_count']} applications approved",
            'approved_count' => $result['approved_count'],
            'errors' => $result['errors'],
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

        $result = $this->batchService->batchRequestInfo(
            $validated['application_ids'],
            $validated['notes']
        );

        return response()->json([
            'message' => "{$result['updated_count']} applications updated - additional info requested",
            'updated_count' => $result['updated_count'],
            'errors' => $result['errors'],
        ]);
    }
}