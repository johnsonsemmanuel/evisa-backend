<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Only admin roles should see payment data
        $channels[] = new PrivateChannel('dashboard.admin');
        $channels[] = new PrivateChannel('payments.admin');
        
        // Applicant gets their own payment updates
        if ($this->payment->application) {
            $channels[] = new PrivateChannel("user.{$this->payment->application->user_id}");
        }
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'application_id' => $this->payment->application_id,
            'reference_number' => $this->payment->application?->reference_number,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'method' => $this->payment->method,
            'status' => $this->payment->status,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.completed';
    }
}