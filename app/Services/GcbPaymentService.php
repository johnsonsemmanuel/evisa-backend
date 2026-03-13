<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GcbPaymentService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.gcb.base_url') ?? '';
        $this->apiKey = config('services.gcb.api_key') ?? '';
    }

    /**
     * Initialize GCB checkout.
     * 
     * @param Application $application
     * @param float $amount
     * @param string $currency
     * @param string|null $callbackUrl
     * @param string|null $paymentOption (momo, card, gcb, mtn, vodafone, airteltigo, or null for all)
     * @return array
     */
    public function initializeCheckout(
        Application $application,
        float $amount,
        string $currency = 'GHS',
        ?string $callbackUrl = null,
        ?string $paymentOption = null
    ): array {
        if (!$this->apiKey) {
            Log::warning('GCB API key not configured');
            return [
                'success' => false,
                'message' => 'GCB payment gateway not configured',
            ];
        }

        $merchantRef = $this->generateMerchantRef($application);
        $callbackUrl = $callbackUrl ?? config('app.frontend_url') . '/payment/callback';

        $payload = [
            'merchantRef' => $merchantRef,
            'amount' => $amount,
            'currency' => $currency,
            'description' => "Ghana eVisa Application - {$application->reference_number}",
            'paymentOption' => $paymentOption,
            'callbackUrl' => $callbackUrl,
        ];

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/checkout", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'checkout_url' => $data['checkOutUrl'] ?? null,
                    'checkout_id' => $data['checkOutId'] ?? null,
                    'merchant_ref' => $merchantRef,
                ];
            }

            Log::error('GCB checkout initialization failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initialize GCB payment',
                'error' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('GCB API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'GCB payment service unavailable',
            ];
        }
    }

    /**
     * Check transaction status.
     * 
     * @param string $checkoutId
     * @return array
     */
    public function checkTransactionStatus(string $checkoutId): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'message' => 'GCB API key not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
            ])->get("{$this->baseUrl}/transactions/{$checkoutId}/status");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'merchant_ref' => $data['merchantRef'] ?? null,
                    'status_code' => $data['status'] ?? null,
                    'bank_ref' => $data['bankRef'] ?? null,
                    'time_completed' => $data['timeCompleted'] ?? null,
                    'payment_option' => $data['paymentOption'] ?? null,
                    'status_message' => $this->getStatusMessage($data['status'] ?? null),
                ];
            }

            Log::error('GCB status check failed', [
                'checkout_id' => $checkoutId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to check transaction status',
            ];
        } catch (\Exception $e) {
            Log::error('GCB status check error', [
                'checkout_id' => $checkoutId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Status check service unavailable',
            ];
        }
    }

    /**
     * Handle GCB callback.
     * 
     * @param array $payload
     * @return array
     */
    public function handleCallback(array $payload): array
    {
        $merchantRef = $payload['merchantRef'] ?? null;
        $statusCode = $payload['statusCode'] ?? null;
        $bankRef = $payload['bankRef'] ?? null;
        $timeCompleted = $payload['timeCompleted'] ?? null;
        $paymentOption = $payload['paymentOption'] ?? null;

        if (!$merchantRef || !$statusCode) {
            return [
                'success' => false,
                'message' => 'Invalid callback payload',
            ];
        }

        $payment = Payment::where('transaction_reference', $merchantRef)->first();

        if (!$payment) {
            Log::warning('GCB callback: Payment not found', ['merchant_ref' => $merchantRef]);
            return [
                'success' => false,
                'message' => 'Payment record not found',
            ];
        }

        // Update payment with GCB response
        $payment->provider_response = $payload;
        $payment->provider_reference = $bankRef;

        // Handle status codes
        switch ($statusCode) {
            case '00': // Payment Successful
                $payment->status = 'completed';
                $payment->paid_at = $timeCompleted ? \Carbon\Carbon::parse($timeCompleted) : now();
                $payment->save();

                // Trigger success actions
                $this->onPaymentSuccess($payment);

                return [
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment successful',
                ];

            case '01': // Payment Pending
                $payment->status = 'pending';
                $payment->save();

                return [
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Payment pending',
                ];

            case '02': // Payment Failed
                $payment->status = 'failed';
                $payment->save();

                return [
                    'success' => true,
                    'status' => 'failed',
                    'message' => 'Payment failed',
                ];

            case '03': // Checkout URL Expired
                $payment->status = 'expired';
                $payment->save();

                return [
                    'success' => true,
                    'status' => 'expired',
                    'message' => 'Checkout session expired',
                ];

            default:
                Log::warning('GCB callback: Unknown status code', [
                    'merchant_ref' => $merchantRef,
                    'status_code' => $statusCode,
                ]);

                return [
                    'success' => false,
                    'message' => 'Unknown status code',
                ];
        }
    }

    /**
     * Get human-readable status message.
     * 
     * @param string|null $statusCode
     * @return string
     */
    protected function getStatusMessage(?string $statusCode): string
    {
        return match ($statusCode) {
            '00' => 'Payment Successful',
            '01' => 'Payment Pending',
            '02' => 'Payment Failed',
            '03' => 'Checkout URL Expired',
            '04' => 'Checkout ID not found or caller error',
            '05' => 'Internal Error',
            default => 'Unknown Status',
        };
    }

    /**
     * Generate merchant reference.
     * 
     * @param Application $application
     * @return string
     */
    protected function generateMerchantRef(Application $application): string
    {
        // Format: GCB-{REF_NUMBER}-{TIMESTAMP}
        // Max length: 20 characters (alphanumeric)
        $timestamp = now()->format('ymdHi'); // 10 chars (without seconds)
        $refShort = substr($application->reference_number, -7); // Last 7 chars
        
        return "GCB{$refShort}{$timestamp}"; // Total: 3+7+10 = 20 chars
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

        Log::info("GCB payment completed for application {$application->reference_number}", [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);
    }
}
