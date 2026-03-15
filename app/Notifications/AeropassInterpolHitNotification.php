<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class AeropassInterpolHitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Application $application
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'slack'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref = $this->application->reference_number ?? 'ID ' . $this->application->id;
        return (new MailMessage)
            ->subject('🚨 eVisa: Interpol nominal HIT — immediate action required')
            ->error()
            ->line('An Aeropass Interpol nominal check returned a HIT.')
            ->line('Application: ' . $ref)
            ->line('Risk level has been set to Critical. Supervisor review required.');
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $ref = $this->application->reference_number ?? 'ID ' . $this->application->id;
        return (new SlackMessage)
            ->error()
            ->content('🚨 *Interpol nominal HIT* — immediate supervisor action required')
            ->attachment(function ($attachment) use ($ref) {
                $attachment->title('Application: ' . $ref)
                    ->footer('eVisa platform');
            });
    }
}
