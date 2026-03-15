<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class AeropassCheckFailedNotification extends Notification implements ShouldQueue
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
            ->subject('eVisa: Aeropass/Interpol check failed — manual review required')
            ->error()
            ->line('The Aeropass nominal check for this application could not be completed after retries.')
            ->line('Application: ' . $ref)
            ->line('Please perform manual Interpol verification and update the application status.');
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $ref = $this->application->reference_number ?? 'ID ' . $this->application->id;
        return (new SlackMessage)
            ->error()
            ->content('⚠️ *Aeropass check failed* — manual review required')
            ->attachment(function ($attachment) use ($ref) {
                $attachment->title('Application: ' . $ref)
                    ->footer('eVisa platform');
            });
    }
}
