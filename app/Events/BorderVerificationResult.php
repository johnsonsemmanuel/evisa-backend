<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BorderVerificationResult implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Application $application,
        public string $verificationStatus,
        public string $portOfEntry,
        public ?int $stationId = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('application.' . $this->application->id),
        ];
        if ($this->stationId !== null) {
            $channels[] = new PresenceChannel('border-station.' . $this->stationId);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'border.verification.result';
    }

    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->application->id,
            'verification_status' => $this->verificationStatus,
            'port_of_entry' => $this->portOfEntry,
            'verified_at' => now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'default';
    }
}
