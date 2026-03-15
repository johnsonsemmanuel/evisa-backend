<?php

namespace App\Services;

use App\Exceptions\WebhookVerificationException;
use App\Models\Application;
use App\Models\Payment;
use Illuminate\Http\Request;
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
        
        // SECURITY: Validate base URL against SSRF allowlist
        if ($this->baseUrl) {
            app(\App\Services\ExternalUrlValidator::class)->validateExternalUrl($this->baseUrl);
        }
    }
    /**
     * Verify GCB webhook signature.
     *
     * SECURITY CRITICAL: This method prevents unauthorized payment confirmations.
     * GCB sends X-GCB-Signature header with HMAC-SHA256 of the raw request body.
     *
     * @param Request $request
     * @return bool
     * @throws WebhookVerificationException
     */
    public function verifyGcbSignature(Request $request): bool
    {
        // Get webhook secret from config
        $webhookSecret = config('services.gcb.webhook_secret');

        if (!$webhookSecret) {
            Log::error('GCB webhook secret not configured');
            throw WebhookVerificationException::missingSecret('GCB');
        }

        // Get signature from header (check common header names)
        $signature = $request->header('X-GCB-Signature')
                  ?? $request->header('X-Signature')
                  ?? $request->header('X-Gcb-Signature');

        if (!$signature) {
            Log::warning('GCB webhook missing signature header', [
                'ip' => $request->ip(),
                'headers' => array_keys($request->headers->all()),
            ]);
            throw WebhookVerificationException::missingSignature('GCB');
        }

        // Get raw request body (CRITICAL: must use getContent(), not all())
        $rawBody = $request->getContent();

        if (empty($rawBody)) {
            Log::warning('GCB webhook has empty body', [
                'ip' => $request->ip(),
            ]);
            throw WebhookVerificationException::invalidSignature('GCB', 'Empty request body');
        }

        // Compute HMAC-SHA256 signature
        $computedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

        // Timing-safe comparison (prevents timing attacks)
        if (!hash_equals($computedSignature, $signature)) {
            Log::warning('GCB webhook signature verification failed', [
                'ip' => $request->ip(),
                'expected_prefix' => substr($computedSignature, 0, 10) . '...',
                'received_prefix' => substr($signature, 0, 10) . '...',
                'user_agent' => $request->userAgent(),
            ]);
            throw WebhookVerificationException::invalidSignature('GCB', 'Signature mismatch');
        }

        Log::info('GCB webhook signature verified successfully', [
            'ip' => $request->ip(),
        ]);

        return true;
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
                'message' => 'GCB payment gateway not configured. Please contact support or use an alternative payment method.',
                'error_code' => 'GCB_NOT_CONFIGURED',
            ];
        }

        if (!$this->baseUrl) {
            Log::warning('GCB base URL not configured');
            return [
                'success' => false,
                'message' => 'GCB payment gateway URL not configured. Please contact support.',
                'error_code' => 'GCB_URL_NOT_CONFIGURED',
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
            Log::info('GCB payment initialization attempt', [
                'merchant_ref' => $merchantRef,
                'amount' => $amount,
                'currency' => $currency,
                'base_url' => $this->baseUrl,
            ]);

            $response = Http::timeout(10)->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/checkout", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('GCB payment initialized successfully', [
                    'merchant_ref' => $merchantRef,
                    'checkout_id' => $data['checkOutId'] ?? null,
                ]);

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
                'merchant_ref' => $merchantRef,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initialize GCB payment. The payment gateway may be temporarily unavailable. Please try again or use an alternative payment method.',
                'error' => $response->json(),
                'error_code' => 'GCB_API_ERROR',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GCB API connection error', [
                'error' => $e->getMessage(),
                'merchant_ref' => $merchantRef,
            ]);

            return [
                'success' => false,
                'message' => 'Unable to connect to GCB payment gateway. Please check your internet connection or try an alternative payment method.',
                'error_code' => 'GCB_CONNECTION_ERROR',
            ];
        } catch (\Exception $e) {
            Log::error('GCB API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'merchant_ref' => $merchantRef,
            ]);

            return [
                'success' => false,
                'message' => 'GCB payment service is temporarily unavailable. Please try again later or use an alternative payment method.',
                'error_code' => 'GCB_SERVICE_ERROR',
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
     * SECURITY: Verifies payment amount matches expected amount.
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
        // GCB callback amount is in cedis; convert to pesewas for verification
        $gatewayAmountPesewas = isset($payload['amount']) ? (int) round((float) $payload['amount'] * 100) : null;

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

        // SECURITY: Verify amount if provided by gateway (payment->amount is in pesewas)
        if ($gatewayAmountPesewas !== null && $statusCode === '00') {
            try {
                app(WebhookProcessingService::class)->verifyPaymentAmount(
                    $payment,
                    $gatewayAmountPesewas,
                    'gcb'
                );
            } catch (\App\Exceptions\PaymentAmountMismatchException $e) {
                // Amount mismatch - payment marked as suspicious
                // Do NOT activate application
                return [
                    'success' => false,
                    'status' => 'suspicious',
                    'message' => 'Payment amount mismatch detected',
                ];
            }
        }

        // Update payment with GCB response
        $payment->provider_response = $payload;
        $payment->provider_reference = $bankRef;

        // Handle status codes
        switch ($statusCode) {
            case '00': // Payment Successful
                $payment->status = 'paid';
                $payment->paid_at = $timeCompleted ? \Carbon\Carbon::parse($timeCompleted) : now();
                $payment->save();

                app(MultiPaymentService::class)->onPaymentSuccess($payment);

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
     * Get transaction status for reconciliation.
     * 
     * @param string $reference Transaction reference (merchant ref)
     * @return array
     */
    public function getTransactionStatus(string $reference): array
    {
        if (!$this->apiKey || !$this->baseUrl) {
            return [
                'success' => false,
                'message' => 'GCB API not configured',
                'status' => 'unknown',
            ];
        }

        try {
            // For GCB, we need to query by merchant reference
            // This endpoint may vary based on GCB's actual API
            $response = Http::timeout(10)->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/transactions/status", [
                'merchantRef' => $reference,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Map GCB status codes to standard statuses
                $status = $this->mapGcbStatusToStandard($data['statusCode'] ?? null);
                
                return [
                    'success' => true,
                    'status' => $status,
                    'amount' => isset($data['amount']) ? (int) ($data['amount'] * 100) : null, // Convert to pesewas
                    'reference' => $data['merchantRef'] ?? $reference,
                    'gateway_reference' => $data['bankRef'] ?? null,
                    'paid_at' => $data['timeCompleted'] ?? null,
                    'raw_data' => $data,
                ];
            }

            // Handle specific error cases
            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'status' => 'not_found',
                    'message' => 'Transaction not found at gateway',
                ];
            }

            Log::warning('GCB transaction status query failed', [
                'reference' => $reference,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'Failed to query transaction status',
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GCB transaction status connection error', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'Connection error',
            ];

        } catch (\Exception $e) {
            Log::error('GCB transaction status error', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'Service error',
            ];
        }
    }

    /**
     * Map GCB status codes to standard payment statuses.
     * 
     * @param string|null $statusCode
     * @return string
     */
    protected function mapGcbStatusToStandard(?string $statusCode): string
    {
        return match ($statusCode) {
            '00' => 'paid',
            '01' => 'processing',
            '02' => 'failed',
            '03' => 'expired',
            '04' => 'not_found',
            '05' => 'failed',
            default => 'unknown',
        };
    }

    /**
     * Process a refund through GCB gateway.
     * 
     * ⚠️ FLAG: GCB REFUND API DOCUMENTATION NEEDED
     * This implementation is based on common payment gateway patterns.
     * Actual GCB refund API endpoint, request format, and response structure
     * must be confirmed with GCB API documentation.
     * 
     * @param Payment $payment
     * @param int $amountInPesewas
     * @param string $reason
     * @return array
     */
    public function processRefund(Payment $payment, int $amountInPesewas, string $reason): array
    {
        if (!$this->apiKey || !$this->baseUrl) {
            return [
                'success' => false,
                'message' => 'GCB API not configured',
            ];
        }

        try {
            // ⚠️ FLAG: Verify actual GCB refund endpoint
            // Common patterns: /refunds, /transactions/{id}/refund, /payments/refund
            $endpoint = "{$this->baseUrl}/refunds";

            // ⚠️ FLAG: Verify actual GCB refund request format
            $payload = [
                'merchantRef' => $payment->transaction_reference,
                'bankRef' => $payment->gateway_reference,
                'amount' => $amountInPesewas / 100, // Convert to cedis if GCB expects currency units
                'reason' => $reason,
                'refundRef' => 'REF-' . time() . '-' . $payment->id,
            ];

            Log::info('GCB refund request initiated', [
                'payment_id' => $payment->id,
                'amount' => $amountInPesewas,
                'merchant_ref' => $payment->transaction_reference,
            ]);

            $response = Http::timeout(30)->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();

                // ⚠️ FLAG: Verify actual GCB success response structure
                // Adjust based on actual API response format
                $success = isset($data['statusCode']) && $data['statusCode'] === '00';

                if ($success) {
                    Log::info('GCB refund processed successfully', [
                        'payment_id' => $payment->id,
                        'refund_reference' => $data['refundRef'] ?? null,
                    ]);

                    return [
                        'success' => true,
                        'reference' => $data['refundRef'] ?? $data['bankRef'] ?? null,
                        'amount' => $amountInPesewas,
                        'gateway_response' => $data,
                        'message' => 'Refund processed successfully',
                    ];
                } else {
                    Log::warning('GCB refund failed', [
                        'payment_id' => $payment->id,
                        'response' => $data,
                    ]);

                    return [
                        'success' => false,
                        'message' => $data['message'] ?? 'Refund failed at gateway',
                        'gateway_response' => $data,
                    ];
                }
            }

            Log::error('GCB refund API error', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => 'Gateway API error: ' . $response->status(),
                'gateway_response' => $response->json(),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GCB refund connection error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];

        } catch (\Exception $e) {
            Log::error('GCB refund processing error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Refund processing error: ' . $e->getMessage(),
            ];
        }
    }
}
