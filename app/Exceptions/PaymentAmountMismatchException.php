<?php

namespace App\Exceptions;

use Exception;

class PaymentAmountMismatchException extends Exception
{
    protected array $details;

    /**
     * Create exception for amount mismatch.
     * Amounts in minor units (pesewas/cents).
     *
     * @param int $paymentId
     * @param int $expectedAmountPesewas
     * @param int $receivedAmountPesewas
     * @param string $gateway
     * @return static
     */
    public static function create(
        int $paymentId,
        int $expectedAmountPesewas,
        int $receivedAmountPesewas,
        string $gateway
    ): static {
        $difference = abs($expectedAmountPesewas - $receivedAmountPesewas);
        $expectedDisplay = number_format($expectedAmountPesewas / 100, 2);
        $receivedDisplay = number_format($receivedAmountPesewas / 100, 2);

        $exception = new static(
            "Payment amount mismatch detected for payment #{$paymentId}. " .
            "Expected: {$expectedDisplay} ({$expectedAmountPesewas} pesewas), " .
            "Received: {$receivedDisplay} ({$receivedAmountPesewas} pesewas), Difference: {$difference} pesewas"
        );

        $exception->details = [
            'payment_id' => $paymentId,
            'expected_amount_pesewas' => $expectedAmountPesewas,
            'received_amount_pesewas' => $receivedAmountPesewas,
            'difference' => $difference,
            'gateway' => $gateway,
            'severity' => 'critical',
        ];

        return $exception;
    }

    /**
     * Get exception details for logging/alerting.
     *
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
