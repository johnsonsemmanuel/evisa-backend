<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key') ?? '';
        $this->publicKey = config('services.paystack.public_key') ?? '';
        $this->baseUrl = 'https://api.paystack.co';
    }

    /**
     * Initialize Paystack transaction.
     * 
     * @param Application $application
     * @param float $amount
     * @param string $currency
     * @param array $channels (e.g., ['card', 'bank', 'mobile_money'])
     * @param string|null $callbackUrl
     * @return array
     */
    public function initializeTransaction(
        Application $application,
        float $amount,
        string $currency = 'GHS',
        array $channels = ['card', 'bank', 'mobile_money'],
        ?string $callbackUrl = null
    ): array {
        if (!$this->secretKey) {
            Log::warning('Paystack secret key not configured');
            return [
                'success' => false,
                'message' => 'Paystack not configured',
            ];
        }

        $reference = $this->generateReference($application);
        $callbackUrl = $callbackUrl ?? config('app.frontend_url') . '/payment/callback';

        $payload = [
            'email' => $application->email,
            'amount' => (int) ($amount * 100), // Convert to kobo/pesewas
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'channels' => $channels,
            'metadata' => [
                'application_id' => $application->id,
                'reference_number' => $application->reference_number,
                'visa_type' => $application->visaType?->name,
                'applicant_name' => "{$application->first_name} {$application->last_name}",
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transaction/initialize", $payload);

            if ($response->successful() && $response->json('status')) {
                $data = $response->json('data');

                return [
                    'success' => true,
                    'authorization_url' => $data['authorization_url'] ?? null,
                    'access_code' => $data['access_code'] ?? null,
                    'reference' => $data['reference'] ?? $reference,
                ];
            }

            Log::error('Paystack initialization failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Payment initialization failed',
            ];
        } catch (\Exception $e) {
            Log::error('Paystack API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Paystack service unavailable',
            ];
        }
    }

    /**
     * Verify transaction.
     * 
     * @param string $reference
     * @return array
     */
    public function verifyTransaction(string $reference): array
    {
        if (!$this->secretKey) {
            return [
                'success' => false,
                'message' => 'Paystack not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
            ])->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if ($response->successful() && $response->json('status')) {
                $data = $response->json('data');

                return [
                    'success' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'amount' => ($data['amount'] ?? 0) / 100, // Convert from kobo
                    'currency' => $data['currency'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'paid_at' => $data['paid_at'] ?? null,
                    'channel' => $data['channel'] ?? null,
                    'customer' => $data['customer'] ?? null,
                    'authorization' => $data['authorization'] ?? null,
                    'raw_data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Verification failed',
            ];
        } catch (\Exception $e) {
            Log::error('Paystack verification error', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Verification service unavailable',
            ];
        }
    }

    /**
     * Handle Paystack webhook.
     * 
     * @param array $payload
     * @param string|null $signature
     * @return array
     */
    public function handleWebhook(array $payload, ?string $signature = null): array
    {
        // Verify webhook signature
        if ($signature && !$this->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook signature verification failed');
            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        }

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        if ($event !== 'charge.success') {
            // We only handle successful charges
            return [
                'success' => true,
                'message' => 'Event ignored',
            ];
        }

        $reference = $data['reference'] ?? null;
        $status = $data['status'] ?? null;

        if (!$reference) {
            return [
                'success' => false,
                'message' => 'Missing reference',
            ];
        }

        $payment = Payment::where('transaction_reference', $reference)->first();

        if (!$payment) {
            Log::warning('Paystack webhook: Payment not found', ['reference' => $reference]);
            return [
                'success' => false,
                'message' => 'Payment not found',
            ];
        }

        // Update payment
        if ($status === 'success') {
            $payment->status = 'completed';
            $payment->paid_at = isset($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at']) : now();
            $payment->provider_reference = $data['id'] ?? null;
            $payment->provider_response = $data;
            $payment->save();

            // Trigger success actions
            $this->onPaymentSuccess($payment);

            return [
                'success' => true,
                'status' => 'completed',
                'message' => 'Payment processed',
            ];
        }

        return [
            'success' => true,
            'message' => 'Webhook processed',
        ];
    }

    /**
     * Verify webhook signature.
     * 
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    protected function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $computedSignature = hash_hmac('sha512', json_encode($payload), $this->secretKey);
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Generate transaction reference.
     * 
     * @param Application $application
     * @return string
     */
    protected function generateReference(Application $application): string
    {
        return 'PS-' . $application->reference_number . '-' . now()->format('YmdHis');
    }

    /**
     * Handle successful payment.
     * 
     * @param Payment $payment
     * @return void
     */
    protected function onPaymentSuccess(Payment $payment): void
    {
        $application = $payment->application;
        if (!$application) {
            return;
        }

        // Update application total fee
        $application->update(['total_fee' => $payment->amount]);

        // Confirm payment and transition status
        if (in_array($application->status, ['submitted_awaiting_payment', 'pending_payment'])) {
            app(ApplicationService::class)->confirmPayment($application);
        }

        // Broadcast real-time payment completion
        app(\App\Services\RealTimeDashboardService::class)->broadcastPaymentCompleted($payment);

        Log::info("Paystack payment completed for application {$application->reference_number}", [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);
    }
}
