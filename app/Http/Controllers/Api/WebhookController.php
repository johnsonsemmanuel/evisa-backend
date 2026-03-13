<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GcbPaymentService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected GcbPaymentService $gcbService,
        protected PaystackService $paystackService
    ) {}

    /**
     * Handle GCB payment callback.
     * Called by GCB Payment Gateway when payment status changes.
     */
    public function handleGcbCallback(Request $request): JsonResponse
    {
        // Verify IP whitelist in production
        if (!app()->environment('local', 'testing')) {
            $allowedIps = config('services.gcb.allowed_ips', []);
            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
                Log::warning('GCB callback from unauthorized IP', ['ip' => $request->ip()]);
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $payload = $request->all();
        
        Log::info('GCB callback received', ['payload' => $payload]);

        $result = $this->gcbService->handleCallback($payload);

        if ($result['success']) {
            return response()->json(['message' => 'Callback processed'], 200);
        }

        // GCB expects 200 for successful receipt, even if processing fails
        // They will retry if non-200 status is returned
        return response()->json(['message' => 'Callback received'], 200);
    }

    /**
     * Handle Paystack webhook.
     * Called by Paystack when payment events occur.
     */
    public function handlePaystackWebhook(Request $request): JsonResponse
    {
        // Verify webhook signature
        $signature = $request->header('X-Paystack-Signature');
        
        if (!app()->environment('local', 'testing')) {
            if (!$signature) {
                Log::warning('Paystack webhook missing signature');
                return response()->json(['message' => 'Missing signature'], 401);
            }
        }

        $payload = $request->all();
        
        Log::info('Paystack webhook received', [
            'event' => $payload['event'] ?? 'unknown',
            'reference' => $payload['data']['reference'] ?? null,
        ]);

        $result = $this->paystackService->handleWebhook($payload, $signature);

        if ($result['success']) {
            return response()->json(['message' => 'Webhook processed'], 200);
        }

        return response()->json(['message' => $result['message'] ?? 'Processing failed'], 400);
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
