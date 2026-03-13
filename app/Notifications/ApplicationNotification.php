<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Application $application,
        public string $type,
        public string $message,
        public array $data = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('GH-eVISA: ' . $this->getSubject())
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->message)
            ->line("Application Reference: {$this->application->reference_number}")
            ->action('View Application', url('/applications/' . $this->application->id))
            ->line('Thank you for using the GH-eVISA platform.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id'   => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'type'             => $this->type,
            'message'          => $this->message,
            'data'             => $this->data,
        ];
    }

    private function getSubject(): string
    {
        return match ($this->type) {
            'application_submitted'       => 'Application Submitted',
            'application_approved'        => 'Visa Approved',
            'application_denied'          => 'Application Decision',
            'status_changed'              => 'Status Update',
            'sla_warning'                 => 'SLA Warning',
            'sla_breached'                => 'SLA Breach Alert',
            'document_reupload_required'  => 'Document Re-upload Required',
            'new_application_assigned'    => 'New Case Assigned',
            default                       => 'Application Update',
        };
    }
}
