<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $details
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'slack'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('eVisa: Database backup failed')
            ->error()
            ->line('The scheduled database backup failed. Immediate action required.')
            ->line('Details: ' . $this->details)
            ->line('Check logs and run backup manually: php artisan backup:database');
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('🚨 *eVisa Database Backup Failed*')
            ->attachment(function ($attachment) {
                $attachment->title('Action required')
                    ->content($this->details)
                    ->footer('Run: php artisan backup:database');
            });
    }
}
