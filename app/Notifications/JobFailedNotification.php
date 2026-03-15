<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class JobFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public object $job,
        public Throwable $exception
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
        $jobClass = is_object($this->job) ? get_class($this->job) : (string) $this->job;
        $appId = method_exists($this->job, 'getApplicationId') ? $this->job->getApplicationId() : null;
        $attempts = method_exists($this->job, 'attempts') ? $this->job->attempts() : '?';

        return (new SlackMessage)
            ->error()
            ->content("Job *{$jobClass}* failed permanently.")
            ->attachment(function ($attachment) use ($jobClass, $appId, $attempts) {
                $attachment->title('Details')
                    ->fields([
                        'Application #' => $appId ?? 'N/A',
                        'Attempts' => (string) $attempts,
                        'Error' => \Illuminate\Support\Str::limit($this->exception->getMessage(), 200),
                    ])
                    ->footer('Check failed_jobs table for full context.');
            });
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jobClass = is_object($this->job) ? get_class($this->job) : (string) $this->job;
        $appId = method_exists($this->job, 'getApplicationId') ? $this->job->getApplicationId() : null;
        $attempts = method_exists($this->job, 'attempts') ? $this->job->attempts() : '?';
        $message = $this->exception->getMessage();

        return (new MailMessage)
            ->subject('eVisa: Job failed permanently - ' . class_basename($jobClass))
            ->error()
            ->line("Job **{$jobClass}** failed permanently for Application #" . ($appId ?? 'N/A') . " after **{$attempts}** attempts.")
            ->line("Error: {$message}")
            ->line('Check the failed_jobs table and logs for full context.');
    }
}
