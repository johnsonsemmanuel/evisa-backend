<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingDocuments = 'pending_documents';
    case PendingPayment = 'pending_payment';
    case PaymentConfirmed = 'payment_confirmed';
    case AeropassPending = 'aeropass_pending';
    case ApprovedPending = 'approved_pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case VisaIssued = 'visa_issued';
    case Expired = 'expired';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::PendingDocuments => 'Pending Documents',
            self::PendingPayment => 'Pending Payment',
            self::PaymentConfirmed => 'Payment Confirmed',
            self::AeropassPending => 'Aeropass Pending',
            self::ApprovedPending => 'Approved Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
            self::VisaIssued => 'Visa Issued',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'gray',
            self::Submitted => 'blue',
            self::UnderReview => 'yellow',
            self::PendingDocuments => 'orange',
            self::PendingPayment => 'purple',
            self::PaymentConfirmed => 'indigo',
            self::AeropassPending => 'cyan',
            self::ApprovedPending => 'lime',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'gray',
            self::VisaIssued => 'emerald',
            self::Expired => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Approved,
            self::Rejected,
            self::Withdrawn,
            self::VisaIssued,
            self::Expired,
        ]);
    }

    public function canTransitionTo(self $new): bool
    {
        return in_array($new, $this->allowedTransitions());
    }

    private function allowedTransitions(): array
    {
        return match($this) {
            self::Draft => [
                self::Submitted,
                self::Withdrawn
            ],
            self::Submitted => [
                self::UnderReview,
                self::PendingDocuments,
                self::PendingPayment,
                self::Withdrawn
            ],
            self::UnderReview => [
                self::PendingPayment,
                self::PendingDocuments,
                self::AeropassPending,
                self::Rejected,
                self::ApprovedPending
            ],
            self::PendingDocuments => [
                self::UnderReview,
                self::Rejected,
                self::Withdrawn
            ],
            self::PendingPayment => [
                self::PaymentConfirmed,
                self::Rejected,
                self::Withdrawn,
                self::Expired,
            ],
            self::PaymentConfirmed => [
                self::AeropassPending,
                self::ApprovedPending
            ],
            self::AeropassPending => [
                self::ApprovedPending,
                self::Rejected
            ],
            self::ApprovedPending => [
                self::Approved,
                self::Rejected
            ],
            self::Approved => [
                self::VisaIssued
            ],
            // Terminal states
            self::Rejected,
            self::Withdrawn,
            self::VisaIssued,
            self::Expired => [],
        };
    }
}