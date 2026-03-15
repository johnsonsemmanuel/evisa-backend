<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\RefundProcessedNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyFinanceOfficerRefund extends CriticalJob
{
    use SerializesModels;

    public function __construct(
        public Payment $refundPayment,
        public Payment $originalPayment
    ) {
        $this->onQueue('critical');
    }

    protected function getApplicationId(): ?int
    {
        return $this->originalPayment->application?->id;
    }

    public function handle(): void
    {
        $financeOfficers = User::whereHas('roles', function ($query) {
            $query->where('name', 'finance_officer');
        })->get();

        if ($financeOfficers->isEmpty()) {
            Log::warning('No finance officers found to notify about refund', [
                'refund_payment_id' => $this->refundPayment->id,
                'original_payment_id' => $this->originalPayment->id,
            ]);
            return;
        }

        Notification::send(
            $financeOfficers,
            new RefundProcessedNotification($this->refundPayment, $this->originalPayment)
        );

        Log::info('Finance officers notified about refund', [
            'refund_payment_id' => $this->refundPayment->id,
            'original_payment_id' => $this->originalPayment->id,
            'notified_count' => $financeOfficers->count(),
        ]);
    }
}
