<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    /**
     * Store a document securely for an application.
     */
    public function upload(Application $application, UploadedFile $file, string $documentType): ApplicationDocument
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "applications/{$application->reference_number}/{$documentType}";

        $storedPath = $file->storeAs($path, $filename, 'secure');

        return ApplicationDocument::create([
            'application_id'      => $application->id,
            'document_type'       => $documentType,
            'original_filename'   => $file->getClientOriginalName(),
            'stored_path'         => $storedPath,
            'mime_type'           => $file->getMimeType(),
            'file_size'           => $file->getSize(),
            'ocr_status'          => 'pending',
            'verification_status' => 'pending',
        ]);
    }

    /**
     * Replace an existing document (re-upload flow).
     */
    public function reupload(ApplicationDocument $document, UploadedFile $file): ApplicationDocument
    {
        // Delete old file
        Storage::disk('secure')->delete($document->stored_path);

        $application = $document->application;
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "applications/{$application->reference_number}/{$document->document_type}";

        $storedPath = $file->storeAs($path, $filename, 'secure');

        $document->update([
            'original_filename'   => $file->getClientOriginalName(),
            'stored_path'         => $storedPath,
            'mime_type'           => $file->getMimeType(),
            'file_size'           => $file->getSize(),
            'ocr_status'          => 'pending',
            'verification_status' => 'pending',
            'rejection_reason'    => null,
        ]);

        return $document->fresh();
    }

    /**
     * Validate that all required documents for a visa type have been uploaded.
     */
    public function validateCompleteness(Application $application): array
    {
        $requiredDocs = $application->visaType->required_documents ?? [];
        $uploadedTypes = $application->documents()->pluck('document_type')->toArray();

        $missing = array_diff($requiredDocs, $uploadedTypes);

        return [
            'complete' => empty($missing),
            'missing'  => array_values($missing),
            'uploaded' => $uploadedTypes,
        ];
    }

    /**
     * Get the secure download URL for a document.
     */
    public function getTemporaryUrl(ApplicationDocument $document, int $expiryMinutes = 15): ?string
    {
        if (Storage::disk('secure')->exists($document->stored_path)) {
            return Storage::disk('secure')->temporaryUrl($document->stored_path, now()->addMinutes($expiryMinutes));
        }
        return null;
    }

    /**
     * Validate file meets upload requirements.
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            $errors[] = 'File type not allowed. Accepted: JPEG, PNG, PDF.';
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file->getSize() > $maxSize) {
            $errors[] = 'File size exceeds 5MB limit.';
        }

        return $errors;
    }
}
