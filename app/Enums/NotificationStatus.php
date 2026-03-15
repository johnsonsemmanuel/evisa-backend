<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Bounced = 'bounced';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Bounced => 'Bounced',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Sent => 'blue',
            self::Delivered => 'green',
            self::Failed => 'red',
            self::Bounced => 'orange',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Sent, self::Delivered]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::Bounced]);
    }
}