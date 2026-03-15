<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public RefundRequest $refundRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->refundRequest->amount / 100, 2);
        $approver = $this->refundRequest->approver;

        return (new MailMessage)
            ->subject('Refund Request Approved')
            ->greeting('Refund Approved')
            ->line("Your refund request has been approved and processed.")
            ->line('')
            ->line("**Refund Details:**")
            ->line("Amount: GHS {$amount}")
            ->line("Gateway: " . strtoupper($this->refundRequest->gateway))
            ->line("Approved by: {$approver->name}")
            ->line("Reference: {$this->refundRequest->gateway_refund_reference}")
            ->line('')
            ->action('View Refund Details', url("/admin/refunds/{$this->refundRequest->id}"))
            ->line('The refund has been processed through the payment gateway.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_approved',
            'refund_request_id' => $this->refundRequest->id,
            'payment_id' => $this->refundRequest->payment_id,
            'amount' => $this->refundRequest->amount,
            'gateway' => $this->refundRequest->gateway,
            'approved_by' => $this->refundRequest->approved_by,
        ];
    }
}
