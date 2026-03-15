<?php

namespace App\Http\Controllers\Api\GIS;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ReasonCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseUtilityController extends Controller
{
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
}