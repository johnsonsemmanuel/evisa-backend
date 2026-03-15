<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Mail\Mailables\Content;

class PaymentConfirmedMail extends BaseApplicantMailable
{
    public function __construct(
        public Payment $payment
    ) {}

    protected function getApplication(): ?Application
    {
        return $this->payment->application;
    }

    protected function subjectLine(): string
    {
        $ref = $this->payment->application?->reference_number ?? $this->payment->id;
        return "Payment Confirmed — Ghana eVisa Ref: {$ref}";
    }

    public function content(): Content
    {
        $application = $this->payment->application;
        $amountMajor = number_format($this->payment->amount / 100, 2);
        $currency = $this->payment->currency ?? 'GHS';
        $paidAt = $this->payment->paid_at?->format('d F Y, H:i') ?? now()->format('d F Y, H:i');

        return new Content(
            view: 'emails.payment-confirmed',
            text: 'emails.payment-confirmed-text',
            with: [
                'applicant_name' => $application ? trim($application->first_name . ' ' . $application->last_name) : 'Applicant',
                'reference_number' => $application?->reference_number,
                'payment_reference' => $this->payment->gateway_reference ?? $this->payment->transaction_reference,
                'amount_formatted' => "{$currency} {$amountMajor}",
                'paid_at' => $paidAt,
            ]
        );
    }
}
