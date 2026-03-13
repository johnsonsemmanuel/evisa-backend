<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EtaApplication;
use App\Models\SumsubVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class SumsubService
{
    protected string $appToken;
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->appToken = config('sumsub.app_token');
        $this->secretKey = config('sumsub.secret_key');
        $this->baseUrl = config('sumsub.base_url', 'https://api.sumsub.com');
    }

    /**
     * Generate access token for Sumsub SDK.
     */
    public function generateAccessToken(string $externalUserId, string $levelName = 'basic-kyc-level'): array
    {
        $timestamp = time();
        $method = 'POST';
        $url = '/resources/accessTokens?userId=' . $externalUserId . '&levelName=' . $levelName;
        
        $body = json_encode([
            'userId' => $externalUserId,
            'levelName' => $levelName,
            'ttlInSecs' => 3600, // 1 hour
        ]);

        $signature = $this->generateSignature($method, $url, $body, $timestamp);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-App-Token' => $this->appToken,
                'X-App-Access-Sig' => $signature,
                'X-App-Access-Ts' => $timestamp,
            ])->post($this->baseUrl . $url, json_decode($body, true));

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Sumsub access token generated', [
                    'external_user_id' => $externalUserId,
                    'level_name' => $levelName,
                ]);

                return [
                    'success' => true,
                    'token' => $data['token'],
                    'userId' => $data['userId'],
                ];
            }

            Log::error('Failed to generate Sumsub access token', [
                'external_user_id' => $externalUserId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate access token',
                'details' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Sumsub access token generation exception', [
                'external_user_id' => $externalUserId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Service unavailable',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get applicant status from Sumsub.
     */
    public function getApplicantStatus(string $applicantId): array
    {
        $timestamp = time();
        $method = 'GET';
        $url = '/resources/applicants/' . $applicantId . '/status';

        $signature = $this->generateSignature($method, $url, '', $timestamp);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-App-Token' => $this->appToken,
                'X-App-Access-Sig' => $signature,
                'X-App-Access-Ts' => $timestamp,
            ])->get($this->baseUrl . $url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get applicant status',
                'details' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Sumsub get applicant status exception', [
                'applicant_id' => $applicantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Service unavailable',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook from Sumsub.
     */
    public function handleWebhook(array $payload): bool
    {
        try {
            $applicantId = $payload['applicantId'] ?? null;
            $inspectionId = $payload['inspectionId'] ?? null;
            $correlationId = $payload['correlationId'] ?? null;
            $levelName = $payload['levelName'] ?? null;
            $externalUserId = $payload['externalUserId'] ?? null;
            $type = $payload['type'] ?? null;
            $reviewStatus = $payload['reviewStatus'] ?? null;
            $createdAt = $payload['createdAt'] ?? null;

            if (!$applicantId) {
                Log::warning('Sumsub webhook missing applicantId', $payload);
                return false;
            }

            // Find existing verification record
            $verification = SumsubVerification::where('applicant_id', $applicantId)->first();

            if (!$verification) {
                Log::warning('Sumsub webhook for unknown applicant', [
                    'applicant_id' => $applicantId,
                    'external_user_id' => $externalUserId,
                ]);
                return false;
            }

            // Update verification status
            $verification->update([
                'verification_status' => $this->mapVerificationStatus($type),
                'review_result' => $this->mapReviewResult($reviewStatus),
                'verification_data' => $payload,
                'reviewed_at' => $createdAt ? now()->parse($createdAt) : now(),
            ]);

            // Update related application
            $this->updateRelatedApplication($verification, $reviewStatus);

            Log::info('Sumsub webhook processed', [
                'applicant_id' => $applicantId,
                'type' => $type,
                'review_status' => $reviewStatus,
                'verification_id' => $verification->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Sumsub webhook processing failed', [
                'payload' => $payload,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Create verification record for application.
     */
    public function createVerificationForApplication($application, string $applicationType = 'visa'): SumsubVerification
    {
        $externalUserId = $this->generateExternalUserId($application, $applicationType);

        return SumsubVerification::create([
            'application_id' => $applicationType === 'visa' ? $application->id : null,
            'eta_application_id' => $applicationType === 'eta' ? $application->id : null,
            'applicant_id' => '', // Will be set when user starts verification
            'external_user_id' => $externalUserId,
            'verification_status' => 'pending',
            'review_result' => 'init',
        ]);
    }

    /**
     * Update applicant ID when verification starts.
     */
    public function updateApplicantId(string $externalUserId, string $applicantId): bool
    {
        $verification = SumsubVerification::where('external_user_id', $externalUserId)->first();

        if ($verification) {
            $verification->update([
                'applicant_id' => $applicantId,
                'submitted_at' => now(),
            ]);

            // Update related application
            $application = $verification->getRelatedApplication();
            if ($application) {
                $application->update([
                    'sumsub_applicant_id' => $applicantId,
                    'sumsub_verification_status' => 'pending',
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * Check if verification is required for application.
     */
    public function isVerificationRequired($application, string $applicationType = 'visa'): bool
    {
        if (!config('sumsub.enabled', false)) {
            return false;
        }

        if ($applicationType === 'eta') {
            return config('sumsub.eta_enabled', false);
        }

        // For visa applications, check tier
        $requiredTiers = explode(',', config('sumsub.required_tiers', 'priority,express'));
        $processingTier = $application->processing_tier ?? 'standard';

        return in_array($processingTier, $requiredTiers);
    }

    /**
     * Generate signature for Sumsub API.
     */
    protected function generateSignature(string $method, string $url, string $body, int $timestamp): string
    {
        $data = $timestamp . $method . $url . $body;
        return hash_hmac('sha256', $data, $this->secretKey);
    }

    /**
     * Generate external user ID.
     */
    protected function generateExternalUserId($application, string $applicationType): string
    {
        $prefix = $applicationType === 'eta' ? 'ETA' : 'VISA';
        return $prefix . '_' . $application->id . '_' . time();
    }

    /**
     * Map Sumsub verification status to our status.
     */
    protected function mapVerificationStatus(?string $type): string
    {
        return match($type) {
            'applicantCreated' => 'pending',
            'applicantPending' => 'queued',
            'applicantReviewed' => 'completed',
            'applicantActionReviewed' => 'completed',
            default => 'pending',
        };
    }

    /**
     * Map Sumsub review result to our result.
     */
    protected function mapReviewResult(?string $reviewStatus): string
    {
        return match($reviewStatus) {
            'completed' => 'approved',
            'rejected' => 'rejected',
            'pending' => 'pending',
            default => 'init',
        };
    }

    /**
     * Update related application based on verification result.
     */
    protected function updateRelatedApplication(SumsubVerification $verification, ?string $reviewStatus): void
    {
        $application = $verification->getRelatedApplication();

        if (!$application) {
            return;
        }

        $application->update([
            'sumsub_verification_status' => $verification->verification_status,
            'sumsub_review_result' => $verification->review_result,
            'sumsub_verified_at' => $verification->reviewed_at,
        ]);

        // If approved, continue with application processing
        if ($reviewStatus === 'completed' && $verification->review_result === 'approved') {
            $this->processApprovedVerification($application, $verification);
        }

        // If rejected, update application status
        if ($verification->review_result === 'rejected') {
            $this->processRejectedVerification($application, $verification);
        }
    }

    /**
     * Process approved verification.
     */
    protected function processApprovedVerification($application, SumsubVerification $verification): void
    {
        // For ETA applications, continue with screening
        if ($application instanceof EtaApplication) {
            // Continue with ETA screening process
            $screeningService = app(EtaScreeningService::class);
            $screeningService->performScreening($application);
        }

        // For visa applications, continue with routing
        if ($application instanceof Application) {
            // Continue with visa processing workflow
            // This could trigger routing, officer assignment, etc.
        }

        Log::info('Sumsub verification approved, continuing processing', [
            'verification_id' => $verification->id,
            'application_type' => get_class($application),
            'application_id' => $application->id,
        ]);
    }

    /**
     * Process rejected verification.
     */
    protected function processRejectedVerification($application, SumsubVerification $verification): void
    {
        // For ETA applications, set status to flagged
        if ($application instanceof EtaApplication) {
            $application->update([
                'status' => 'flagged',
                'screening_notes' => json_encode(['Sumsub verification rejected']),
            ]);
        }

        // For visa applications, flag for manual review
        if ($application instanceof Application) {
            $application->update([
                'status' => 'flagged_for_review',
            ]);
        }

        Log::info('Sumsub verification rejected, flagging application', [
            'verification_id' => $verification->id,
            'application_type' => get_class($application),
            'application_id' => $application->id,
        ]);
    }
}