<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfficerAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Application $application,
        public User $officer
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('officer.' . $this->officer->id),
            new PrivateChannel('application.' . $this->application->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'officer.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->application->id,
            'officer_id' => $this->officer->id,
            'assigned_at' => $this->application->updated_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'default';
    }
}
