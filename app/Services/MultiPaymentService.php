<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use App\Services\ApplicationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MultiPaymentService
{
    protected array $providers = ['paystack', 'gcb', 'stripe', 'mobile_money', 'bank_transfer'];

    public function __construct(
        protected PaystackService $paystackService,
        protected GcbPaymentService $gcbService
    ) {}

    /**
     * Get available payment methods for a country.
     */
    public function getAvailablePaymentMethods(string $countryCode = 'GH'): array
    {
        $methods = [
            [
                'id' => 'gcb',
                'provider' => 'gcb',
                'name' => 'GCB Payment Gateway',
                'description' => 'Pay with cards, mobile money, or bank account via GCB',
                'icon' => 'building',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
                'badge' => 'Local',
            ],
            [
                'id' => 'paystack',
                'provider' => 'paystack',
                'name' => 'Paystack',
                'description' => 'Pay with cards, mobile money, or bank transfer',
                'icon' => 'credit-card',
                'currencies' => ['GHS', 'USD'],
                'countries' => ['GH', 'NG', 'ZA', 'KE'],
                'badge' => 'Popular',
            ],
            [
                'id' => 'bank_transfer',
                'provider' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'description' => 'Direct bank transfer (manual verification)',
                'icon' => 'building',
                'currencies' => ['GHS', 'USD'],
                'countries' => ['*'],
                'badge' => null,
            ],
        ];

        // Filter methods available for the country
        return array_values(array_filter($methods, function ($method) use ($countryCode) {
            return in_array('*', $method['countries']) || in_array($countryCode, $method['countries']);
        }));
    }

    /**
     * Initialize payment with selected method.
     * Note: Test API keys still redirect to real payment gateway checkout pages.
     * Test mode only affects verification (simulating successful payments for testing).
     */
    public function initializePayment(
        Application $application,
        string $paymentMethod,
        string $currency = 'GHS',
        ?string $callbackUrl = null
    ): array {
        $amount = $this->calculateAmount($application, $currency);

        return match ($paymentMethod) {
            'gcb' => $this->initializeGcb($application, $amount, $currency, null, $callbackUrl),
            'paystack' => $this->initializePaystack($application, $amount, $currency, 'paystack_card', $callbackUrl),
            'bank_transfer' => $this->initializeBankTransfer($application, $amount, $currency),
            default => ['success' => false, 'message' => 'Invalid payment method'],
        };
    }

    /**
     * Check if we're in test mode based on API keys.
     */
    protected function isTestMode(): bool
    {
        $paystackKey = config('services.paystack.secret_key', '');
        $stripeKey = config('services.stripe.secret_key', '');
        $gcbUrl = config('services.gcb.base_url', '');

        return str_contains($paystackKey, 'test') || 
               str_contains($stripeKey, 'test') || 
               str_contains($gcbUrl, 'uat') ||
               str_contains($gcbUrl, 'test');
    }

    /**
     * Initialize test payment (mock for development).
     */
    protected function initializeTestPayment(
        Application $application,
        string $paymentMethod,
        float $amount,
        string $currency,
        ?string $callbackUrl
    ): array {
        $reference = $this->generateReference($application, 'TEST');

        // Create payment record
        $payment = $this->createPaymentRecord(
            $application,
            $reference,
            $paymentMethod, // Use the method directly (gcb, paystack, bank_transfer)
            $amount,
            $currency,
            $paymentMethod
        );

        // For bank transfer, return instructions
        if ($paymentMethod === 'bank_transfer') {
            return $this->initializeBankTransfer($application, $amount, $currency);
        }

        // For other methods, return a test checkout URL
        $testUrl = config('app.frontend_url') . '/test-payment?' . http_build_query([
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'method' => $paymentMethod,
            'callback' => $callbackUrl,
        ]);

        return [
            'success' => true,
            'provider' => explode('_', $paymentMethod)[0],
            'checkout_url' => $testUrl,
            'authorization_url' => $testUrl, // Some frontend code expects this key
            'reference' => $reference,
            'test_mode' => true,
            'message' => 'Test mode: This is a simulated payment for development purposes',
        ];
    }

    /**
     * Initialize GCB payment.
     */
    protected function initializeGcb(
        Application $application,
        float $amount,
        string $currency,
        ?string $paymentOption,
        ?string $callbackUrl
    ): array {
        $reference = $this->generateReference($application, 'GCB');

        $result = $this->gcbService->initializeCheckout(
            $application,
            $amount,
            $currency,
            $callbackUrl,
            $paymentOption // null means all payment options available
        );

        if ($result['success']) {
            $payment = $this->createPaymentRecord(
                $application,
                $reference,
                'gcb',
                $amount,
                $currency,
                'gcb'
            );

            // Store GCB checkout ID in metadata
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'checkout_id' => $result['checkout_id'] ?? null,
                    'merchant_ref' => $result['merchant_ref'] ?? null,
                ]),
            ]);

            return [
                'success' => true,
                'provider' => 'gcb',
                'authorization_url' => $result['checkout_url'],
                'reference' => $reference,
                'checkout_id' => $result['checkout_id'],
            ];
        }

        return $result;
    }

    /**
     * Initialize Paystack payment.
     */
    protected function initializePaystack(
        Application $application,
        float $amount,
        string $currency,
        string $method,
        ?string $callbackUrl
    ): array {
        $reference = $this->generateReference($application, 'PS');
        $channels = ['card', 'bank', 'mobile_money']; // All channels available

        $result = $this->paystackService->initializeTransaction(
            $application,
            $amount,
            $currency,
            $channels,
            $callbackUrl
        );

        if ($result['success']) {
            $payment = $this->createPaymentRecord($application, $reference, 'paystack', $amount, $currency, 'paystack');
            
            // Store Paystack's reference in provider_reference for verification
            $payment->update(['provider_reference' => $result['reference']]);

            return [
                'success' => true,
                'provider' => 'paystack',
                'authorization_url' => $result['authorization_url'],
                'reference' => $result['reference'],
            ];
        }

        return $result;
    }

    /**
     * Initialize Stripe payment.
     */
    protected function initializeStripe(
        Application $application,
        float $amount,
        string $currency,
        ?string $callbackUrl
    ): array {
        $reference = $this->generateReference($application, 'ST');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.stripe.secret_key'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.stripe.com/v1/checkout/sessions', [
                'payment_method_types[]' => 'card',
                'line_items[0][price_data][currency]' => strtolower($currency),
                'line_items[0][price_data][product_data][name]' => 'Ghana eVisa - ' . $application->visaType?->name,
                'line_items[0][price_data][unit_amount]' => (int) ($amount * 100),
                'line_items[0][quantity]' => 1,
                'mode' => 'payment',
                'success_url' => ($callbackUrl ?? config('app.frontend_url') . '/payment/callback') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/payment/cancelled',
                'client_reference_id' => $reference,
                'metadata[application_id]' => $application->id,
                'metadata[reference_number]' => $application->reference_number,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $this->createPaymentRecord($application, $reference, 'stripe', $amount, $currency, 'stripe_card');

                return [
                    'success' => true,
                    'provider' => 'stripe',
                    'authorization_url' => $data['url'],
                    'session_id' => $data['id'],
                    'reference' => $reference,
                ];
            }

            return ['success' => false, 'message' => 'Stripe initialization failed'];
        } catch (\Exception $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment service unavailable'];
        }
    }

    /**
     * Initialize bank transfer payment.
     */
    protected function initializeBankTransfer(
        Application $application,
        float $amount,
        string $currency
    ): array {
        $reference = $this->generateReference($application, 'BT');

        $this->createPaymentRecord($application, $reference, 'bank_transfer', $amount, $currency, 'bank_transfer');

        // Bank details for manual transfer
        $bankDetails = [
            'bank_name' => 'Ghana Commercial Bank',
            'account_name' => 'Ghana Immigration Service - eVisa',
            'account_number' => '1234567890123',
            'branch' => 'Accra Main Branch',
            'swift_code' => 'GHCBGHAC',
        ];

        return [
            'success' => true,
            'provider' => 'bank_transfer',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'bank_details' => $bankDetails,
            'instructions' => [
                'Use reference number as payment description',
                'Upload proof of payment after transfer',
                'Payment verification takes 1-2 business days',
            ],
        ];
    }

    /**
     * Verify payment status.
     */
    public function verifyPayment(string $reference): array
    {
        $payment = Payment::where('transaction_reference', $reference)
            ->orWhere('provider_reference', $reference)
            ->first();

        if (!$payment) {
            return ['success' => false, 'status' => 'not_found'];
        }

        if ($payment->status === 'completed') {
            return ['success' => true, 'status' => 'completed', 'payment' => $payment];
        }

        // Check if this is a test payment
        if (str_starts_with($reference, 'TEST-')) {
            return $this->verifyTestPayment($reference, $payment);
        }

        // Verify with provider
        return match ($payment->payment_provider) {
            'gcb' => $this->verifyGcb($reference, $payment),
            'paystack' => $this->verifyPaystack($reference, $payment),
            'stripe' => $this->verifyStripe($reference, $payment),
            'bank_transfer' => ['success' => true, 'status' => $payment->status, 'payment' => $payment],
            default => ['success' => false, 'status' => 'unknown_provider'],
        };
    }

    /**
     * Verify test payment (for development).
     */
    protected function verifyTestPayment(string $reference, Payment $payment): array
    {
        // In test mode, we simulate payment completion
        // This would normally be handled by the test payment page callback
        return ['success' => true, 'status' => $payment->status, 'payment' => $payment];
    }

    /**
     * Verify GCB payment.
     */
    protected function verifyGcb(string $reference, Payment $payment): array
    {
        $checkoutId = $payment->metadata['checkout_id'] ?? null;

        if (!$checkoutId) {
            return ['success' => false, 'status' => 'missing_checkout_id'];
        }

        $result = $this->gcbService->checkTransactionStatus($checkoutId);

        if ($result['success']) {
            $statusCode = $result['status_code'];

            if ($statusCode === '00') {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => $result['time_completed'] ? \Carbon\Carbon::parse($result['time_completed']) : now(),
                    'provider_reference' => $result['bank_ref'],
                ]);

                $this->onPaymentSuccess($payment);

                return ['success' => true, 'status' => 'completed', 'payment' => $payment->fresh()];
            }

            return ['success' => true, 'status' => $payment->status, 'gcb_status' => $statusCode];
        }

        return $result;
    }

    /**
     * Verify Paystack payment.
     */
    protected function verifyPaystack(string $reference, Payment $payment): array
    {
        $result = $this->paystackService->verifyTransaction($reference);

        if ($result['success'] && $result['status'] === 'success') {
            $payment->update([
                'status' => 'completed',
                'paid_at' => $result['paid_at'] ? \Carbon\Carbon::parse($result['paid_at']) : now(),
                'provider_reference' => $result['reference'],
                'provider_response' => $result['raw_data'] ?? null,
            ]);

            $this->onPaymentSuccess($payment);

            return ['success' => true, 'status' => 'completed', 'payment' => $payment->fresh()];
        }

        return ['success' => false, 'status' => $payment->status, 'message' => $result['message'] ?? 'Verification failed'];
    }

    /**
     * Verify Stripe payment.
     */
    protected function verifyStripe(string $reference, Payment $payment): array
    {
        try {
            // For Stripe, we typically verify via webhooks
            // This is a fallback check
            return ['success' => true, 'status' => $payment->status, 'payment' => $payment];
        } catch (\Exception $e) {
            Log::error('Stripe verify error: ' . $e->getMessage());
            return ['success' => false, 'status' => 'verification_failed'];
        }
    }

    /**
     * Confirm bank transfer manually.
     */
    public function confirmBankTransfer(string $reference, string $proofUrl, ?int $confirmedBy = null): array
    {
        $payment = Payment::where('transaction_reference', $reference)
            ->where('payment_provider', 'bank_transfer')
            ->first();

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'proof_url' => $proofUrl,
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->onPaymentSuccess($payment);

        return ['success' => true, 'payment' => $payment->fresh()];
    }

    /**
     * Handle successful payment.
     * Transitions: draft → submitted_awaiting_payment → paid_submitted → submitted (with routing)
     */
    public function onPaymentSuccess(Payment $payment): void
    {
        $application = $payment->application;
        if (!$application) return;

        // Store total fee
        $application->update(['total_fee' => $payment->amount]);

        $applicationService = app(ApplicationService::class);

        // Handle draft applications - submit for payment first
        if ($application->status === 'draft') {
            $applicationService->submitForPayment($application);
            $application = $application->fresh();
        }

        // Confirm payment and submit for routing
        if (in_array($application->status, ['submitted_awaiting_payment', 'pending_payment'])) {
            $applicationService->confirmPayment($application);
            $applicationService->submit($application->fresh());
        }

        Log::info("Payment completed for application {$application->reference_number}");
    }

    /**
     * Create payment record.
     */
    protected function createPaymentRecord(
        Application $application,
        string $reference,
        string $provider,
        float $amount,
        string $currency,
        string $method
    ): Payment {
        // Ensure application exists and has an ID
        if (!$application->exists || !$application->id) {
            throw new \InvalidArgumentException('Cannot create payment for non-existent application');
        }

        return Payment::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'transaction_reference' => $reference,
            'payment_provider' => $provider,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'metadata' => ['payment_method' => $method],
        ]);
    }

    /**
     * Calculate amount in specified currency using unified PricingService.
     */
    protected function calculateAmount(Application $application, string $currency): float
    {
        $pricingService = app(\App\Services\PricingService::class);
        $pricing = $pricingService->calculatePrice($application);
        
        $amountUsd = $pricing['total'];

        // Convert if needed (simplified - use real exchange rates in production)
        $rates = ['USD' => 1, 'GHS' => 12.5, 'EUR' => 0.92, 'GBP' => 0.79];
        $rate = $rates[$currency] ?? 1;

        return round($amountUsd * $rate, 2);
    }

    /**
     * Generate payment reference.
     */
    protected function generateReference(Application $application, string $prefix): string
    {
        return $prefix . '-' . $application->reference_number . '-' . Str::random(6);
    }
}
