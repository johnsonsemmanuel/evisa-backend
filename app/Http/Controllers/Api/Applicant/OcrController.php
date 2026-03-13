<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Services\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OcrController extends Controller
{
    public function __construct(
        protected OcrService $ocrService,
    ) {}

    /**
     * Extract passport data from an uploaded image using OCR.
     */
    public function extractPassportData(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:jpeg,jpg,png,pdf',
            ],
        ]);

        $file = $request->file('file');
        $result = $this->ocrService->extractPassportData($file);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'OCR extraction failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Passport data extracted successfully',
            'data' => $result['data'],
            'confidence' => $result['confidence'],
            'note' => 'Please verify all extracted information before submitting',
        ]);
    }
}
