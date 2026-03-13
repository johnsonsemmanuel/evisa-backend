<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verification API for Border/Airline Portal
 * 
 * Provides QR Code validation endpoint for verifying eVisas
 * at immigration checkpoints and airline check-in.
 */
class VerificationController extends Controller
{
    /**
     * Validate an eVisa by QR code or reference number.
     * 
     * This endpoint is used by border control and airlines to verify
     * the authenticity of an eVisa.
     */
    public function validateEvisa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string', // QR code content or reference number
            'passport_number' => 'nullable|string', // Optional passport verification
        ]);

        // Extract reference number from QR code
        // QR format: GHEVISA:GH-2026-000001:CHECKSUM
        $code = $validated['code'];
        $referenceNumber = $this->extractReferenceNumber($code);

        if (!$referenceNumber) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid QR code format',
            ], 400);
        }

        $application = Application::where('reference_number', $referenceNumber)->first();

        if (!$application) {
            return response()->json([
                'valid' => false,
                'message' => 'eVisa not found',
                'reference_number' => $referenceNumber,
            ], 404);
        }

        // Verify passport number if provided
        if (isset($validated['passport_number'])) {
            $passportMatch = strtoupper(trim($application->passport_number_encrypted)) === 
                             strtoupper(trim($validated['passport_number']));
            if (!$passportMatch) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Passport number does not match',
                    'reference_number' => $referenceNumber,
                ], 422);
            }
        }

        // Check if eVisa is approved
        if ($application->status !== 'approved') {
            return response()->json([
                'valid' => false,
                'message' => 'eVisa is not approved',
                'reference_number' => $referenceNumber,
                'status' => $application->status,
            ], 422);
        }

        // Check if QR code matches (if stored)
        if ($application->evisa_qr_code && $code !== $application->evisa_qr_code) {
            // Verify checksum
            if (!$this->verifyChecksum($code, $application)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'QR code verification failed',
                    'reference_number' => $referenceNumber,
                ], 422);
            }
        }

        // Return verification result
        return response()->json([
            'valid' => true,
            'message' => 'eVisa verified successfully',
            'reference_number' => $application->reference_number,
            'visa_type' => $application->visaType->name ?? null,
            'holder_name' => $application->first_name_encrypted . ' ' . $application->last_name_encrypted,
            'nationality' => $application->nationality_encrypted,
            'valid_from' => $application->decided_at?->format('Y-m-d'),
            'valid_until' => $application->intended_arrival?->addDays($application->duration_days)->format('Y-m-d'),
            'duration_days' => $application->duration_days,
            'purpose' => $application->purpose_of_visit,
            'issued_at' => $application->decided_at?->toIso8601String(),
        ]);
    }

    /**
     * Extract reference number from QR code content.
     */
    protected function extractReferenceNumber(string $code): ?string
    {
        // Handle direct reference number
        if (preg_match('/^GH-\d{4}-\d{6}$/', $code)) {
            return $code;
        }

        // Handle QR format: GHEVISA:GH-2026-000001:CHECKSUM
        if (preg_match('/^GHEVISA:(GH-\d{4}-\d{6}):/', $code, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Verify QR code checksum.
     */
    protected function verifyChecksum(string $code, Application $application): bool
    {
        // Extract checksum from QR code
        $parts = explode(':', $code);
        if (count($parts) < 3) {
            return false;
        }

        $providedChecksum = $parts[2];
        
        // Generate expected checksum
        $data = $application->reference_number . $application->passport_number_encrypted . $application->decided_at?->timestamp;
        $expectedChecksum = substr(hash('sha256', $data), 0, 8);

        return strtoupper($providedChecksum) === strtoupper($expectedChecksum);
    }

    /**
     * Get eVisa status by reference number (public endpoint).
     */
    public function getStatus(string $referenceNumber): JsonResponse
    {
        $application = Application::where('reference_number', $referenceNumber)->first();

        if (!$application) {
            return response()->json([
                'found' => false,
                'message' => 'Application not found',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'reference_number' => $application->reference_number,
            'status' => $application->status,
            'visa_type' => $application->visaType->name ?? null,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'decided_at' => $application->decided_at?->toIso8601String(),
        ]);
    }
}
