<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\DocumentService;
use App\Services\FileValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
        protected FileValidationService $fileValidationService,
        protected \App\Services\OcrService $ocrService,
    ) {}

    /**
     * Upload a document for an application.
     * 
     * SECURITY: 5-layer defense-in-depth implementation
     * OWASP A04:2021 — Insecure Design mitigation
     */
    public function upload(Request $request, Application $application): JsonResponse
    {
        // Verify ownership
        if ($application->user_id !== $request->user()->id) {
            abort(403, __('auth.unauthorized'));
        }

        // LAYER 1: Request-level validation (what we claim to accept)
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120', // 5MB max
                'mimetypes:application/pdf,image/jpeg,image/png', // Double-check MIME
            ],
            'document_type' => 'required|string|max:100',
        ]);

        $file = $request->file('file');

        // LAYER 2: Content-level MIME sniffing (what the file actually is)
        // Validates magic numbers, detects malicious content
        $this->fileValidationService->validateFileContent($file);

        // LAYER 3, 4, 5: Storage, signed URLs, hardening (handled in DocumentService)
        $document = $this->documentService->upload($application, $file, $request->input('document_type'));

        return response()->json([
            'message'  => __('document.uploaded'),
            'document' => $document,
        ], 201);
    }

    /**
     * Re-upload a rejected or failed document.
     */
    public function reupload(Request $request, ApplicationDocument $document): JsonResponse
    {
        if ($document->application->user_id !== $request->user()->id) {
            abort(403, __('auth.unauthorized'));
        }

        if (!$document->needsReupload()) {
            return response()->json(['message' => __('document.reupload_not_needed')], 422);
        }

        $request->validate([
            'file' => 'required|file|max:5120|mimes:jpeg,jpg,png,pdf',
        ]);

        $document = $this->documentService->reupload($document, $request->file('file'));

        return response()->json([
            'message'  => __('document.reuploaded'),
            'document' => $document,
        ]);
    }

    /**
     * Check document completeness for an application.
     */
    public function checkCompleteness(Request $request, Application $application): JsonResponse
    {
        if ($application->user_id !== $request->user()->id) {
            abort(403, __('auth.unauthorized'));
        }

        $result = $this->documentService->validateCompleteness($application);

        return response()->json($result);
    }

    /**
     * Serve a document via signed URL.
     * 
     * LAYER 4: Signed URL serving with security headers
     * SECURITY: Prevents direct file access, enforces ownership, adds CSP headers
     */
    public function serve(Request $request, ApplicationDocument $document): Response
    {
        // Verify ownership via application relationship
        if ($document->application->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to document');
        }

        // Verify signed URL (prevents URL tampering)
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired download link');
        }

        // Log document access (audit trail)
        \Log::channel('document_access')->info('Document accessed', [
            'document_id' => $document->id,
            'application_id' => $document->application_id,
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Stream the file with security headers
        return Storage::disk('private')->response(
            $document->stored_path,
            $document->original_filename,
            [
                // Force download (prevent inline execution)
                'Content-Disposition' => 'attachment; filename="' . $document->original_filename . '"',
                
                // Prevent any script execution
                'Content-Security-Policy' => "default-src 'none'",
                
                // Prevent MIME sniffing
                'X-Content-Type-Options' => 'nosniff',
                
                // Prevent framing
                'X-Frame-Options' => 'DENY',
            ]
        );
    }

    /**
     * Generate a temporary signed download URL for a document.
     */
    public function getDownloadUrl(Request $request, ApplicationDocument $document): JsonResponse
    {
        // Verify ownership
        if ($document->application->user_id !== $request->user()->id) {
            abort(403, __('auth.unauthorized'));
        }

        // Generate signed URL (expires in 15 minutes)
        $url = URL::temporarySignedRoute(
            'documents.serve',
            now()->addMinutes(15),
            ['document' => $document->id]
        );

        return response()->json([
            'download_url' => $url,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]);
    }
}