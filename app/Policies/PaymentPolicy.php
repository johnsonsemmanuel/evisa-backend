<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine if the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        // Applicants can view their own payments
        // Finance officers and admins can view all payments
        return in_array($user->role, [
            'applicant',
            'finance_officer',
            'admin',
            'gis_admin',
            'mfa_admin',
        ]);
    }

    /**
     * Determine if the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Applicants can only view their own payments
        if ($user->isApplicant()) {
            return $payment->user_id === $user->id;
        }

        // Finance officers and admins can view all payments
        return in_array($user->role, [
            'finance_officer',
            'admin',
            'gis_admin',
            'mfa_admin',
        ]);
    }

    /**
     * Determine if the user can create payments.
     */
    public function create(User $user): bool
    {
        // Only applicants can initiate payments for their applications
        return $user->isApplicant();
    }

    /**
     * Determine if the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Only finance officers and admins can update payment records
        // (e.g., confirming bank transfers)
        return in_array($user->role, [
            'finance_officer',
            'admin',
        ]);
    }

    /**
     * Determine if the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Only admins can delete payment records
        return $user->isAdmin();
    }

    /**
     * Determine if the user can confirm a bank transfer payment.
     */
    public function confirmBankTransfer(User $user, Payment $payment): bool
    {
        // Only finance officers and admins can confirm bank transfers
        return in_array($user->role, [
            'finance_officer',
            'admin',
        ]) && $payment->payment_provider === 'bank_transfer' 
           && $payment->status === 'pending';
    }

    /**
     * Determine if the user can refund the payment.
     */
    public function refund(User $user, Payment $payment): bool
    {
        // Only finance officers and admins can process refunds
        return in_array($user->role, [
            'finance_officer',
            'admin',
        ]) && $payment->status === 'paid';
    }

    /**
     * Determine if the user can view payment statistics.
     */
    public function viewStatistics(User $user): bool
    {
        // Finance officers and admins can view payment statistics
        return in_array($user->role, [
            'finance_officer',
            'admin',
            'gis_admin',
            'mfa_admin',
        ]);
    }
}
