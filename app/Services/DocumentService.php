<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DocumentService
 * 
 * Secure document storage and management.
 * Implements OWASP A04:2021 — Insecure Design mitigation.
 * 
 * SECURITY LAYERS:
 * 3. Storage outside webroot with randomized filenames
 * 4. Signed URL serving only
 * 5. EXIF stripping, security headers, audit logging
 * 
 * @package App\Services
 */
class DocumentService
{
    public function __construct(
        protected FileValidationService $fileValidationService
    ) {}

    /**
     * Store a document securely for an application.
     * 
     * LAYER 3: Storage location (outside webroot)
     * LAYER 5: Additional hardening (EXIF stripping, randomized filename)
     */
    public function upload(Application $application, UploadedFile $file, string $documentType): ApplicationDocument
    {
        // LAYER 3: Generate secure storage path
        // - UUID filename (never use original filename)
        // - Path derived from application ID (not user input)
        // - Stored in private disk (outside webroot)
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "documents/{$application->id}/{$documentType}";
        $fullPath = "{$path}/{$filename}";

        // LAYER 5: Strip EXIF data from images (privacy + security)
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $tempPath = storage_path('app/temp/' . $filename);
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            // Strip EXIF and save to temp
            if ($this->fileValidationService->stripExifData($file, $tempPath)) {
                // Store the cleaned file
                Storage::disk('private')->put($fullPath, file_get_contents($tempPath));
                unlink($tempPath); // Clean up temp file
            } else {
                // Fallback: store original if EXIF stripping fails
                Storage::disk('private')->putFileAs($path, $file, $filename);
            }
        } else {
            // For PDFs, store directly
            Storage::disk('private')->putFileAs($path, $file, $filename);
        }

        // Create database record
        return ApplicationDocument::create([
            'application_id'      => $application->id,
            'document_type'       => $documentType,
            'original_filename'   => $file->getClientOriginalName(),
            'stored_path'         => $fullPath,
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
        Storage::disk('private')->delete($document->stored_path);

        $application = $document->application;
        
        // Generate new secure filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "documents/{$application->id}/{$document->document_type}";
        $fullPath = "{$path}/{$filename}";

        // Strip EXIF data from images
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $tempPath = storage_path('app/temp/' . $filename);
            
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            if ($this->fileValidationService->stripExifData($file, $tempPath)) {
                Storage::disk('private')->put($fullPath, file_get_contents($tempPath));
                unlink($tempPath);
            } else {
                Storage::disk('private')->putFileAs($path, $file, $filename);
            }
        } else {
            Storage::disk('private')->putFileAs($path, $file, $filename);
        }

        $document->update([
            'original_filename'   => $file->getClientOriginalName(),
            'stored_path'         => $fullPath,
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
     * 
     * LAYER 4: Signed URL generation (15-minute expiry)
     */
    public function getTemporaryUrl(ApplicationDocument $document, int $expiryMinutes = 15): ?string
    {
        if (Storage::disk('private')->exists($document->stored_path)) {
            return \URL::temporarySignedRoute(
                'documents.serve',
                now()->addMinutes($expiryMinutes),
                ['document' => $document->id]
            );
        }
        return null;
    }

    /**
     * Validate file meets upload requirements.
     * 
     * @deprecated Use FileValidationService::validateFileContent() instead
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        $allowedMimes = $this->fileValidationService->getAllowedMimeTypes();
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
