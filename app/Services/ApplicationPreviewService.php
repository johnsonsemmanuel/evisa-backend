<?php

namespace App\Services;

use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Service for generating application preview PDFs.
 */
class ApplicationPreviewService
{
    /**
     * Generate a preview PDF of the application before submission.
     */
    public function generatePreview(Application $application): string
    {
        $application->load(['visaType', 'serviceTier', 'documents']);

        $data = [
            'application' => $application,
            'applicant_name' => $application->first_name . ' ' . $application->last_name,
            'visa_type' => $application->visaType->name ?? 'N/A',
            'service_tier' => $application->serviceTier->name ?? 'Standard',
            'entry_type' => ucfirst($application->entry_type ?? 'single'),
            'visa_channel' => ucfirst($application->visa_channel ?? 'e-visa'),
            'documents' => $application->documents,
            'preview_date' => now()->format('d M Y H:i'),
        ];

        $pdf = Pdf::loadView('pdf.application-preview', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Get application data formatted for preview display.
     */
    public function getPreviewData(Application $application): array
    {
        return [
            'reference_number' => $application->reference_number,
            'status' => $application->status,
            
            // Personal Information
            'personal' => [
                'full_name' => $application->first_name . ' ' . $application->last_name,
                'date_of_birth' => $application->date_of_birth,
                'gender' => ucfirst($application->gender ?? ''),
                'nationality' => $application->nationality,
                'email' => $application->email,
                'phone' => $application->phone,
            ],
            
            // Passport Information
            'passport' => [
                'number' => $application->passport_number,
                'issue_date' => $application->passport_issue_date,
                'expiry_date' => $application->passport_expiry,
            ],
            
            // Visa Information
            'visa' => [
                'type' => $application->visaType->name ?? 'N/A',
                'channel' => ucfirst($application->visa_channel ?? 'e-visa'),
                'entry_type' => ucfirst($application->entry_type ?? 'single'),
                'processing_tier' => $application->serviceTier->name ?? 'Standard',
            ],
            
            // Travel Information
            'travel' => [
                'intended_arrival' => $application->intended_arrival,
                'duration_days' => $application->duration_days,
                'purpose' => $application->purpose_of_visit,
                'address_in_ghana' => $application->address_in_ghana,
            ],
            
            // Documents
            'documents' => $application->documents->map(fn($doc) => [
                'type' => $doc->document_type,
                'filename' => $doc->original_filename,
                'uploaded_at' => $doc->created_at->format('d M Y'),
            ]),
            
            // Fees
            'fees' => [
                'total' => $application->total_fee ?? 0,
                'government_fee' => $application->government_fee ?? 0,
                'platform_fee' => $application->platform_fee ?? 0,
                'processing_fee' => $application->processing_fee ?? 0,
            ],
        ];
    }
}
