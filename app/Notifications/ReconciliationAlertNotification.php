<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReconciliationAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Carbon $reconciliationDate,
        public string $gateway,
        public array $stats
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = "Payment Reconciliation Alert - {$this->stats['discrepancies']} Discrepancies Found";
        
        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Payment Reconciliation Alert')
            ->line("Payment reconciliation has detected discrepancies that require your attention.")
            ->line('')
            ->line("**Reconciliation Details:**")
            ->line("Date: {$this->reconciliationDate->format('Y-m-d')}")
            ->line("Gateway: " . strtoupper($this->gateway))
            ->line('')
            ->line("**Summary:**")
            ->line("Total Payments Checked: {$this->stats['total_checked']}")
            ->line("Successfully Matched: {$this->stats['matched']}")
            ->line("**Discrepancies Found: {$this->stats['discrepancies']}**")
            ->line("Processing Errors: {$this->stats['errors']}")
            ->line('')
            ->line('**Action Required:**')
            ->line('Please review the reconciliation issues in the admin panel and take appropriate action.')
            ->line('Critical issues may have resulted in applications being frozen for security.')
            ->action('Review Reconciliation Issues', url('/admin/reconciliation/issues'))
            ->line('')
            ->line('**Discrepancy Types to Look For:**')
            ->line('• **CRITICAL**: Local paid but gateway failed (applications frozen)')
            ->line('• **CRITICAL**: Amount mismatches (applications frozen)')
            ->line('• **HIGH**: Local failed but gateway paid (manual review needed)')
            ->line('• **HIGH**: Reference not found at gateway')
            ->line('• **CRITICAL**: Missing local records')
            ->line('')
            ->line('This is an automated alert from the eVisa payment reconciliation system.');

        // Add urgency styling for high discrepancy counts
        if ($this->stats['discrepancies'] >= 10) {
            $message->line('')
                   ->line('⚠️ **HIGH PRIORITY**: Large number of discrepancies detected!')
                   ->line('Immediate attention required to ensure financial compliance.');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_reconciliation_alert',
            'reconciliation_date' => $this->reconciliationDate->format('Y-m-d'),
            'gateway' => $this->gateway,
            'stats' => $this->stats,
            'severity' => $this->getSeverity(),
            'message' => $this->getShortMessage(),
        ];
    }

    /**
     * Get the severity level based on discrepancy count.
     */
    protected function getSeverity(): string
    {
        $discrepancies = $this->stats['discrepancies'];
        
        if ($discrepancies >= 20) {
            return 'critical';
        } elseif ($discrepancies >= 10) {
            return 'high';
        } elseif ($discrepancies >= 5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get a short message for the notification.
     */
    protected function getShortMessage(): string
    {
        $discrepancies = $this->stats['discrepancies'];
        $gateway = strtoupper($this->gateway);
        $date = $this->reconciliationDate->format('M j, Y');
        
        return "Payment reconciliation found {$discrepancies} discrepancies for {$gateway} on {$date}. Review required.";
    }
}