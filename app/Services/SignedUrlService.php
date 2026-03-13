<?php

namespace App\Services;

use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Government-Grade Signed URL Service
 * Generates time-limited, cryptographically signed URLs for secure document access.
 */
class SignedUrlService
{
    /**
     * Default URL expiration in minutes.
     */
    protected int $defaultExpiration = 15;

    /**
     * Generate a signed URL for document download.
     */
    public function generateDocumentUrl(ApplicationDocument $document, int $expirationMinutes = null): string
    {
        $expiration = $expirationMinutes ?? $this->defaultExpiration;

        return URL::temporarySignedRoute(
            'documents.secure-download',
            now()->addMinutes($expiration),
            [
                'document' => $document->id,
                'token' => $this->generateAccessToken($document),
            ]
        );
    }

    /**
     * Generate a secure access token for the document.
     */
    protected function generateAccessToken(ApplicationDocument $document): string
    {
        $payload = [
            'document_id' => $document->id,
            'application_id' => $document->application_id,
            'timestamp' => now()->timestamp,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Validate an access token.
     */
    public function validateAccessToken(string $token, ApplicationDocument $document): bool
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);

            if (!$payload) {
                return false;
            }

            // Verify document ID matches
            if ($payload['document_id'] !== $document->id) {
                return false;
            }

            // Verify application ID matches
            if ($payload['application_id'] !== $document->application_id) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            \Log::channel('security')->warning('Invalid document access token', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate a signed URL for eVisa download.
     */
    public function generateEvisaUrl(int $applicationId, int $expirationMinutes = 30): string
    {
        return URL::temporarySignedRoute(
            'evisa.secure-download',
            now()->addMinutes($expirationMinutes),
            [
                'application' => $applicationId,
                'token' => $this->generateEvisaToken($applicationId),
            ]
        );
    }

    /**
     * Generate eVisa access token.
     */
    protected function generateEvisaToken(int $applicationId): string
    {
        $payload = [
            'application_id' => $applicationId,
            'timestamp' => now()->timestamp,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Log document access for audit trail.
     */
    public function logDocumentAccess(ApplicationDocument $document, int $userId, string $ipAddress): void
    {
        \Log::channel('document_access')->info('Document accessed', [
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'application_id' => $document->application_id,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
