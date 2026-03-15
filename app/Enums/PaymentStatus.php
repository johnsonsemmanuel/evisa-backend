<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Initiated = 'initiated';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case PendingVerification = 'pending_verification';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Suspicious = 'suspicious';

    public function label(): string
    {
        return match($this) {
            self::Initiated => 'Initiated',
            self::Processing => 'Processing',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
            self::PendingVerification => 'Pending Verification',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::Suspicious => 'Suspicious',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Initiated => 'blue',
            self::Processing => 'yellow',
            self::Paid => 'green',
            self::Failed => 'red',
            self::Expired => 'gray',
            self::PendingVerification => 'orange',
            self::Cancelled => 'gray',
            self::Refunded => 'purple',
            self::Suspicious => 'red',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Paid;
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Failed,
            self::Expired,
            self::Cancelled,
            self::Refunded
        ]);
    }

    public function canTransitionTo(self $new): bool
    {
        return in_array($new, $this->allowedTransitions());
    }

    private function allowedTransitions(): array
    {
        return match($this) {
            self::Initiated => [
                self::Processing,
                self::Failed,
                self::Cancelled,
                self::Expired
            ],
            self::Processing => [
                self::Paid,
                self::Failed,
                self::PendingVerification,
                self::Suspicious
            ],
            self::PendingVerification => [
                self::Paid,
                self::Failed,
                self::Suspicious
            ],
            self::Paid => [
                self::Refunded
            ],
            self::Suspicious => [
                self::Paid,
                self::Failed,
                self::Cancelled
            ],
            // Final states
            self::Failed,
            self::Expired,
            self::Cancelled,
            self::Refunded => [],
        };
    }
}