<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case SMS = 'sms';
    case Push = 'push';
    case InApp = 'in_app';

    public function label(): string
    {
        return match($this) {
            self::Email => 'Email',
            self::SMS => 'SMS',
            self::Push => 'Push Notification',
            self::InApp => 'In-App Notification',
        };
    }

    public function isRealTime(): bool
    {
        return in_array($this, [self::Push, self::InApp]);
    }

    public function requiresExternalService(): bool
    {
        return in_array($this, [self::Email, self::SMS, self::Push]);
    }
}