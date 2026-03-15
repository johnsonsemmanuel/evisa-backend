<?php

namespace App\Enums;

enum KycStatus: string
{
    case NotStarted = 'not_started';
    case PendingDocuments = 'pending_documents';
    case UnderReview = 'under_review';
    case OnHold = 'on_hold';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::PendingDocuments => 'Pending Documents',
            self::UnderReview => 'Under Review',
            self::OnHold => 'On Hold',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }

    public function allowsPayment(): bool
    {
        return $this === self::Approved;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Expired], true);
    }
}
