<?php

namespace App\Enums;

enum EtaStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Expired = 'expired';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending Review',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Approved => 'green',
            self::Denied => 'red',
            self::Expired => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Denied, self::Expired]);
    }

    public function canTransitionTo(self $new): bool
    {
        return in_array($new, $this->allowedTransitions());
    }

    private function allowedTransitions(): array
    {
        return match($this) {
            self::Pending => [
                self::Approved,
                self::Denied
            ],
            self::Approved => [
                self::Expired
            ],
            // Final states
            self::Denied,
            self::Expired => [],
        };
    }
}