<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public array $rejectionLabels
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = implode(', ', $this->rejectionLabels) ?: 'Verification could not be completed';
        return (new MailMessage)
            ->subject('Identity verification result')
            ->line('Your identity verification was not approved.')
            ->line('Reason: ' . $labels)
            ->line('You may re-submit documents or contact support for assistance.');
    }
}
