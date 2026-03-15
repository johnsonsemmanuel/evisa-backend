<?php

namespace App\Services;

use App\Exceptions\PaymentAmountMismatchException;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentAmountMismatchAlert;

class WebhookProcessingService
{
    /**
     * Process a webhook with idempotency protection.
     * 
     * This method ensures that each webhook is processed exactly once,
     * even if the gateway sends duplicate notifications.
     * 
     * @param string $gateway Gateway name ('gcb' or 'paystack')
     * @param string $eventId Unique event identifier from gateway
     * @param string $eventType Type of event (e.g., 'charge.success')
     * @param array $payload Full webhook payload
     * @param callable $processor Callback function to process the webhook
     * @return array Result with 'success', 'duplicate', and optional 'message'
     */
    public function processWithIdempotency(
        string $gateway,
        string $eventId,
        string $eventType,
        array $payload,
        callable $processor
    ): array {
        try {
            // Start database transaction for atomic idempotency check + processing
            return DB::transaction(function () use ($gateway, $eventId, $eventType, $payload, $processor) {
                
                // STEP 1: Attempt to create webhook event record
                // This will fail if duplicate due to unique constraint
                try {
                    $webhookEvent = WebhookEvent::create([
                        'gateway' => $gateway,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'payload' => $payload,
                        'processed_at' => null,
                    ]);

                    Log::info("New webhook event recorded", [
                        'gateway' => $gateway,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                    ]);

                } catch (\Illuminate\Database\QueryException $e) {
                    // Check if this is a duplicate key error
                    if ($this->isDuplicateKeyError($e)) {
                        Log::info("Duplicate webhook detected - skipping processing", [
                            'gateway' => $gateway,
                            'event_id' => $eventId,
                            'event_type' => $eventType,
                        ]);

                        return [
                            'success' => true,
                            'duplicate' => true,
                            'message' => 'Webhook already processed',
                        ];
                    }

                    // Re-throw if it's not a duplicate key error
                    throw $e;
                }

                // STEP 2: Process the webhook (only if not duplicate)
                try {
                    $result = $processor($payload);

                    // STEP 3: Mark as processed on success
                    $webhookEvent->markAsProcessed();

                    Log::info("Webhook processed successfully", [
                        'gateway' => $gateway,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                    ]);

                    return [
                        'success' => true,
                        'duplicate' => false,
                        'message' => 'Webhook processed',
                        'result' => $result,
                    ];

                } catch (\Exception $processingError) {
                    // Log processing error but don't mark as processed
                    Log::error("Webhook processing failed", [
                        'gateway' => $gateway,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'error' => $processingError->getMessage(),
                    ]);

                    // Re-throw to rollback transaction
                    throw $processingError;
                }
            });

        } catch (\Exception $e) {
            Log::error("Webhook processing error", [
                'gateway' => $gateway,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'duplicate' => false,
                'message' => 'Processing error: ' . $e->getMessage(),
            ];
        }
    }
    /**
     * Verify payment amount matches expected amount.
     *
     * SECURITY CRITICAL: Prevents payment amount manipulation.
     * All amounts in minor currency units (pesewas/cents/kobo).
     * If amounts differ by more than tolerance pesewas, mark as suspicious.
     *
     * @param Payment $payment
     * @param int $gatewayAmountPesewas Amount from gateway in minor units
     * @param string $gateway
     * @param int $tolerancePesewas Maximum acceptable difference in minor units (default 1)
     * @throws PaymentAmountMismatchException
     * @return void
     */
    public function verifyPaymentAmount(
        Payment $payment,
        int $gatewayAmountPesewas,
        string $gateway,
        int $tolerancePesewas = 1
    ): void {
        $expectedPesewas = (int) $payment->amount;
        $difference = abs($expectedPesewas - $gatewayAmountPesewas);

        if ($difference > $tolerancePesewas) {
            Log::critical('Payment amount mismatch detected', [
                'payment_id' => $payment->id,
                'application_id' => $payment->application_id,
                'expected_pesewas' => $expectedPesewas,
                'gateway_pesewas' => $gatewayAmountPesewas,
                'difference' => $difference,
                'gateway' => $gateway,
                'severity' => 'CRITICAL',
            ]);

            $payment->update([
                'status' => 'suspicious',
                'provider_response' => array_merge(
                    $payment->provider_response ?? [],
                    [
                        'amount_mismatch' => true,
                        'expected_amount_pesewas' => $expectedPesewas,
                        'received_amount_pesewas' => $gatewayAmountPesewas,
                        'difference' => $difference,
                        'flagged_at' => now()->toIso8601String(),
                    ]
                ),
            ]);

            $this->sendAmountMismatchAlert($payment, $expectedPesewas, $gatewayAmountPesewas, $gateway);

            throw PaymentAmountMismatchException::create(
                $payment->id,
                $expectedPesewas,
                $gatewayAmountPesewas,
                $gateway
            );
        }

        Log::info('Payment amount verified successfully', [
            'payment_id' => $payment->id,
            'expected_pesewas' => $expectedPesewas,
            'gateway_pesewas' => $gatewayAmountPesewas,
        ]);
    }

    /**
     * Send alert to administrators about amount mismatch.
     * Amounts in pesewas (minor units).
     *
     * @param Payment $payment
     * @param int $expectedAmountPesewas
     * @param int $receivedAmountPesewas
     * @param string $gateway
     * @return void
     */
    protected function sendAmountMismatchAlert(
        Payment $payment,
        int $expectedAmountPesewas,
        int $receivedAmountPesewas,
        string $gateway
    ): void {
        try {
            // Log to dedicated security log channel
            Log::channel('security')->critical('PAYMENT AMOUNT MISMATCH', [
                'payment_id' => $payment->id,
                'application_id' => $payment->application_id,
                'user_id' => $payment->user_id,
                'expected_amount_pesewas' => $expectedAmountPesewas,
                'received_amount_pesewas' => $receivedAmountPesewas,
                'difference' => abs($expectedAmountPesewas - $receivedAmountPesewas),
                'gateway' => $gateway,
                'timestamp' => now()->toIso8601String(),
            ]);

            // TODO: Send email/SMS alert to admin team
            // Notification::route('mail', config('app.admin_alert_email'))
            //     ->notify(new PaymentAmountMismatchAlert($payment, $expectedAmountPesewas, $receivedAmountPesewas));

        } catch (\Exception $e) {
            Log::error('Failed to send amount mismatch alert', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Check if the exception is a duplicate key error.
     * 
     * @param \Illuminate\Database\QueryException $e
     * @return bool
     */
    protected function isDuplicateKeyError(\Illuminate\Database\QueryException $e): bool
    {
        // MySQL error code 1062 = Duplicate entry
        // PostgreSQL error code 23505 = Unique violation
        $errorCode = $e->errorInfo[1] ?? null;
        
        return in_array($errorCode, [1062, 23505]) || 
               str_contains($e->getMessage(), 'Duplicate entry') ||
               str_contains($e->getMessage(), 'unique constraint');
    }

    /**
     * Get webhook event statistics for monitoring.
     * 
     * @param string|null $gateway Optional gateway filter
     * @param int $hours Number of hours to look back
     * @return array
     */
    public function getStatistics(?string $gateway = null, int $hours = 24): array
    {
        $query = WebhookEvent::where('created_at', '>=', now()->subHours($hours));
        
        if ($gateway) {
            $query->where('gateway', $gateway);
        }

        $total = $query->count();
        $processed = (clone $query)->processed()->count();
        $unprocessed = (clone $query)->unprocessed()->count();

        return [
            'total' => $total,
            'processed' => $processed,
            'unprocessed' => $unprocessed,
            'processing_rate' => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
            'period_hours' => $hours,
            'gateway' => $gateway ?? 'all',
        ];
    }

    /**
     * Clean up old processed webhook events.
     * 
     * @param int $daysToKeep Number of days to keep processed events
     * @return int Number of records deleted
     */
    public function cleanupOldEvents(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deleted = WebhookEvent::where('processed_at', '<', $cutoffDate)
            ->delete();

        Log::info("Cleaned up old webhook events", [
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoffDate->toDateTimeString(),
        ]);

        return $deleted;
    }
}
