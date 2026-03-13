<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Application $application,
        public string $type,
        public string $message
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(User $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'type' => $this->type,
            'message' => $this->message,
            'status' => $this->application->status,
        ];
    }
}
