<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuspensionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Carbon $suspendedUntil
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Access Temporarily Suspended')
            ->greeting('Payment Access Suspended')
            ->line('Your payment access has been temporarily suspended due to multiple failed payment attempts.')
            ->line('')
            ->line('**Suspension Details:**')
            ->line('Suspended until: ' . $this->suspendedUntil->format('M j, Y \a\t g:i A T'))
            ->line('Reason: Multiple failed payment attempts detected')
            ->line('')
            ->line('**What this means:**')
            ->line('• You cannot initiate new payments until the suspension is lifted')
            ->line('• Existing applications are not affected')
            ->line('• This is a security measure to protect your account')
            ->line('')
            ->line('**If you believe this is an error:**')
            ->line('Please contact our support team immediately with your account details.')
            ->action('Contact Support', url('/support'))
            ->line('We apologize for any inconvenience caused.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_suspension',
            'suspended_until' => $this->suspendedUntil->toISOString(),
            'message' => 'Payment access suspended until ' . $this->suspendedUntil->format('M j, Y \a\t g:i A'),
        ];
    }
}