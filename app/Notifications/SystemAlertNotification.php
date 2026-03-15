<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $severity,
        public string $event,
        public string $details,
        public ?string $actionRequired = null,
        public ?string $logPath = null,
        public ?array $extra = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $time = now()->utc()->format('Y-m-d H:i:s') . ' UTC';

        $text = "🚨 *eVisa Platform Alert*\n";
        $text .= "*Severity*: {$this->severity}\n";
        $text .= "*Event*: {$this->event}\n";
        $text .= "*Environment*: " . (config('app.env') ?? 'production') . "\n";
        $text .= "*Time*: {$time}\n";
        $text .= "*Details*: {$this->details}\n";
        if ($this->actionRequired) {
            $text .= "*Action Required*: {$this->actionRequired}\n";
        }
        if ($this->logPath) {
            $text .= "*Logs*: `{$this->logPath}`\n";
        }

        $message = (new SlackMessage)
            ->error()
            ->content($text);

        if (!empty($this->extra)) {
            $message->attachment(function ($attachment) {
                $attachment->fields($this->extra);
            });
        }

        return $message;
    }
}
