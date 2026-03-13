<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardMetricsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $agency,
        public array $metrics,
        public ?int $missionId = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Broadcast to agency-specific channel
        $channels[] = new PrivateChannel("dashboard.{$this->agency}");
        
        // For MFA, also broadcast to mission-specific channel if applicable
        if ($this->agency === 'mfa' && $this->missionId) {
            $channels[] = new PrivateChannel("dashboard.mfa.mission.{$this->missionId}");
        }
        
        // Admin gets all updates
        $channels[] = new PrivateChannel('dashboard.admin');
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'agency' => $this->agency,
            'metrics' => $this->metrics,
            'mission_id' => $this->missionId,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'metrics.updated';
    }
}