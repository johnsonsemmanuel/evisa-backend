<?php

namespace App\Services;

use App\Enums\KycStatus;
use App\Exceptions\SumsubApiException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SumsubService
{
    private string $appToken;
    private string $secretKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->appToken = config('sumsub.app_token');
        $this->secretKey = config('sumsub.secret_key');
        $this->baseUrl = rtrim(config('sumsub.base_url', 'https://api.sumsub.com'), '/');
        $this->timeout = (int) config('sumsub.timeout', 30);

        if ($this->baseUrl && class_exists(\App\Services\ExternalUrlValidator::class)) {
            app(\App\Services\ExternalUrlValidator::class)->validateExternalUrl($this->baseUrl);
        }
    }

    /**
     * Create applicant in Sumsub; store applicantId on user.
     * POST /resources/applicants?levelName={levelName}
     */
    public function createApplicant(User $applicant): array
    {
        $levelName = config('sumsub.kyc_level', 'basic-kyc-level');
        $path = '/resources/applicants?levelName=' . rawurlencode($levelName);
        $body = json_encode([
            'externalUserId' => (string) $applicant->id,
            'email' => $applicant->email ?? '',
            'phone' => $applicant->phone ?? '',
        ]);
        $headers = $this->signRequest('POST', $path, $body);

        $response = $this->client()->withHeaders($headers)->post($this->baseUrl . $path, json_decode($body, true));

        if (!$response->successful()) {
            $this->throwFromResponse($response, null);
        }

        $data = $response->json();
        $applicantId = $data['id'] ?? null;
        if (!$applicantId) {
            throw new SumsubApiException('Sumsub response missing applicant id', 0, null, $response->status(), null, null);
        }

        $applicant->update([
            'sumsub_applicant_id' => $applicantId,
            'kyc_level' => $levelName,
        ]);

        Log::info('Sumsub applicant created', ['applicant_id' => $applicantId, 'user_id' => $applicant->id]);

        return [
            'applicant_id' => $applicantId,
            'created_at' => $data['createdAt'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Get required ID docs status; map to KycStatus.
     * GET /resources/applicants/{applicantId}/requiredIdDocsStatus
     */
    public function getApplicantStatus(string $sumsubApplicantId): array
    {
        $path = '/resources/applicants/' . rawurlencode($sumsubApplicantId) . '/requiredIdDocsStatus';
        $headers = $this->signRequest('GET', $path, '');

        $response = $this->client()->withHeaders($headers)->get($this->baseUrl . $path);

        if (!$response->successful()) {
            $this->throwFromResponse($response, $sumsubApplicantId);
        }

        $data = $response->json();
        $reviewStatus = $data['reviewStatus'] ?? $data['review']['reviewStatus'] ?? null;
        $reviewResult = $data['reviewResult'] ?? $data['review']['reviewResult'] ?? null;
        $answer = $reviewResult['reviewAnswer'] ?? $reviewStatus ?? null;

        $kycStatus = $this->mapToKycStatus($answer, $data);

        return [
            'review_status' => $reviewStatus,
            'review_result' => $reviewResult,
            'kyc_status' => $kycStatus,
            'data' => $data,
        ];
    }

    /**
     * Full applicant record including review.reviewResult.
     * GET /resources/applicants/{applicantId}/one
     */
    public function getApplicantReviewResult(string $sumsubApplicantId): array
    {
        $path = '/resources/applicants/' . rawurlencode($sumsubApplicantId) . '/one';
        $headers = $this->signRequest('GET', $path, '');

        $response = $this->client()->withHeaders($headers)->get($this->baseUrl . $path);

        if (!$response->successful()) {
            $this->throwFromResponse($response, $sumsubApplicantId);
        }

        $data = $response->json();
        $review = $data['review'] ?? [];
        $reviewResult = $review['reviewResult'] ?? [];
        $rejectionLabels = $reviewResult['rejectLabels'] ?? $reviewResult['rejectionLabels'] ?? [];

        return [
            'applicant' => $data,
            'review' => $review,
            'review_result' => $reviewResult,
            'rejection_labels' => $rejectionLabels,
        ];
    }

    /**
     * Generate access token for Sumsub SDK (frontend).
     * POST /resources/accessTokens?userId=...&levelName=...&ttlInSecs=...
     */
    public function generateAccessToken(User $applicant, int $ttlInSecs = 3600): string
    {
        $externalUserId = (string) $applicant->id;
        $levelName = $applicant->kyc_level ?? config('sumsub.kyc_level', 'basic-kyc-level');
        $path = '/resources/accessTokens?userId=' . rawurlencode($externalUserId)
            . '&levelName=' . rawurlencode($levelName)
            . '&ttlInSecs=' . $ttlInSecs;
        $headers = $this->signRequest('POST', $path, '');

        $response = $this->client()->withHeaders($headers)->post($this->baseUrl . $path);

        if (!$response->successful()) {
            $this->throwFromResponse($response, $applicant->sumsub_applicant_id);
        }

        $token = $response->json('token');
        if (!$token) {
            throw new SumsubApiException('Sumsub response missing token', 0, null, $response->status(), null, $applicant->sumsub_applicant_id);
        }

        Cache::put("sumsub_token:{$applicant->id}", $token, $ttlInSecs - 60);

        return $token;
    }

    /**
     * Reset applicant for re-do KYC.
     * POST /resources/applicants/{applicantId}/reset
     */
    public function resetApplicant(string $sumsubApplicantId): void
    {
        $path = '/resources/applicants/' . rawurlencode($sumsubApplicantId) . '/reset';
        $headers = $this->signRequest('POST', $path, '');

        $response = $this->client()->withHeaders($headers)->post($this->baseUrl . $path);

        if (!$response->successful()) {
            $this->throwFromResponse($response, $sumsubApplicantId);
        }

        Log::info('Sumsub applicant reset', ['applicant_id' => $sumsubApplicantId]);
    }

    /**
     * Sign request: returns headers X-App-Token, X-App-Access-Sig, X-App-Access-Ts.
     * Signature = HMAC-SHA256(ts + method + path + body, secret_key).
     */
    private function signRequest(string $method, string $path, string $body = ''): array
    {
        $ts = (string) time();
        $payload = $ts . strtoupper($method) . $path . $body;
        $sig = hash_hmac('sha256', $payload, $this->secretKey);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-App-Token' => $this->appToken,
            'X-App-Access-Sig' => $sig,
            'X-App-Access-Ts' => $ts,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)->retry(2, 500);
    }

    private function throwFromResponse(\Illuminate\Http\Client\Response $response, ?string $applicantId): void
    {
        $status = $response->status();
        $body = $response->body();
        Log::error('Sumsub API error', [
            'status' => $status,
            'applicant_id' => $applicantId,
            'body_preview' => strlen($body) > 500 ? substr($body, 0, 500) . '...' : $body,
        ]);
        throw SumsubApiException::fromResponse($status, $body, $applicantId);
    }

    private function mapToKycStatus(?string $answer, array $data): KycStatus
    {
        if ($answer === null || $answer === '') {
            $documentsRequired = $data['requiredIdDocsStatus'] ?? [];
            $allSubmitted = !empty($data['idDocSetType']) || empty($documentsRequired);
            return $allSubmitted ? KycStatus::UnderReview : KycStatus::NotStarted;
        }
        $a = strtoupper((string) $answer);
        return match ($a) {
            'GREEN' => KycStatus::Approved,
            'RED' => KycStatus::Rejected,
            'YELLOW' => KycStatus::UnderReview,
            'PENDING' => KycStatus::PendingDocuments,
            default => KycStatus::NotStarted,
        };
    }

    /**
     * Map webhook reviewAnswer to KycStatus (for use by webhook job).
     */
    public function mapReviewAnswerToKycStatus(?string $reviewAnswer): KycStatus
    {
        if ($reviewAnswer === null || $reviewAnswer === '') {
            return KycStatus::PendingDocuments;
        }
        $a = strtoupper($reviewAnswer);
        return match ($a) {
            'GREEN' => KycStatus::Approved,
            'RED' => KycStatus::Rejected,
            'YELLOW' => KycStatus::UnderReview,
            default => KycStatus::UnderReview,
        };
    }
}
