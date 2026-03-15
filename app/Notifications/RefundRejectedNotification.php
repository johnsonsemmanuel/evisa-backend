<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundRejectedNotification extends Notification implements ShouldQueue
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
        $rejector = $this->refundRequest->rejector;

        return (new MailMessage)
            ->subject('Refund Request Rejected')
            ->greeting('Refund Rejected')
            ->line("Your refund request has been rejected.")
            ->line('')
            ->line("**Refund Details:**")
            ->line("Amount: GHS {$amount}")
            ->line("Gateway: " . strtoupper($this->refundRequest->gateway))
            ->line("Rejected by: {$rejector->name}")
            ->line('')
            ->line("**Rejection Reason:**")
            ->line($this->refundRequest->rejection_reason)
            ->line('')
            ->action('View Refund Details', url("/admin/refunds/{$this->refundRequest->id}"))
            ->line('If you believe this rejection was in error, please contact the finance team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_rejected',
            'refund_request_id' => $this->refundRequest->id,
            'payment_id' => $this->refundRequest->payment_id,
            'amount' => $this->refundRequest->amount,
            'gateway' => $this->refundRequest->gateway,
            'rejected_by' => $this->refundRequest->rejected_by,
            'rejection_reason' => $this->refundRequest->rejection_reason,
        ];
    }
}
