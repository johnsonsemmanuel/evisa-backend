<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class FailedJobsDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $count,
        public array $jobTypes,
        public int $minutes
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];
        if (config('logging.channels.slack.url') || env('LOG_SLACK_WEBHOOK_URL')) {
            $channels[] = 'slack';
        }
        $adminEmail = config('security.monitoring.alert_email') ?: config('mail.from.address');
        if ($adminEmail) {
            $channels[] = 'mail';
        }
        return $channels ?: ['mail'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $lines = [];
        foreach ($this->jobTypes as $name => $c) {
            $lines[] = '• ' . class_basename($name) . ': ' . $c;
        }

        return (new SlackMessage)
            ->warning()
            ->content("Failed jobs digest: *{$this->count}* failure(s) in the last *{$this->minutes}* minutes.")
            ->attachment(function ($attachment) use ($lines) {
                $attachment->title('Job types')
                    ->text(implode("\n", $lines))
                    ->footer('Check failed_jobs table. Run: php artisan queue:monitor-failed-jobs');
            });
    }

    public function toMail(object $notifiable): MailMessage
    {
        $summary = collect($this->jobTypes)->map(fn ($c, $name) => class_basename($name) . ': ' . $c)->implode(', ');

        return (new MailMessage)
            ->subject("eVisa: {$this->count} failed job(s) in the last {$this->minutes} minutes")
            ->error()
            ->line("There were **{$this->count}** failed queue job(s) in the last **{$this->minutes}** minutes.")
            ->line('Job types: ' . $summary)
            ->line('Check the failed_jobs table and logs for details.')
            ->line('Command: php artisan queue:monitor-failed-jobs');
    }
}
