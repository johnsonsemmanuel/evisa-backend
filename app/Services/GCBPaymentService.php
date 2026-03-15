<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\GCBApiException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GCBPaymentService
{
    private PendingRequest $httpClient;

    public function __construct()
    {
        throw_if(
            empty(config('gcb.merchant_id')),
            new \RuntimeException('GCB merchant ID is not configured. Set GCB_MERCHANT_ID in .env')
        );

        $baseUrl = rtrim((string) config('gcb.base_url'), '/');
        if ($baseUrl && class_exists(\App\Services\ExternalUrlValidator::class)) {
            app(\App\Services\ExternalUrlValidator::class)->validateExternalUrl($baseUrl);
        }

        $this->httpClient = Http::baseUrl($baseUrl)
            ->timeout(config('gcb.timeout', 30))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Merchant-ID' => config('gcb.merchant_id'),
            ]);
    }

    /**
     * Sign request payload for GCB (HMAC-SHA256).
     * Verify exact algorithm with GCB ICD.
     */
    private function signRequest(array $payload): string
    {
        $data = ($payload['merchant_id'] ?? '')
            . ($payload['transaction_reference'] ?? '')
            . ($payload['amount'] ?? '')
            . ($payload['currency'] ?? '');
        return hash_hmac('sha256', $data, config('gcb.secret_key'));
    }

    /**
     * Create payment intent and return checkout URL.
     */
    public function initiatePayment(Payment $payment): array
    {
        $application = $payment->application;
        if (!$application) {
            throw new \InvalidArgumentException('Payment has no application');
        }

        $transactionReference = $payment->transaction_reference ?: (string) $payment->id;
        if (!$payment->transaction_reference) {
            $payment->update(['transaction_reference' => $transactionReference]);
        }

        $payload = [
            'merchant_id' => config('gcb.merchant_id'),
            'transaction_reference' => $transactionReference,
            'amount' => (int) $payment->amount,
            'currency' => 'GHS',
            'description' => "Ghana eVisa Fee - Application #{$application->id}",
            'callback_url' => route('gcb.callback'),
            'return_url' => config('app.url') . '/payment/complete',
            'customer_email' => $application->email ?? '',
            'customer_name' => trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? '')),
        ];
        $payload['signature'] = $this->signRequest($payload);

        $response = $this->httpClient->post('/api/v1/checkout', $payload);

        if (!$response->successful()) {
            $this->handleGCBError($response, 'initiatePayment');
        }

        $data = $response->json();
        return [
            'checkout_url' => $data['checkout_url'] ?? $data['checkOutUrl'] ?? $data['redirect_url'] ?? null,
            'transaction_reference' => $transactionReference,
            'gateway_reference' => $data['transaction_id'] ?? $data['reference'] ?? null,
            'data' => $data,
        ];
    }

    /**
     * Query GCB for transaction status. Used by reconciliation and manual investigation.
     */
    public function getTransactionStatus(string $transactionReference): array
    {
        $response = $this->httpClient->get('/api/v1/transactions/' . $transactionReference);

        if ($response->status() === 404) {
            return [
                'status' => PaymentStatus::Failed,
                'amount' => null,
                'paid_at' => null,
                'gateway_reference' => null,
                'found' => false,
            ];
        }

        if (!$response->successful()) {
            $this->handleGCBError($response, 'getTransactionStatus');
        }

        $data = $response->json();
        $gcbStatus = $data['status'] ?? $data['statusCode'] ?? $data['status_code'] ?? null;
        $status = $this->mapGCBStatusToPaymentStatus($gcbStatus);
        $amount = isset($data['amount']) ? (int) round((float) $data['amount'] * 100) : ($data['amount_pesewas'] ?? null);
        $paidAt = isset($data['paid_at']) ? $data['paid_at'] : ($data['timeCompleted'] ?? $data['completed_at'] ?? null);

        return [
            'status' => $status,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'gateway_reference' => $data['gateway_reference'] ?? $data['bankRef'] ?? $data['reference'] ?? null,
            'data' => $data,
        ];
    }

    private function mapGCBStatusToPaymentStatus(?string $gcbStatus): PaymentStatus
    {
        $s = strtoupper((string) $gcbStatus);
        return match ($s) {
            '00', 'SUCCESS', 'PAID', 'COMPLETED' => PaymentStatus::Paid,
            '01', 'PENDING', 'PROCESSING' => PaymentStatus::Processing,
            '02', 'FAILED', 'DECLINED' => PaymentStatus::Failed,
            '03', 'CANCELLED', 'CANCELED' => PaymentStatus::Cancelled,
            'REFUNDED' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };
    }

    /**
     * Process refund. Required for refund flow (Payment Audit Session 10).
     */
    public function processRefund(Payment $payment, int $amountInPesewas, string $reason): array
    {
        $payload = [
            'transaction_reference' => $payment->transaction_reference ?: (string) $payment->id,
            'gateway_reference' => $payment->gateway_reference,
            'amount' => $amountInPesewas,
            'reason' => $reason,
            'refund_reference' => 'REF-' . $payment->id . '-' . time(),
        ];
        $payload['signature'] = hash_hmac('sha256', $payload['transaction_reference'] . $payload['amount'] . $payload['refund_reference'], config('gcb.secret_key'));

        $response = $this->httpClient->post('/api/v1/refunds', $payload);

        if (!$response->successful()) {
            Log::warning('GCB refund API error', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'error_code' => $response->json('error_code'),
            ]);
            $this->handleGCBError($response, 'processRefund');
        }

        $data = $response->json();
        return [
            'success' => true,
            'refund_reference' => $data['refund_reference'] ?? $data['refundRef'] ?? $payload['refund_reference'],
            'amount' => $amountInPesewas,
            'data' => $data,
        ];
    }

    /**
     * Health check for GCB gateway. Used by /api/health/full and Paystack fallback logic.
     */
    public function ping(): bool
    {
        try {
            $response = $this->httpClient->timeout(5)->get('/api/v1/health');
            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('GCB ping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Log and throw on GCB API error. Never log amount, customer details, or card data.
     */
    public function handleGCBError(Response $response, string $context): never
    {
        Log::error('GCB API error', [
            'context' => $context,
            'status' => $response->status(),
            'error_code' => $response->json('error_code'),
        ]);

        throw new GCBApiException(
            message: "GCB API error in {$context}: " . ($response->json('message') ?? $response->json('error') ?? 'Unknown error'),
            statusCode: $response->status(),
            errorCode: $response->json('error_code'),
        );
    }

    /**
     * Verify GCB webhook signature (X-GCB-Signature = HMAC-SHA256(rawBody, webhook_secret)).
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('gcb.webhook_secret');
        if (empty($secret)) {
            Log::error('GCB webhook secret not configured');
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}
