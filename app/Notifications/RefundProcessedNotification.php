<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $refundPayment,
        public Payment $originalPayment
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
        $application = $this->originalPayment->application;
        $refundAmount = $this->refundPayment->amount / 100; // Convert from pesewas
        
        return (new MailMessage)
            ->subject('Refund Processed - eVisa Application')
            ->line('A refund has been processed for an eVisa application.')
            ->line("Application: {$application->reference_number}")
            ->line("Original Payment: {$this->originalPayment->transaction_reference}")
            ->line("Refund Amount: {$this->refundPayment->currency} {$refundAmount}")
            ->line("Refund Reference: {$this->refundPayment->transaction_reference}")
            ->action('View Application', url("/admin/applications/{$application->id}"))
            ->line('Please review this refund in the admin panel.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_processed',
            'refund_payment_id' => $this->refundPayment->id,
            'original_payment_id' => $this->originalPayment->id,
            'application_id' => $this->originalPayment->application_id,
            'refund_amount' => $this->refundPayment->amount,
            'currency' => $this->refundPayment->currency,
            'refund_reference' => $this->refundPayment->transaction_reference,
            'original_reference' => $this->originalPayment->transaction_reference,
        ];
    }
}