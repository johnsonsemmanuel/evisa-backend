<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\ApplicationPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function __construct(
        protected ApplicationPreviewService $previewService,
    ) {}

    /**
     * Get application preview data (JSON format for frontend display).
     */
    public function getPreview(Request $request, Application $application): JsonResponse
    {
        if ($application->user_id !== $request->user()->id) {
            abort(403, __('auth.unauthorized'));
        }

        $previewData = $this->previewService->getPreviewData($application);

        return response()->json([
            'success' => true,
            'preview' => $previewData,
        ]);
    }

    /**
     * Generate and download application preview PDF.
     */
    public function downloadPreview(Request $request, Application $application)
    {
        if ($application->user_id !== $request->user()->id) {
            abort(403, __('auth.unauthorized'));
        }

        $pdf = $this->previewService->generatePreview($application);

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="application-preview-' . $application->reference_number . '.pdf"');
    }
}
