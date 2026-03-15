<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReconciliationFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $gateway = null,
        public ?string $message = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gateway = $this->gateway ?? 'unknown';
        $msg = $this->message ?? 'Payment reconciliation command failed. Check logs and failed_jobs table.';

        return (new MailMessage)
            ->subject('eVisa: Payment reconciliation failed - ' . $gateway)
            ->error()
            ->line('The scheduled payment reconciliation did not complete successfully.')
            ->line('Gateway: ' . $gateway)
            ->line('Details: ' . $msg)
            ->line('Please check storage/logs/reconciliation.log and the failed_jobs table.');
    }
}
