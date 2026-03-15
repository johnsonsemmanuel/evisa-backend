<?php

namespace App\Enums;

enum SumsubVerificationStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Queued = 'queued';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match($this) {
            self::NotRequired => 'Not Required',
            self::Pending => 'Pending Verification',
            self::Queued => 'Queued for Review',
            self::Completed => 'Verification Completed',
            self::Failed => 'Verification Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NotRequired => 'gray',
            self::Pending => 'blue',
            self::Queued => 'yellow',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::NotRequired, self::Completed, self::Failed]);
    }
}