<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundInitiatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public RefundRequest $refundRequest
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->refundRequest->amount / 100, 2);
        $initiator = $this->refundRequest->initiator;

        return (new MailMessage)
            ->subject('Refund Request Awaiting Your Approval')
            ->greeting('Refund Approval Required')
            ->line("A refund request has been initiated and requires your approval.")
            ->line('')
            ->line("**Refund Details:**")
            ->line("Amount: GHS {$amount}")
            ->line("Gateway: " . strtoupper($this->refundRequest->gateway))
            ->line("Initiated by: {$initiator->name}")
            ->line("Reason: {$this->refundRequest->reason}")
            ->line('')
            ->line("**Why dual approval?**")
            ->line("This refund exceeds GHS 500 and requires approval from a second finance officer for security and audit compliance.")
            ->line('')
            ->action('Review Refund Request', url("/admin/refunds/{$this->refundRequest->id}"))
            ->line('Please review and approve or reject this refund request.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_initiated',
            'refund_request_id' => $this->refundRequest->id,
            'payment_id' => $this->refundRequest->payment_id,
            'amount' => $this->refundRequest->amount,
            'gateway' => $this->refundRequest->gateway,
            'initiated_by' => $this->refundRequest->initiated_by,
            'message' => "Refund request for GHS " . number_format($this->refundRequest->amount / 100, 2) . " awaiting your approval",
        ];
    }
}
