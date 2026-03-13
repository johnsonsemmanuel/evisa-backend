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
                'id' => 'gcb_card',
                'provider' => 'gcb',
                'name' => 'GCB Card Payment',
                'description' => 'Pay with Visa, Mastercard via GCB Gateway',
                'icon' => 'credit-card',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
            ],
            [
                'id' => 'gcb_momo',
                'provider' => 'gcb',
                'name' => 'GCB Mobile Money',
                'description' => 'MTN, Vodafone, AirtelTigo via GCB',
                'icon' => 'smartphone',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
            ],
            [
                'id' => 'gcb_mtn',
                'provider' => 'gcb',
                'name' => 'MTN Mobile Money',
                'description' => 'Pay with MTN MoMo',
                'icon' => 'smartphone',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
            ],
            [
                'id' => 'gcb_vodafone',
                'provider' => 'gcb',
                'name' => 'Vodafone Cash',
                'description' => 'Pay with Vodafone Cash',
                'icon' => 'smartphone',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
            ],
            [
                'id' => 'gcb_airteltigo',
                'provider' => 'gcb',
                'name' => 'AirtelTigo Money',
                'description' => 'Pay with AirtelTigo Money',
                'icon' => 'smartphone',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
            ],
            [
                'id' => 'gcb_account',
                'provider' => 'gcb',
                'name' => 'GCB Account',
                'description' => 'Pay with your GCB Bank Account',
                'icon' => 'building',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
            ],
            [
                'id' => 'paystack_card',
                'provider' => 'paystack',
                'name' => 'Paystack Card Payment',
                'description' => 'Pay with Visa, Mastercard, or Verve',
                'icon' => 'credit-card',
                'currencies' => ['GHS', 'NGN', 'USD'],
                'countries' => ['GH', 'NG', 'ZA', 'KE'],
            ],
            [
                'id' => 'paystack_mobile_money',
                'provider' => 'paystack',
                'name' => 'Paystack Mobile Money',
                'description' => 'Pay with MTN MoMo, Vodafone Cash, AirtelTigo Money',
                'icon' => 'smartphone',
                'currencies' => ['GHS'],
                'countries' => ['GH'],
            ],
            [
                'id' => 'stripe_card',
                'provider' => 'stripe',
                'name' => 'International Card',
                'description' => 'Pay with international Visa, Mastercard, Amex',
                'icon' => 'globe',
                'currencies' => ['USD', 'EUR', 'GBP'],
                'countries' => ['*'],
            ],
            [
                'id' => 'bank_transfer',
                'provider' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'description' => 'Direct bank transfer (manual verification)',
                'icon' => 'building',
                'currencies' => ['GHS', 'USD'],
                'countries' => ['*'],
            ],
        ];

        // Filter methods available for the country
        return array_values(array_filter($methods, function ($method) use ($countryCode) {
            return in_array('*', $method['countries']) || in_array($countryCode, $method['countries']);
        }));
    }

    /**
     * Initialize payment with selected method.
     */
    public function initializePayment(
        Application $application,
        string $paymentMethod,
        string $currency = 'GHS',
        ?string $callbackUrl = null
    ): array {
        $amount = $this->calculateAmount($application, $currency);

        return match ($paymentMethod) {
            'gcb_card' => $this->initializeGcb($application, $amount, $currency, 'card', $callbackUrl),
            'gcb_momo' => $this->initializeGcb($application, $amount, $currency, 'momo', $callbackUrl),
            'gcb_mtn' => $this->initializeGcb($application, $amount, $currency, 'mtn', $callbackUrl),
            'gcb_vodafone' => $this->initializeGcb($application, $amount, $currency, 'vodafone', $callbackUrl),
            'gcb_airteltigo' => $this->initializeGcb($application, $amount, $currency, 'airteltigo', $callbackUrl),
            'gcb_account' => $this->initializeGcb($application, $amount, $currency, 'gcb', $callbackUrl),
            'paystack_card', 'paystack_mobile_money' => $this->initializePaystack($application, $amount, $currency, $paymentMethod, $callbackUrl),
            'stripe_card' => $this->initializeStripe($application, $amount, $currency, $callbackUrl),
            'bank_transfer' => $this->initializeBankTransfer($application, $amount, $currency),
            default => ['success' => false, 'message' => 'Invalid payment method'],
        };
    }

    /**
     * Initialize GCB payment.
     */
    protected function initializeGcb(
        Application $application,
        float $amount,
        string $currency,
        string $paymentOption,
        ?string $callbackUrl
    ): array {
        $reference = $this->generateReference($application, 'GCB');

        $result = $this->gcbService->initializeCheckout(
            $application,
            $amount,
            $currency,
            $callbackUrl,
            $paymentOption
        );

        if ($result['success']) {
            $payment = $this->createPaymentRecord(
                $application,
                $reference,
                'gcb',
                $amount,
                $currency,
                "gcb_{$paymentOption}"
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
        $channels = $method === 'paystack_mobile_money' ? ['mobile_money'] : ['card', 'bank'];

        $result = $this->paystackService->initializeTransaction(
            $application,
            $amount,
            $currency,
            $channels,
            $callbackUrl
        );

        if ($result['success']) {
            $this->createPaymentRecord($application, $reference, 'paystack', $amount, $currency, $method);

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
     * Transitions: submitted_awaiting_payment/pending_payment → paid_submitted
     * Routing is NOT triggered here — it is handled separately.
     */
    protected function onPaymentSuccess(Payment $payment): void
    {
        $application = $payment->application;
        if (!$application) return;

        // Store total fee
        $application->update(['total_fee' => $payment->amount]);

        // Use centralized ApplicationService for proper status transition + audit trail
        if (in_array($application->status, ['submitted_awaiting_payment', 'pending_payment'])) {
            app(ApplicationService::class)->confirmPayment($application);
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
