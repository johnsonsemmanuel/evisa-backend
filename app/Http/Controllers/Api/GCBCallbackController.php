<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Events\PaymentConfirmed;
use App\Jobs\SendPaymentConfirmationEmail;
use App\Models\Payment;
use App\Services\GCBPaymentService;
use App\Services\WebhookProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GCB payment callback. POST /api/gcb/callback
 * 1. Verify HMAC (X-GCB-Signature)
 * 2. Idempotency (WebhookProcessingService)
 * 3. Find payment by transaction_reference / merchantRef
 * 4. Verify amount
 * 5. Update payment status
 * 6. Update application to payment_confirmed
 * 7. Dispatch SendPaymentConfirmationEmail (and receipt job if any)
 * 8. Audit (via WebhookEvent in processWithIdempotency)
 * 9. Return 200
 */
class GCBCallbackController extends Controller
{
    public function __construct(
        protected GCBPaymentService $gcbService,
        protected WebhookProcessingService $webhookProcessor
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-GCB-Signature') ?? $request->header('X-Gcb-Signature');

        if (!$this->gcbService->verifyWebhookSignature($rawBody, (string) $signature)) {
            Log::warning('GCB callback signature verification failed', ['ip' => $request->ip()]);
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $transactionRef = $payload['transaction_reference'] ?? $payload['merchantRef'] ?? null;
        $statusCode = $payload['statusCode'] ?? $payload['status'] ?? null;

        if (!$transactionRef) {
            Log::warning('GCB callback missing transaction reference', ['ip' => $request->ip()]);
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        $eventId = $transactionRef;
        $eventType = 'payment.status.' . ($statusCode ?? 'unknown');

        $result = $this->webhookProcessor->processWithIdempotency(
            gateway: 'gcb',
            eventId: $eventId,
            eventType: $eventType,
            payload: $payload,
            processor: fn (array $data) => $this->processCallback($data)
        );

        return response()->json([
            'status' => 'ok',
            'message' => $result['duplicate'] ?? false ? 'Already processed' : 'Processed',
        ], 200);
    }

    private function processCallback(array $payload): array
    {
        $transactionRef = $payload['transaction_reference'] ?? $payload['merchantRef'] ?? null;
        $statusCode = $payload['statusCode'] ?? $payload['status'] ?? '00';
        $bankRef = $payload['bankRef'] ?? $payload['gateway_reference'] ?? null;
        $timeCompleted = $payload['timeCompleted'] ?? $payload['paid_at'] ?? null;
        $gatewayAmountPesewas = $this->resolveAmountPesewas($payload);

        $payment = Payment::where('transaction_reference', $transactionRef)
            ->orWhere('id', $transactionRef)
            ->first();

        if (!$payment) {
            Log::warning('GCB callback: payment not found', ['transaction_ref' => $transactionRef]);
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->provider_response = array_merge($payment->provider_response ?? [], $payload);
        $payment->gateway_reference = $bankRef ?? $payment->gateway_reference;
        $payment->save();

        if ($statusCode === '00' || strtoupper((string) $statusCode) === 'SUCCESS') {
            if ($gatewayAmountPesewas !== null) {
                try {
                    $this->webhookProcessor->verifyPaymentAmount($payment, $gatewayAmountPesewas, 'gcb');
                } catch (\App\Exceptions\PaymentAmountMismatchException $e) {
                    $payment->markAsSuspicious('Amount mismatch from GCB');
                    return ['success' => false, 'status' => 'suspicious', 'message' => 'Amount mismatch'];
                }
            }

            $payment->markAsPaid();
            if ($timeCompleted) {
                $payment->update(['paid_at' => \Carbon\Carbon::parse($timeCompleted)]);
            }

            $application = $payment->application;
            if ($application && $application->status !== ApplicationStatus::PaymentConfirmed) {
                $application->transitionTo(ApplicationStatus::PaymentConfirmed);
            }

            SendPaymentConfirmationEmail::dispatch($payment)->onQueue('critical');
            broadcast(new PaymentConfirmed($payment));
            if (class_exists(\App\Jobs\GenerateReceiptPDF::class)) {
                \App\Jobs\GenerateReceiptPDF::dispatch($payment)->onQueue('default');
            }

            Log::info('GCB payment confirmed', ['payment_id' => $payment->id, 'application_id' => $application?->id]);
            return ['success' => true, 'status' => 'paid'];
        }

        if (in_array($statusCode, ['01', 'PENDING'], true)) {
            $payment->transitionTo(PaymentStatus::Processing);
            return ['success' => true, 'status' => 'processing'];
        }

        if (in_array($statusCode, ['02', '03', 'FAILED', 'DECLINED', 'CANCELLED'], true)) {
            $payment->markAsFailed($payload['message'] ?? $payload['reason'] ?? null);
            return ['success' => true, 'status' => 'failed'];
        }

        Log::info('GCB callback processed', ['payment_id' => $payment->id, 'status_code' => $statusCode]);
        return ['success' => true, 'status' => (string) $statusCode];
    }

    private function resolveAmountPesewas(array $payload): ?int
    {
        if (isset($payload['amount_pesewas']) && is_numeric($payload['amount_pesewas'])) {
            return (int) $payload['amount_pesewas'];
        }
        if (isset($payload['amount']) && is_numeric($payload['amount'])) {
            $amount = (float) $payload['amount'];
            if ($amount >= 1) {
                return (int) round($amount * 100);
            }
            return (int) round($amount * 100);
        }
        return null;
    }
}
