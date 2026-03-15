<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Paystack API client for eVisa payments.
 * Parallel to GCBPaymentService; used by PaymentGatewayService for fallback.
 */
class PaystackPaymentService
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('paystack.secret_key');
        $this->baseUrl = rtrim((string) config('paystack.base_url', 'https://api.paystack.co'), '/');

        if ($this->baseUrl && class_exists(\App\Services\ExternalUrlValidator::class)) {
            app(\App\Services\ExternalUrlValidator::class)->validateExternalUrl($this->baseUrl);
        }
    }

    /**
     * Initialize Paystack transaction. Returns URL to redirect applicant.
     */
    public function initiatePayment(Payment $payment): array
    {
        $application = $payment->application;
        if (!$application) {
            throw new \InvalidArgumentException('Payment has no application');
        }

        $reference = $payment->transaction_reference ?: 'PS-' . $payment->id . '-' . now()->format('YmdHis');
        if (!$payment->transaction_reference) {
            $payment->update(['transaction_reference' => $reference]);
        }

        $callbackUrl = config('app.frontend_url') . '/payment/callback';
        if (config('app.url')) {
            $callbackUrl = rtrim(config('app.url'), '/') . '/api/applicant/payment/callback';
        }

        $payload = [
            'email' => $application->email ?? $application->user?->email ?? 'noreply@evisa.example',
            'amount' => (int) $payment->amount,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'currency' => config('paystack.currency', 'GHS'),
            'metadata' => [
                'application_id' => (string) $application->id,
                'applicant_name' => trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? '')),
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(config('paystack.timeout', 30))
            ->post($this->baseUrl . '/transaction/initialize', $payload);

        if (!$response->successful()) {
            Log::error('Paystack initialize failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new RuntimeException(
                'Paystack initialization failed: ' . ($response->json('message') ?? $response->reason())
            );
        }

        $data = $response->json('data');
        if (empty($data) || empty($data['authorization_url'])) {
            throw new RuntimeException('Paystack returned no authorization URL');
        }

        return [
            'authorization_url' => $data['authorization_url'],
            'access_code' => $data['access_code'] ?? null,
            'reference' => $data['reference'] ?? $reference,
        ];
    }

    /**
     * Verify transaction status at Paystack.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])
            ->timeout(config('paystack.timeout', 30))
            ->get($this->baseUrl . '/transaction/verify/' . $reference);

        if (!$response->successful()) {
            return [
                'status' => 'failed',
                'amount' => null,
                'paid_at' => null,
                'gateway_response' => $response->json(),
            ];
        }

        $data = $response->json('data');
        $status = $data['status'] ?? 'unknown';
        $amount = isset($data['amount']) ? (int) $data['amount'] : null;
        $paidAt = $data['paid_at'] ?? null;

        return [
            'status' => $status,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'gateway_response' => $data,
        ];
    }

    /**
     * Process refund. Paystack expects amount in kobo/pesewas and transaction reference.
     */
    public function processRefund(Payment $payment, int $amount): array
    {
        $transactionRef = $payment->gateway_reference ?: $payment->transaction_reference;
        if (!$transactionRef) {
            throw new \InvalidArgumentException('Payment has no gateway or transaction reference for refund');
        }

        $payload = [
            'transaction' => $transactionRef,
            'amount' => $amount,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(config('paystack.timeout', 30))
            ->post($this->baseUrl . '/refund', $payload);

        if (!$response->successful()) {
            Log::warning('Paystack refund failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Refund failed',
                'gateway_response' => $response->json(),
            ];
        }

        $body = $response->json();
        $success = ($body['status'] ?? false) === true;
        $refundData = $body['data'] ?? [];

        return [
            'success' => $success,
            'reference' => $refundData['transaction_reference'] ?? $refundData['id'] ?? null,
            'amount' => $amount,
            'gateway_response' => $body,
        ];
    }

    /**
     * Lightweight health check (Paystack /bank list).
     */
    public function ping(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])
                ->timeout(5)
                ->get($this->baseUrl . '/bank');
            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('Paystack ping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
