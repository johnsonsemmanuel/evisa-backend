<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $email,
        private string $ipAddress,
        private int $attempts,
        private bool $isAdminNotification = false
    ) {}

    /**
     * Get the notification's delivery channels.
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
        if ($this->isAdminNotification) {
            return $this->adminNotification();
        }

        return $this->userNotification();
    }

    /**
     * Notification to the account owner
     */
    private function userNotification(): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Security Alert - Account Locked')
            ->greeting('Security Alert')
            ->line('Your account has been temporarily locked due to multiple failed login attempts.')
            ->line('**Account Details:**')
            ->line('• Email: ' . $this->email)
            ->line('• Failed attempts: ' . $this->attempts)
            ->line('• Last attempt from IP: ' . $this->ipAddress)
            ->line('• Time: ' . now()->format('Y-m-d H:i:s T'))
            ->line('')
            ->line('**What happened?**')
            ->line('Someone attempted to log into your account ' . $this->attempts . ' times with incorrect credentials.')
            ->line('')
            ->line('**What should you do?**')
            ->line('• If this was you, please contact system administrators to unlock your account')
            ->line('• If this was not you, your account may be under attack - please contact support immediately')
            ->line('• Consider changing your password once your account is unlocked')
            ->line('')
            ->line('**To unlock your account:**')
            ->line('Contact your system administrator or support team. Only administrators can unlock accounts after this security lockout.')
            ->line('')
            ->line('This is an automated security measure to protect your account.')
            ->salutation('Ghana Immigration Service - eVisa System');
    }

    /**
     * Notification to administrators
     */
    private function adminNotification(): MailMessage
    {
        return (new MailMessage)
            ->subject('SECURITY ALERT: Account Permanently Locked')
            ->greeting('Administrator Security Alert')
            ->line('An account has been permanently locked due to excessive failed login attempts.')
            ->line('')
            ->line('**Incident Details:**')
            ->line('• Account: ' . $this->email)
            ->line('• Failed attempts: ' . $this->attempts)
            ->line('• Source IP: ' . $this->ipAddress)
            ->line('• Locked at: ' . now()->format('Y-m-d H:i:s T'))
            ->line('')
            ->line('**Recommended Actions:**')
            ->line('1. Investigate the source IP address for suspicious activity')
            ->line('2. Contact the account owner to verify if they were attempting to log in')
            ->line('3. Consider blocking the IP address if this appears to be an attack')
            ->line('4. Review security logs for related incidents')
            ->line('')
            ->line('**To unlock the account:**')
            ->line('Use the admin panel or API endpoint: POST /api/admin/users/{user}/unlock-account')
            ->line('')
            ->line('This lockout was triggered by the progressive brute force protection system.')
            ->action('View Admin Dashboard', url('/dashboard/admin'))
            ->salutation('Ghana Immigration Service - eVisa Security System');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_locked',
            'email' => $this->email,
            'ip_address' => $this->ipAddress,
            'attempts' => $this->attempts,
            'locked_at' => now()->toISOString(),
            'is_admin_notification' => $this->isAdminNotification,
        ];
    }
}