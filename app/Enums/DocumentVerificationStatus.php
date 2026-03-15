<?php

namespace App\Enums;

enum DocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ReuploadRequested = 'reupload_requested';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending Review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::ReuploadRequested => 'Reupload Requested',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Accepted => 'green',
            self::Rejected => 'red',
            self::ReuploadRequested => 'orange',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected]);
    }

    public function canTransitionTo(self $new): bool
    {
        return in_array($new, $this->allowedTransitions());
    }

    private function allowedTransitions(): array
    {
        return match($this) {
            self::Pending => [
                self::Accepted,
                self::Rejected,
                self::ReuploadRequested
            ],
            self::ReuploadRequested => [
                self::Pending,
                self::Accepted,
                self::Rejected
            ],
            // Final states
            self::Accepted,
            self::Rejected => [],
        };
    }
}