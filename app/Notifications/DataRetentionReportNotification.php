<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataRetentionReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $stats;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $stats)
    {
        $this->stats = $stats;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->stats['anonymized'] + $this->stats['deleted'];
        
        return (new MailMessage)
            ->subject('Data Retention Policy Applied - ' . now()->format('Y-m-d'))
            ->greeting('Data Retention Policy Report')
            ->line('The automated data retention policy has been applied to the eVisa system.')
            ->line('**Summary:**')
            ->line("• Records anonymized: {$this->stats['anonymized']}")
            ->line("• Records deleted: {$this->stats['deleted']}")
            ->line("• Total processed: {$total}")
            ->line("• Errors: {$this->stats['errors']}")
            ->when($this->stats['errors'] > 0, function ($message) {
                return $message->line('⚠️ Please check the system logs for error details.');
            })
            ->line('This action was performed automatically in compliance with Ghana Data Protection Commission requirements.')
            ->line('All payment records and audit logs have been preserved as required by financial regulations.')
            ->salutation('Ghana Immigration Service eVisa System');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'data_retention_report',
            'stats' => $this->stats,
            'applied_at' => now(),
        ];
    }
}