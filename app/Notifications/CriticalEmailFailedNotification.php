<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalEmailFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $referenceNumber,
        public string $mailableClass
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Critical eVisa email failed — Ref: {$this->referenceNumber}")
            ->error()
            ->line("An important email could not be delivered to the applicant (Ref: {$this->referenceNumber}).")
            ->line('Mailable: ' . $this->mailableClass)
            ->line('Please contact the applicant by phone to convey the decision or instructions.')
            ->line('A record has been created in Failed Email Notifications for follow-up.');
    }
}
