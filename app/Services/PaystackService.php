<?php

namespace App\Services;

use App\Exceptions\WebhookVerificationException;
use App\Models\Application;
use App\Models\Payment;
use Illuminate\Http\Request;
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
        $this->baseUrl = config('services.paystack.base_url');
        
        // SECURITY: Validate base URL against SSRF allowlist
        if ($this->baseUrl) {
            app(\App\Services\ExternalUrlValidator::class)->validateExternalUrl($this->baseUrl);
        }
    }
    /**
     * Verify Paystack webhook signature.
     *
     * SECURITY CRITICAL: This method prevents unauthorized payment confirmations.
     * Paystack sends X-Paystack-Signature header with HMAC-SHA512 of the raw request body.
     *
     * @param Request $request
     * @return bool
     * @throws WebhookVerificationException
     */
    public function verifyPaystackSignature(Request $request): bool
    {
        // Get webhook secret (uses secret key for signature)
        $webhookSecret = $this->secretKey;

        if (!$webhookSecret) {
            Log::error('Paystack secret key not configured');
            throw WebhookVerificationException::missingSecret('Paystack');
        }

        // Get signature from header
        $signature = $request->header('X-Paystack-Signature');

        if (!$signature) {
            Log::warning('Paystack webhook missing signature header', [
                'ip' => $request->ip(),
                'headers' => array_keys($request->headers->all()),
            ]);
            throw WebhookVerificationException::missingSignature('Paystack');
        }

        // Get raw request body (CRITICAL: must use getContent(), not all())
        $rawBody = $request->getContent();

        if (empty($rawBody)) {
            Log::warning('Paystack webhook has empty body', [
                'ip' => $request->ip(),
            ]);
            throw WebhookVerificationException::invalidSignature('Paystack', 'Empty request body');
        }

        // Compute HMAC-SHA512 signature (Paystack uses SHA512)
        $computedSignature = hash_hmac('sha512', $rawBody, $webhookSecret);

        // Timing-safe comparison (prevents timing attacks)
        if (!hash_equals($computedSignature, $signature)) {
            Log::warning('Paystack webhook signature verification failed', [
                'ip' => $request->ip(),
                'expected_prefix' => substr($computedSignature, 0, 10) . '...',
                'received_prefix' => substr($signature, 0, 10) . '...',
                'user_agent' => $request->userAgent(),
            ]);
            throw WebhookVerificationException::invalidSignature('Paystack', 'Signature mismatch');
        }

        Log::info('Paystack webhook signature verified successfully', [
            'ip' => $request->ip(),
        ]);

        return true;
    }


    /**
     * Initialize Paystack transaction.
     *
     * @param int $amountPesewas Amount in minor units (pesewas/kobo)
     */
    public function initializeTransaction(
        Application $application,
        int $amountPesewas,
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
            'amount' => $amountPesewas,
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
     * Processes all Paystack webhook events with proper error handling,
     * audit logging, and database transactions.
     * 
     * @param array $payload
     * @return array
     */
    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];
        $reference = $data['reference'] ?? null;

        Log::info('Processing Paystack webhook event', [
            'event' => $event,
            'reference' => $reference,
        ]);

        try {
            return match ($event) {
                'charge.success' => $this->handleChargeSuccess($data, $payload),
                'charge.failed' => $this->handleChargeFailed($data, $payload),
                'refund.processed' => $this->handleRefundProcessed($data, $payload),
                default => [
                    'success' => true,
                    'message' => "Event '{$event}' ignored",
                ],
            };
        } catch (\Exception $e) {
            Log::error('Paystack webhook processing failed', [
                'event' => $event,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Processing failed',
            ];
        }
    }

    /**
     * Handle charge.success event.
     * 
     * @param array $data
     * @param array $fullPayload
     * @return array
     */
    protected function handleChargeSuccess(array $data, array $fullPayload): array
    {
        $reference = $data['reference'] ?? null;
        
        if (!$reference) {
            Log::warning('Paystack charge.success missing reference');
            return ['success' => false, 'message' => 'Missing reference'];
        }

        return \DB::transaction(function () use ($data, $fullPayload, $reference) {
            // Find payment record
            $payment = Payment::where('transaction_reference', $reference)->first();
            
            if (!$payment) {
                Log::warning('Paystack charge.success: Payment not found', [
                    'reference' => $reference,
                ]);
                return ['success' => true, 'message' => 'Payment not found']; // Return 200 to prevent retries
            }

            // Verify amount matches (Paystack amount in kobo = minor units, same as payment->amount in pesewas)
            $gatewayAmountKobo = isset($data['amount']) ? (int) $data['amount'] : null;
            if ($gatewayAmountKobo !== null) {
                try {
                    app(\App\Services\WebhookProcessingService::class)->verifyPaymentAmount(
                        $payment,
                        $gatewayAmountKobo,
                        'paystack',
                        1
                    );
                } catch (\App\Exceptions\PaymentAmountMismatchException $e) {
                    $payment->raw_response = $fullPayload;
                    $payment->save();
                    return ['success' => true, 'message' => 'Amount mismatch - marked suspicious'];
                }
            }

            // Update payment status
            $payment->status = 'paid';
            $payment->paid_at = isset($data['paid_at']) ? \Carbon\Carbon::parse($data['paid_at']) : now();
            $payment->gateway_reference = $data['id'] ?? null;
            $payment->provider_response = $data;
            $payment->raw_response = $fullPayload;
            $payment->save();

            // Update application status
            $application = $payment->application;
            if ($application && in_array($application->status, ['submitted_awaiting_payment', 'pending_payment', 'draft'])) {
                $application->forceFill(['status' => 'payment_confirmed'])->save();
            }

            // Create audit log
            \App\Models\PaymentAuditLog::log('payment_confirmed_webhook', $payment, [
                'new_value' => [
                    'status' => 'paid',
                    'gateway_reference' => $data['id'] ?? null,
                    'paid_at' => $payment->paid_at,
                ],
                'actor_type' => 'gateway',
                'notes' => 'Payment confirmed via Paystack webhook',
            ]);

            // Dispatch email notification (queued)
            \App\Jobs\SendPaymentConfirmationEmail::dispatch($payment);
            broadcast(new \App\Events\PaymentConfirmed($payment));

            Log::info('Paystack charge.success processed successfully', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'amount' => $payment->amount,
            ]);

            return ['success' => true, 'message' => 'Payment confirmed'];
        });
    }

    /**
     * Handle charge.failed event.
     * 
     * @param array $data
     * @param array $fullPayload
     * @return array
     */
    protected function handleChargeFailed(array $data, array $fullPayload): array
    {
        $reference = $data['reference'] ?? null;
        
        if (!$reference) {
            Log::warning('Paystack charge.failed missing reference');
            return ['success' => false, 'message' => 'Missing reference'];
        }

        return \DB::transaction(function () use ($data, $fullPayload, $reference) {
            // Find payment record
            $payment = Payment::where('transaction_reference', $reference)->first();
            
            if (!$payment) {
                Log::warning('Paystack charge.failed: Payment not found', [
                    'reference' => $reference,
                ]);
                return ['success' => true, 'message' => 'Payment not found']; // Return 200 to prevent retries
            }

            // Update payment status
            $failureReason = $data['gateway_response'] ?? $data['message'] ?? 'Payment failed';
            
            $payment->status = 'failed';
            $payment->failure_reason = $failureReason;
            $payment->raw_response = $fullPayload;
            $payment->save();

            // DO NOT change application status - applicant can retry payment

            // Create audit log
            \App\Models\PaymentAuditLog::log('payment_failed_webhook', $payment, [
                'new_value' => [
                    'status' => 'failed',
                    'failure_reason' => $failureReason,
                ],
                'actor_type' => 'gateway',
                'notes' => 'Payment failed via Paystack webhook',
            ]);

            // Dispatch email notification (queued)
            \App\Jobs\SendPaymentFailedEmail::dispatch($payment);

            Log::info('Paystack charge.failed processed successfully', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'failure_reason' => $failureReason,
            ]);

            return ['success' => true, 'message' => 'Payment failure recorded'];
        });
    }

    /**
     * Handle refund.processed event.
     * 
     * @param array $data
     * @param array $fullPayload
     * @return array
     */
    protected function handleRefundProcessed(array $data, array $fullPayload): array
    {
        $transactionReference = $data['transaction_reference'] ?? $data['reference'] ?? null;
        
        if (!$transactionReference) {
            Log::warning('Paystack refund.processed missing transaction reference');
            return ['success' => false, 'message' => 'Missing transaction reference'];
        }

        return \DB::transaction(function () use ($data, $fullPayload, $transactionReference) {
            // Find original payment
            $originalPayment = Payment::where('transaction_reference', $transactionReference)->first();
            
            if (!$originalPayment) {
                Log::warning('Paystack refund.processed: Original payment not found', [
                    'transaction_reference' => $transactionReference,
                ]);
                return ['success' => true, 'message' => 'Original payment not found']; // Return 200 to prevent retries
            }

            // Create new refund payment record
            $refundAmount = isset($data['amount']) ? (int) $data['amount'] : $originalPayment->amount;
            
            $refundPayment = new Payment([
                'application_id' => $originalPayment->application_id,
                'user_id' => $originalPayment->user_id,
                'transaction_reference' => 'REF-' . $transactionReference . '-' . now()->format('YmdHis'),
                'gateway' => 'paystack',
                'payment_provider' => 'paystack',
                'gateway_reference' => $data['id'] ?? null,
                'currency' => $originalPayment->currency,
                'paid_at' => now(),
                'provider_response' => $data,
                'raw_response' => $fullPayload,
                'metadata' => [
                    'refund_type' => 'gateway_processed',
                    'original_payment_id' => $originalPayment->id,
                    'original_reference' => $transactionReference,
                ],
            ]);
            $refundPayment->amount = $refundAmount;
            $refundPayment->status = 'refunded';
            $refundPayment->save();

            // Update application status if full refund
            $application = $originalPayment->application;
            if ($application && $refundAmount >= $originalPayment->amount) {
                $application->forceFill(['status' => 'pending_payment'])->save();
            }

            // Create audit log
            \App\Models\PaymentAuditLog::log('refund_processed_webhook', $refundPayment, [
                'new_value' => [
                    'status' => 'refunded',
                    'amount' => $refundAmount,
                    'original_payment_id' => $originalPayment->id,
                ],
                'actor_type' => 'gateway',
                'notes' => 'Refund processed via Paystack webhook',
            ]);

            // Notify finance officers (queued)
            \App\Jobs\NotifyFinanceOfficerRefund::dispatch($refundPayment, $originalPayment);

            Log::info('Paystack refund.processed processed successfully', [
                'refund_payment_id' => $refundPayment->id,
                'original_payment_id' => $originalPayment->id,
                'refund_amount' => $refundAmount,
            ]);

            return ['success' => true, 'message' => 'Refund processed'];
        });
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

    /**
     * Process a refund through Paystack gateway.
     * 
     * Paystack refund API documentation:
     * https://paystack.com/docs/api/#refund-create
     * 
     * @param Payment $payment
     * @param int $amountInPesewas
     * @return array
     */
    public function processRefund(Payment $payment, int $amountInPesewas): array
    {
        if (!$this->secretKey) {
            return [
                'success' => false,
                'message' => 'Paystack secret key not configured',
            ];
        }

        try {
            // Paystack refund endpoint
            $endpoint = 'https://api.paystack.co/refund';

            // Paystack expects amount in kobo (pesewas)
            $payload = [
                'transaction' => $payment->transaction_reference,
                'amount' => $amountInPesewas, // Amount in kobo
            ];

            Log::info('Paystack refund request initiated', [
                'payment_id' => $payment->id,
                'amount' => $amountInPesewas,
                'reference' => $payment->transaction_reference,
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Paystack returns status: true for success
                if ($data['status'] === true) {
                    $refundData = $data['data'] ?? [];

                    Log::info('Paystack refund processed successfully', [
                        'payment_id' => $payment->id,
                        'refund_id' => $refundData['id'] ?? null,
                        'transaction_reference' => $refundData['transaction_reference'] ?? null,
                    ]);

                    return [
                        'success' => true,
                        'reference' => $refundData['transaction_reference'] ?? $refundData['id'] ?? null,
                        'amount' => $amountInPesewas,
                        'gateway_response' => $data,
                        'message' => $data['message'] ?? 'Refund processed successfully',
                    ];
                } else {
                    Log::warning('Paystack refund failed', [
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

            Log::error('Paystack refund API error', [
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
            Log::error('Paystack refund connection error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];

        } catch (\Exception $e) {
            Log::error('Paystack refund processing error', [
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
