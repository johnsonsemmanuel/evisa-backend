<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Exceptions\GCBApiException;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orchestrates payment initiation with GCB primary and Paystack fallback.
 */
class PaymentGatewayService
{
    private const GCB_AVAILABLE_CACHE_KEY = 'gcb:available';
    private const GCB_AVAILABLE_TTL_SECONDS = 60;

    public function __construct(
        private GCBPaymentService $gcb,
        private PaystackPaymentService $paystack,
    ) {}

    /**
     * Initiate payment. Prefers GCB (government mandate); falls back to Paystack on GCB failure.
     *
     * @param  string  $preferredGateway  'gcb' | 'paystack' | 'auto'
     */
    public function initiatePayment(Payment $payment, string $preferredGateway = 'gcb'): array
    {
        $preferredGateway = strtolower($preferredGateway);
        if (!in_array($preferredGateway, ['gcb', 'paystack', 'auto'], true)) {
            throw new \InvalidArgumentException("Invalid preferred gateway: {$preferredGateway}. Use gcb, paystack, or auto.");
        }

        if ($preferredGateway === 'gcb' || $preferredGateway === 'auto') {
            if ($this->isGCBAvailable()) {
                try {
                    $result = $this->gcb->initiatePayment($payment);
                    $payment->update(['gateway' => PaymentGateway::GCB]);
                    return $this->normalizeInitiateResult($result, 'gcb');
                } catch (GCBApiException $e) {
                    Log::warning('GCB payment initiation failed, trying Paystack fallback', [
                        'payment_id' => $payment->id,
                        'gcb_error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($preferredGateway === 'paystack' || $preferredGateway === 'auto') {
            $result = $this->paystack->initiatePayment($payment);
            $payment->update(['gateway' => PaymentGateway::Paystack]);
            return $this->normalizeInitiateResult($result, 'paystack');
        }

        throw new RuntimeException('No payment gateway available');
    }

    private function normalizeInitiateResult(array $result, string $gateway): array
    {
        $url = $result['authorization_url'] ?? $result['checkout_url'] ?? null;
        $reference = $result['reference'] ?? $result['transaction_reference'] ?? null;
        return array_merge($result, [
            'success' => true,
            'authorization_url' => $url ?? $result['authorization_url'] ?? null,
            'checkout_url' => $url ?? $result['checkout_url'] ?? null,
            'reference' => $reference,
            'gateway' => $gateway,
        ]);
    }

    public function isGCBAvailable(): bool
    {
        return Cache::remember(self::GCB_AVAILABLE_CACHE_KEY, self::GCB_AVAILABLE_TTL_SECONDS, fn () => $this->gcb->ping());
    }

    /**
     * Set GCB availability in cache (used by CheckGCBAvailability command).
     */
    public function setGCBAvailable(bool $available): void
    {
        Cache::put(self::GCB_AVAILABLE_CACHE_KEY, $available, self::GCB_AVAILABLE_TTL_SECONDS);
    }

    public function getTransactionStatus(Payment $payment): array
    {
        $gateway = $payment->gateway;
        if (!$gateway instanceof PaymentGateway) {
            $gateway = PaymentGateway::tryFrom($payment->gateway ?? $payment->payment_provider ?? '') ?? $gateway;
        }
        $value = $gateway instanceof PaymentGateway ? $gateway->value : (string) $gateway;

        return match ($value) {
            'gcb' => $this->gcb->getTransactionStatus($payment->gateway_reference ?? $payment->transaction_reference ?? ''),
            'paystack' => $this->paystack->verifyTransaction($payment->gateway_reference ?? $payment->transaction_reference ?? ''),
            default => throw new \InvalidArgumentException("Unknown gateway: {$value}"),
        };
    }

    public function processRefund(Payment $payment, int $amount, string $reason): array
    {
        $gateway = $payment->gateway;
        if (!$gateway instanceof PaymentGateway) {
            $gateway = PaymentGateway::tryFrom($payment->gateway ?? $payment->payment_provider ?? '') ?? $gateway;
        }
        $value = $gateway instanceof PaymentGateway ? $gateway->value : (string) $gateway;

        return match ($value) {
            'gcb' => $this->gcb->processRefund($payment, $amount, $reason),
            'paystack' => $this->paystack->processRefund($payment, $amount),
            default => throw new \InvalidArgumentException("Cannot refund unknown gateway: {$value}"),
        };
    }
}
