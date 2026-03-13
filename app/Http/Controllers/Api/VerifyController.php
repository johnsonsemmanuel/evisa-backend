<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;

class VerifyController extends Controller
{
    /**
     * Verify an eVisa by QR code.
     * This endpoint is public and used by border officers.
     */
    public function verify(string $code): JsonResponse
    {
        // Parse the QR code format: GHEVISA:REFERENCE:CHECKSUM
        $parts = explode(':', $code);
        
        if (count($parts) !== 3 || $parts[0] !== 'GHEVISA') {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid QR code format. This does not appear to be a valid Ghana eVisa.',
            ]);
        }

        $reference = $parts[1];
        $checksum = $parts[2];

        // Find the application
        $application = Application::where('reference_number', $reference)
            ->whereIn('status', ['approved', 'issued'])
            ->first();

        if (!$application) {
            return response()->json([
                'valid' => false,
                'message' => 'No valid eVisa found with this reference number. The visa may have been revoked or does not exist.',
            ]);
        }

        // Verify checksum
        $expectedChecksum = $this->generateChecksum($application);
        if ($checksum !== $expectedChecksum) {
            return response()->json([
                'valid' => false,
                'message' => 'Document verification failed. The QR code checksum does not match. This document may have been tampered with.',
            ]);
        }

        // Check if visa is still valid (if valid_until date exists)
        if ($application->intended_arrival && $application->duration_days) {
            $validUntil = $application->intended_arrival->copy()->addDays($application->duration_days);
            if ($validUntil->isPast()) {
                return response()->json([
                    'valid' => false,
                    'message' => 'This eVisa has expired. The validity period ended on ' . $validUntil->format('d M Y') . '.',
                ]);
            }
        }

        // Return verified application data
        return response()->json([
            'valid' => true,
            'application' => [
                'reference_number' => $application->reference_number,
                'full_name' => $application->first_name . ' ' . $application->last_name,
                'passport_number' => $application->passport_number,
                'nationality' => $application->nationality,
                'visa_type' => $application->visaType->name ?? 'N/A',
                'arrival_date' => $application->intended_arrival ? $application->intended_arrival->format('d M Y') : 'N/A',
                'duration_days' => $application->duration_days ?? 0,
                'issued_at' => $application->decided_at ? $application->decided_at->format('d M Y') : 'N/A',
                'valid_until' => $application->intended_arrival && $application->duration_days 
                    ? $application->intended_arrival->copy()->addDays($application->duration_days)->format('d M Y') 
                    : 'N/A',
                'status' => $application->status,
            ],
        ]);
    }

    /**
     * Generate checksum for verification.
     */
    private function generateChecksum(Application $application): string
    {
        $data = $application->reference_number . 
                $application->passport_number . 
                $application->decided_at?->timestamp;
        
        return strtoupper(substr(hash('sha256', $data), 0, 8));
    }
}
