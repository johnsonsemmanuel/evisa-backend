<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SumsubService;
use App\Models\Application;
use App\Models\EtaApplication;
use App\Models\SumsubVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SumsubController extends Controller
{
    protected SumsubService $sumsubService;

    public function __construct(SumsubService $sumsubService)
    {
        $this->sumsubService = $sumsubService;
    }

    /**
     * Generate access token for Sumsub SDK.
     */
    public function generateToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'application_id' => 'nullable|integer',
                'eta_application_id' => 'nullable|integer',
                'application_type' => 'required|in:visa,eta',
                'level_name' => 'string|in:basic-kyc-level,evisa-level,eta-level',
            ]);

            if (!$validated['application_id'] && !$validated['eta_application_id']) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Either application_id or eta_application_id is required',
                    ]
                ], 422);
            }

            // Get the application
            if ($validated['application_type'] === 'visa') {
                $application = Application::find($validated['application_id']);
                if (!$application) {
                    return response()->json([
                        'error' => [
                            'code' => 'NOT_FOUND',
                            'message' => 'Visa application not found',
                        ]
                    ], 404);
                }
            } else {
                $application = EtaApplication::find($validated['eta_application_id']);
                if (!$application) {
                    return response()->json([
                        'error' => [
                            'code' => 'NOT_FOUND',
                            'message' => 'ETA application not found',
                        ]
                    ], 404);
                }
            }

            // Check if verification is required
            if (!$this->sumsubService->isVerificationRequired($application, $validated['application_type'])) {
                return response()->json([
                    'error' => [
                        'code' => 'VERIFICATION_NOT_REQUIRED',
                        'message' => 'Sumsub verification is not required for this application',
                    ]
                ], 400);
            }

            // Create or get existing verification record
            $verification = SumsubVerification::where(
                $validated['application_type'] === 'visa' ? 'application_id' : 'eta_application_id',
                $application->id
            )->first();

            if (!$verification) {
                $verification = $this->sumsubService->createVerificationForApplication(
                    $application,
                    $validated['application_type']
                );
            }

            // Generate access token
            $levelName = $validated['level_name'] ?? ($validated['application_type'] === 'eta' ? 'eta-level' : 'evisa-level');
            $result = $this->sumsubService->generateAccessToken($verification->external_user_id, $levelName);

            if (!$result['success']) {
                return response()->json([
                    'error' => [
                        'code' => 'TOKEN_GENERATION_FAILED',
                        'message' => $result['error'],
                        'details' => $result['details'] ?? null,
                    ]
                ], 500);
            }

            return response()->json([
                'success' => true,
                'token' => $result['token'],
                'external_user_id' => $verification->external_user_id,
                'level_name' => $levelName,
                'verification_id' => $verification->id,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                ]
            ], 422);
        } catch (\Exception $e) {
            Log::error('Sumsub token generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_ERROR',
                    'message' => 'Failed to generate verification token',
                ]
            ], 500);
        }
    }

    /**
     * Handle webhook from Sumsub.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature if configured
            if (config('sumsub.webhook_secret')) {
                $signature = $request->header('X-Payload-Digest');
                $payload = $request->getContent();
                $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, config('sumsub.webhook_secret'));

                if (!hash_equals($expectedSignature, $signature)) {
                    Log::warning('Sumsub webhook signature mismatch', [
                        'expected' => $expectedSignature,
                        'received' => $signature,
                    ]);

                    return response()->json([
                        'error' => 'Invalid signature'
                    ], 401);
                }
            }

            $payload = $request->all();

            Log::info('Sumsub webhook received', [
                'type' => $payload['type'] ?? 'unknown',
                'applicant_id' => $payload['applicantId'] ?? 'unknown',
            ]);

            $success = $this->sumsubService->handleWebhook($payload);

            if ($success) {
                return response()->json(['status' => 'success']);
            }

            return response()->json([
                'error' => 'Failed to process webhook'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Sumsub webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Get verification status.
     */
    public function getStatus(Request $request, string $applicantId): JsonResponse
    {
        try {
            $verification = SumsubVerification::where('applicant_id', $applicantId)->first();

            if (!$verification) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Verification not found',
                    ]
                ], 404);
            }

            // Get latest status from Sumsub
            $result = $this->sumsubService->getApplicantStatus($applicantId);

            if ($result['success']) {
                return response()->json([
                    'verification_id' => $verification->id,
                    'applicant_id' => $applicantId,
                    'verification_status' => $verification->verification_status,
                    'review_result' => $verification->review_result,
                    'submitted_at' => $verification->submitted_at?->toISOString(),
                    'reviewed_at' => $verification->reviewed_at?->toISOString(),
                    'sumsub_data' => $result['data'],
                ]);
            }

            return response()->json([
                'verification_id' => $verification->id,
                'applicant_id' => $applicantId,
                'verification_status' => $verification->verification_status,
                'review_result' => $verification->review_result,
                'submitted_at' => $verification->submitted_at?->toISOString(),
                'reviewed_at' => $verification->reviewed_at?->toISOString(),
                'error' => 'Failed to get latest status from Sumsub',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Sumsub verification status', [
                'applicant_id' => $applicantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_ERROR',
                    'message' => 'Failed to get verification status',
                ]
            ], 500);
        }
    }

    /**
     * Update applicant ID when verification starts.
     */
    public function updateApplicantId(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'external_user_id' => 'required|string',
                'applicant_id' => 'required|string',
            ]);

            $success = $this->sumsubService->updateApplicantId(
                $validated['external_user_id'],
                $validated['applicant_id']
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Applicant ID updated successfully',
                ]);
            }

            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Verification record not found',
                ]
            ], 404);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                ]
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update Sumsub applicant ID', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_ERROR',
                    'message' => 'Failed to update applicant ID',
                ]
            ], 500);
        }
    }

    /**
     * Get verification statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_verifications' => SumsubVerification::count(),
                'pending_verifications' => SumsubVerification::pending()->count(),
                'completed_verifications' => SumsubVerification::completed()->count(),
                'approved_verifications' => SumsubVerification::approved()->count(),
                'rejected_verifications' => SumsubVerification::rejected()->count(),
                'visa_verifications' => SumsubVerification::forApplicationType('visa')->count(),
                'eta_verifications' => SumsubVerification::forApplicationType('eta')->count(),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('Failed to get Sumsub statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_ERROR',
                    'message' => 'Failed to get statistics',
                ]
            ], 500);
        }
    }
}