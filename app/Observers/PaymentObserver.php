<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\PaymentAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CriticalPaymentAlert;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     * Logs when a payment is first initiated (after it has an ID).
     */
    public function created(Payment $payment): void
    {
        PaymentAuditLog::log('payment_initiated', $payment, [
            'new_value' => [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway,
                'payment_provider' => $payment->payment_provider,
                'application_id' => $payment->application_id,
                'user_id' => $payment->user_id,
                'status' => $payment->status ?? 'initiated',
            ],
            'actor_id' => Auth::id() ?? $payment->user_id,
            'actor_type' => Auth::check() ? 'user' : 'system',
            'notes' => 'Payment initiated',
        ]);
    }

    /**
     * Handle the Payment "updating" event.
     * Tracks all changes to payment records, with special handling for critical changes.
     */
    public function updating(Payment $payment): void
    {
        $original = $payment->getOriginal();
        $changes = $payment->getDirty();

        // Remove sensitive fields from audit logs
        unset($changes['raw_response']);
        unset($original['raw_response']);

        // Check for status changes
        if (isset($changes['status']) && $original['status'] !== $changes['status']) {
            $this->logStatusChange($payment, $original['status'], $changes['status']);
        }

        // CRITICAL: Check for amount changes (should NEVER happen after initiation)
        if (isset($changes['amount']) && $original['amount'] !== $changes['amount']) {
            $this->logAmountChange($payment, $original['amount'], $changes['amount']);
        }

        // Log other significant changes
        if (isset($changes['gateway_reference']) && $original['gateway_reference'] !== $changes['gateway_reference']) {
            $this->logGatewayReferenceChange($payment, $original['gateway_reference'], $changes['gateway_reference']);
        }

        if (isset($changes['failure_reason'])) {
            $this->logFailureReason($payment, $changes['failure_reason']);
        }
    }

    /**
     * Handle the Payment "deleting" event.
     * This should NEVER fire - payments use soft deletes only.
     */
    public function deleting(Payment $payment): void
    {
        // Log the soft delete attempt
        PaymentAuditLog::log('payment_deleted', $payment, [
            'old_value' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'application_id' => $payment->application_id,
            ],
            'actor_id' => Auth::id(),
            'actor_type' => 'user',
            'notes' => 'Payment soft deleted - this should only happen for cleanup of test data',
        ]);

        // If this is a hard delete (not soft delete), log critical alert
        if (!$payment->isForceDeleting() === false) {
            Log::critical('PAYMENT HARD DELETE ATTEMPTED', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'actor_id' => Auth::id(),
            ]);
        }
    }

    /**
     * Log status change.
     */
    protected function logStatusChange(Payment $payment, string $oldStatus, string $newStatus): void
    {
        PaymentAuditLog::log('payment_status_changed', $payment, [
            'old_value' => [
                'status' => $oldStatus,
            ],
            'new_value' => [
                'status' => $newStatus,
                'gateway_reference' => $payment->gateway_reference,
                'paid_at' => $payment->paid_at,
            ],
            'notes' => "Status changed from '{$oldStatus}' to '{$newStatus}'",
        ]);

        // Log to application logs for monitoring
        Log::info('Payment status changed', [
            'payment_id' => $payment->id,
            'application_id' => $payment->application_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'actor_id' => Auth::id(),
        ]);
    }

    /**
     * Log amount change - CRITICAL EVENT.
     * Amount should NEVER change after payment initiation.
     */
    protected function logAmountChange(Payment $payment, int $oldAmount, int $newAmount): void
    {
        PaymentAuditLog::log('payment_amount_changed', $payment, [
            'old_value' => [
                'amount' => $oldAmount,
                'amount_currency' => $oldAmount / 100,
            ],
            'new_value' => [
                'amount' => $newAmount,
                'amount_currency' => $newAmount / 100,
            ],
            'notes' => 'CRITICAL: Payment amount changed after initiation - possible fraud or system error',
        ]);

        // Log critical alert
        Log::critical('PAYMENT AMOUNT CHANGED AFTER INITIATION', [
            'payment_id' => $payment->id,
            'application_id' => $payment->application_id,
            'old_amount' => $oldAmount,
            'new_amount' => $newAmount,
            'difference' => $newAmount - $oldAmount,
            'actor_id' => Auth::id(),
            'ip_address' => request()?->ip(),
        ]);

        // TODO: Send notification to admin/finance team
        // This would typically trigger an email or Slack notification
        // Notification::route('mail', config('app.finance_email'))
        //     ->notify(new CriticalPaymentAlert($payment, 'amount_changed'));
    }

    /**
     * Log gateway reference change.
     */
    protected function logGatewayReferenceChange(Payment $payment, ?string $oldReference, ?string $newReference): void
    {
        PaymentAuditLog::log('payment_gateway_reference_updated', $payment, [
            'old_value' => [
                'gateway_reference' => $oldReference,
            ],
            'new_value' => [
                'gateway_reference' => $newReference,
            ],
            'notes' => 'Gateway reference updated',
        ]);
    }

    /**
     * Log failure reason.
     */
    protected function logFailureReason(Payment $payment, ?string $failureReason): void
    {
        PaymentAuditLog::log('payment_failure_recorded', $payment, [
            'new_value' => [
                'failure_reason' => $failureReason,
                'status' => $payment->status,
            ],
            'notes' => 'Payment failure reason recorded',
        ]);
    }
}
