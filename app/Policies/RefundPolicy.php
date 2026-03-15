<?php

namespace App\Policies;

use App\Models\RefundRequest;
use App\Models\User;

class RefundPolicy
{
    /**
     * Determine if the user can initiate refunds.
     */
    public function initiate(User $user): bool
    {
        return $user->hasAnyRole(['finance_officer', 'admin']);
    }

    /**
     * Determine if the user can approve a refund.
     * User cannot approve their own refund request.
     */
    public function approve(User $user, RefundRequest $refundRequest): bool
    {
        // Must have finance_officer or admin role
        if (!$user->hasAnyRole(['finance_officer', 'admin'])) {
            return false;
        }

        // Cannot approve own request
        if ($refundRequest->initiated_by === $user->id) {
            return false;
        }

        // Can only approve pending refunds
        if (!$refundRequest->isPending()) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can reject a refund.
     */
    public function reject(User $user, RefundRequest $refundRequest): bool
    {
        // Must have finance_officer or admin role
        if (!$user->hasAnyRole(['finance_officer', 'admin'])) {
            return false;
        }

        // Can only reject pending refunds
        if (!$refundRequest->isPending()) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can view a refund.
     */
    public function view(User $user, RefundRequest $refundRequest): bool
    {
        return $user->hasAnyRole(['finance_officer', 'admin']);
    }

    /**
     * Determine if the user can view any refunds.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['finance_officer', 'admin']);
    }
}
