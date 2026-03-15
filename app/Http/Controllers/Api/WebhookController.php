<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WebhookVerificationException;
use App\Http\Controllers\Controller;
use App\Services\GcbPaymentService;
use App\Services\PaystackService;
use App\Services\WebhookProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected GcbPaymentService $gcbService,
        protected PaystackService $paystackService,
        protected WebhookProcessingService $webhookProcessor
    ) {}

    /**
     * Handle GCB payment callback.
     * Called by GCB Payment Gateway when payment status changes.
     * SECURITY: Validates IP whitelist AND webhook signature.
     * IDEMPOTENCY: Prevents duplicate processing using database-level unique constraint.
     */
    public function handleGcbCallback(Request $request): JsonResponse
    {
        try {
            // STEP 1: Verify IP whitelist (first line of defense)
            $allowedIps = config('services.gcb.allowed_ips', []);
            
            // For local/testing, use localhost IPs if whitelist is empty
            if (app()->environment('local', 'testing') && empty($allowedIps)) {
                $allowedIps = ['127.0.0.1', '::1', 'localhost'];
            }
            
            if (empty($allowedIps)) {
                Log::error('GCB allowed IPs not configured');
                return response()->json(['status' => 'error', 'message' => 'Configuration error'], 500);
            }

            $requestIp = $request->ip();
            
            if (!in_array($requestIp, $allowedIps)) {
                Log::warning('GCB callback from unauthorized IP', [
                    'ip' => $requestIp,
                    'allowed_ips' => $allowedIps,
                    'user_agent' => $request->userAgent(),
                ]);
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            // STEP 2: Verify webhook signature (CRITICAL SECURITY)
            try {
                $this->gcbService->verifyGcbSignature($request);
            } catch (WebhookVerificationException $e) {
                // Log failed verification attempt with source IP (NEVER log request body)
                Log::error('GCB webhook signature verification failed', [
                    'ip' => $requestIp,
                    'user_agent' => $request->userAgent(),
                    'error' => $e->getMessage(),
                ]);
                
                // Return 401 immediately - do not process
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            // STEP 3: Extract payload and event identifiers
            $payload = $request->all();
            $merchantRef = $payload['merchantRef'] ?? null;
            $statusCode = $payload['statusCode'] ?? null;

            if (!$merchantRef) {
                Log::warning('GCB callback missing merchantRef', [
                    'ip' => $requestIp,
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
            }

            // Use merchantRef as event_id for idempotency
            $eventId = $merchantRef;
            $eventType = "payment.status.{$statusCode}";

            Log::info('GCB callback received and verified', [
                'ip' => $requestIp,
                'event_id' => $eventId,
                'status_code' => $statusCode,
            ]);

            // STEP 4: Process with idempotency protection
            $result = $this->webhookProcessor->processWithIdempotency(
                gateway: 'gcb',
                eventId: $eventId,
                eventType: $eventType,
                payload: $payload,
                processor: fn($data) => $this->gcbService->handleCallback($data)
            );

            // STEP 5: Return 200 for both success and duplicates
            // (GCB expects 200 to stop retrying)
            if ($result['success']) {
                return response()->json([
                    'status' => 'ok',
                    'message' => $result['duplicate'] ? 'Already processed' : 'Processed successfully'
                ], 200);
            }

            // Even on processing failure, return 200 to prevent infinite retries
            // The error is already logged
            return response()->json(['status' => 'ok', 'message' => 'Received'], 200);

        } catch (\Exception $e) {
            // Catch-all for any unexpected errors
            Log::error('GCB webhook handler exception', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 500 to signal gateway to retry later
            return response()->json(['status' => 'error', 'message' => 'Internal error'], 500);
        }
    }

    /**
     * Handle Paystack webhook.
     * Called by Paystack when payment events occur.
     * SECURITY: Validates webhook signature using HMAC-SHA512.
     * IDEMPOTENCY: Prevents duplicate processing using database-level unique constraint.
     */
    public function handlePaystackWebhook(Request $request): JsonResponse
    {
        try {
            // STEP 1: Verify webhook signature (CRITICAL SECURITY)
            try {
                $this->paystackService->verifyPaystackSignature($request);
            } catch (WebhookVerificationException $e) {
                // Log failed verification attempt with source IP (NEVER log request body)
                Log::error('Paystack webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'error' => $e->getMessage(),
                ]);
                
                // Return 401 immediately - do not process
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            // STEP 2: Decode payload after verification
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                Log::warning('Paystack webhook invalid JSON', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid JSON'], 400);
            }

            // STEP 3: Extract event identifiers
            $event = $data['event'] ?? 'unknown';
            $reference = $data['data']['reference'] ?? null;

            if (!$reference) {
                Log::warning('Paystack webhook missing reference', [
                    'ip' => $request->ip(),
                    'event' => $event,
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
            }

            // Use reference as event_id for idempotency
            $eventId = $reference;
            $eventType = $event;

            Log::info('Paystack webhook received and verified', [
                'ip' => $request->ip(),
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            // STEP 4: Process with idempotency protection
            $result = $this->webhookProcessor->processWithIdempotency(
                gateway: 'paystack',
                eventId: $eventId,
                eventType: $eventType,
                payload: $data,
                processor: fn($payload) => $this->paystackService->handleWebhook($payload)
            );

            // STEP 5: Return appropriate response
            if ($result['success']) {
                return response()->json([
                    'status' => 'ok',
                    'message' => $result['duplicate'] ? 'Already processed' : 'Processed successfully'
                ], 200);
            }

            // Return 400 for processing failures (Paystack will retry on 5xx)
            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Processing failed'
            ], 400);

        } catch (\Exception $e) {
            // Catch-all for any unexpected errors
            Log::error('Paystack webhook handler exception', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 500 to signal Paystack to retry later
            return response()->json(['status' => 'error', 'message' => 'Internal error'], 500);
        }
    }

    /**
     * Legacy payment webhook handler (for backward compatibility).
     */
    public function handlePayment(Request $request): JsonResponse
    {
        $provider = $request->header('X-Provider', 'paystack');
        
        return match ($provider) {
            'gcb' => $this->handleGcbCallback($request),
            'paystack' => $this->handlePaystackWebhook($request),
            default => response()->json(['message' => 'Unknown provider'], 400),
        };
    }
}
