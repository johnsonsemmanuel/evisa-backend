<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GcbAvailabilityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public bool $available,
        public string $timestamp
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->available) {
            return (new MailMessage)
                ->subject('eVisa: GCB payment gateway is available again')
                ->line('GCB payment gateway has recovered and is now available.')
                ->line('Time (UTC): ' . $this->timestamp)
                ->line('Payments will again prefer GCB (government mandate) with Paystack as fallback.');
        }

        return (new MailMessage)
            ->subject('eVisa: GCB payment gateway unavailable')
            ->error()
            ->line('GCB payment gateway is currently unavailable.')
            ->line('Time (UTC): ' . $this->timestamp)
            ->line('Payments will use Paystack fallback until GCB recovers. Monitor SLA and contact GCB if prolonged.');
    }
}
