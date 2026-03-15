<?php

namespace App\Enums;

enum SumsubReviewResult: string
{
    case Init = 'init';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Init => 'Initialized',
            self::Pending => 'Pending Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Init => 'gray',
            self::Pending => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected]);
    }
}