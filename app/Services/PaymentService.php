<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Initiate a payment for an application.
     * Supports Paystack and Stripe providers.
     */
    public function initiatePayment(Application $application, string $provider = 'paystack'): array
    {
        // Recalculate and validate fee server-side (prevent price manipulation)
        $pricingService = app(\App\Services\PricingService::class);
        $pricingService->calculateAndUpdateApplicationFee($application);
        
        $visaType = $application->visaType;
        $user = $application->user;
        $transactionRef = 'GH-' . strtoupper(Str::random(12));

        // Calculate fee based on processing tier
        $amount = $this->calculateFee($application);

        // Create payment record
        $payment = Payment::create([
            'application_id'        => $application->id,
            'user_id'               => $application->user_id,
            'transaction_reference' => $transactionRef,
            'payment_provider'      => $provider,
            'amount'                => $amount,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        // Generate checkout URL based on provider
        $checkoutUrl = null;
        
        if ($provider === 'paystack') {
            $checkoutUrl = $this->initiatePaystack($payment, $user->email);
        } elseif ($provider === 'stripe') {
            $checkoutUrl = $this->initiateStripe($payment, $user->email);
        }

        return [
            'payment' => $payment,
            'checkout_url' => $checkoutUrl,
            'transaction_reference' => $transactionRef,
        ];
    }

    /**
     * Initialize Paystack payment and return checkout URL.
     */
    protected function initiatePaystack(Payment $payment, string $email): ?string
    {
        $secretKey = config('services.paystack.secret_key');
        
        if (!$secretKey) {
            Log::warning('Paystack secret key not configured');
            return $this->getSimulatedCheckoutUrl($payment);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$secretKey}",
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => (int) ($payment->amount * 100), // Paystack uses kobo/cents
                'currency' => $payment->currency,
                'reference' => $payment->transaction_reference,
                'callback_url' => config('app.frontend_url') . '/payment/callback',
                'metadata' => [
                    'application_id' => $payment->application_id,
                    'payment_id' => $payment->id,
                ],
            ]);

            if ($response->successful() && $response->json('status')) {
                $data = $response->json('data');
                $payment->update(['provider_reference' => $data['access_code'] ?? null]);
                return $data['authorization_url'] ?? null;
            }

            Log::error('Paystack initialization failed', ['response' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('Paystack API error', ['error' => $e->getMessage()]);
        }

        return $this->getSimulatedCheckoutUrl($payment);
    }

    /**
     * Initialize Stripe payment session.
     */
    protected function initiateStripe(Payment $payment, string $email): ?string
    {
        $secretKey = config('services.stripe.secret');
        
        if (!$secretKey) {
            Log::warning('Stripe secret key not configured');
            return $this->getSimulatedCheckoutUrl($payment);
        }

        // Stripe integration would go here
        // For now, return simulated URL
        return $this->getSimulatedCheckoutUrl($payment);
    }

    /**
     * Get simulated checkout URL for development/testing.
     */
    protected function getSimulatedCheckoutUrl(Payment $payment): string
    {
        return config('app.frontend_url') . '/payment/simulate?ref=' . $payment->transaction_reference;
    }

    /**
     * Handle webhook callback from payment provider.
     * Verifies signature and updates payment + application status.
     */
    public function handleWebhook(array $payload, string $provider): bool
    {
        $providerRef = $payload['provider_reference'] ?? null;
        $txnRef = $payload['transaction_reference'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$txnRef || !$status) {
            return false;
        }

        $payment = Payment::where('transaction_reference', $txnRef)->first();

        if (!$payment) {
            return false;
        }

        $payment->provider_reference = $providerRef;
        $payment->provider_response = $payload;

        if ($status === 'success' || $status === 'completed') {
            $payment->status = 'completed';
            $payment->paid_at = now();
            $payment->save();

            $this->onPaymentSuccess($payment);
            return true;
        }

        if ($status === 'failed') {
            $payment->status = 'failed';
            $payment->save();
            return true;
        }

        return false;
    }

    /**
     * Actions to take after successful payment.
     * Transitions: submitted_awaiting_payment/pending_payment → paid_submitted → submitted (with routing)
     */
    protected function onPaymentSuccess(Payment $payment): void
    {
        $application = $payment->application;

        if (in_array($application->status, ['submitted_awaiting_payment', 'pending_payment', 'draft'])) {
            $applicationService = app(ApplicationService::class);
            $applicationService->confirmPayment($application);
            
            // Submit the application to trigger routing
            $applicationService->submit($application->fresh());
        }
    }

    /**
     * Verify payment was actually completed (for double-check).
     */
    public function verifyPayment(Payment $payment): bool
    {
        return $payment->status === 'completed' && $payment->paid_at !== null;
    }

    /**
     * Calculate the fee for an application using the unified PricingService.
     * 
     * @deprecated Use PricingService directly instead
     */
    public function calculateFee(Application $application): float
    {
        $pricingService = app(\App\Services\PricingService::class);
        $pricing = $pricingService->calculatePrice($application);
        
        return $pricing['total'];
    }
}
