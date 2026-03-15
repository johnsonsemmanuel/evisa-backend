<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Payment $payment
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('application.' . $this->payment->application_id),
        ];
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'payment.confirmed';
    }

    public function broadcastWith(): array
    {
        $amount = (int) $this->payment->amount;
        $major = (int) floor($amount / 100);
        $minor = $amount % 100;
        $currency = $this->payment->currency ?? 'GHS';
        $amountFormatted = $currency . ' ' . number_format($major) . '.' . str_pad((string) $minor, 2, '0', STR_PAD_LEFT);

        return [
            'application_id' => $this->payment->application_id,
            'payment_id' => $this->payment->id,
            'amount_formatted' => $amountFormatted,
            'paid_at' => $this->payment->paid_at?->toISOString() ?? now()->toISOString(),
            'next_step' => 'Your application is now under review.',
        ];
    }

    public function broadcastQueue(): string
    {
        return 'default';
    }
}
