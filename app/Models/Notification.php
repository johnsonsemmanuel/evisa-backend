<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    /**
     * Get notification icon based on type.
     */
    public function getIcon(): string
    {
        return match($this->type) {
            'App\\Notifications\\ApplicationStatusChanged' => 'file-text',
            'application_submitted' => 'file-text',
            'application_approved' => 'check-circle',
            'application_denied' => 'x-circle',
            'status_changed' => 'clock',
            'document_reupload_required' => 'alert-triangle',
            'payment_received' => 'credit-card',
            'evisa_ready' => 'download',
            default => 'bell',
        };
    }

    /**
     * Get notification color based on type.
     */
    public function getColor(): string
    {
        return match($this->type) {
            'App\\Notifications\\ApplicationStatusChanged' => 'info',
            'application_submitted' => 'info',
            'application_approved' => 'success',
            'application_denied' => 'danger',
            'status_changed' => 'warning',
            'document_reupload_required' => 'danger',
            'payment_received' => 'success',
            'evisa_ready' => 'success',
            default => 'muted',
        };
    }

    /**
     * Get the application reference number from notification data.
     */
    public function getReferenceNumber(): string
    {
        $data = $this->data;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return $data['reference_number'] ?? 'N/A';
    }

    /**
     * Get the notification message from data.
     */
    public function getMessage(): string
    {
        $data = $this->data;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return $data['message'] ?? 'Notification';
    }

    /**
     * Get the notification type for display.
     */
    public function getDisplayType(): string
    {
        $data = $this->data;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        return $data['type'] ?? 'status_changed';
    }
}
