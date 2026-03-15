<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public string $action,
        public array $details
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = User::find($this->userId);
        $userName = $user ? $user->name : "User ID {$this->userId}";
        $userEmail = $user ? $user->email : "Unknown";

        $subject = $this->action === 'suspended' 
            ? 'Payment Access Suspended - Security Alert'
            : 'Payment Account Flagged - Security Alert';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Payment Security Alert')
            ->line("A payment security event has occurred that requires your attention.")
            ->line('')
            ->line("**User Details:**")
            ->line("Name: {$userName}")
            ->line("Email: {$userEmail}")
            ->line("User ID: {$this->userId}")
            ->line('')
            ->line("**Security Event:**")
            ->line("Action: " . ucfirst($this->action))
            ->line("Reason: {$this->details['reason']}")
            ->line("IP Address: {$this->details['ip']}")
            ->line("Time: " . now()->format('M j, Y \a\t g:i A T'));

        if ($this->action === 'suspended') {
            $message->line("Suspended until: {$this->details['suspended_until']}")
                   ->line('')
                   ->line("**Immediate Actions Required:**")
                   ->line("• Review user's payment history for suspicious activity")
                   ->line("• Contact user to verify legitimate payment attempts")
                   ->line("• Consider manual review of account security");
        } else {
            $message->line('')
                   ->line("**Recommended Actions:**")
                   ->line("• Monitor user's payment activity closely")
                   ->line("• Review recent payment attempts for patterns")
                   ->line("• Consider contacting user to verify activity");
        }

        return $message->action('Review User Account', url("/admin/users/{$this->userId}"))
                      ->line('This is an automated security alert from the eVisa payment system.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_security_alert',
            'user_id' => $this->userId,
            'action' => $this->action,
            'details' => $this->details,
            'message' => "Payment security alert: User {$this->userId} {$this->action} - {$this->details['reason']}",
        ];
    }
}