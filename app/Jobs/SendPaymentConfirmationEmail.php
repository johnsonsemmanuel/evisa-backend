<?php

namespace App\Jobs;

use App\Mail\PaymentConfirmationMail;
use App\Models\Payment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentConfirmationEmail extends CriticalJob
{
    use SerializesModels;

    public function __construct(
        public Payment $payment
    ) {
        $this->onQueue('critical');
    }

    protected function getApplicationId(): ?int
    {
        return $this->payment->application?->id;
    }

    public function handle(): void
    {
        $application = $this->payment->application;

        if (!$application || !$application->email) {
            Log::warning('Cannot send payment confirmation email - missing application or email', [
                'payment_id' => $this->payment->id,
            ]);
            return;
        }

        Mail::to($application->email)->send(new PaymentConfirmationMail($this->payment));

        Log::info('Payment confirmation email sent', [
            'payment_id' => $this->payment->id,
            'application_id' => $application->id,
            'email' => $application->email,
        ]);
    }
}
