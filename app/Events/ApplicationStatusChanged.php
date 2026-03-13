<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Application $application,
        public string $previousStatus,
        public string $newStatus,
        public ?string $notes = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Broadcast to agency-specific channels
        if ($this->application->assigned_agency) {
            $channels[] = new PrivateChannel("dashboard.{$this->application->assigned_agency}");
            
            // For MFA, also broadcast to mission-specific channel
            if ($this->application->assigned_agency === 'mfa' && $this->application->owner_mission_id) {
                $channels[] = new PrivateChannel("dashboard.mfa.mission.{$this->application->owner_mission_id}");
            }
        }
        
        // Admin gets all updates
        $channels[] = new PrivateChannel('dashboard.admin');
        
        // Applicant gets their own updates
        $channels[] = new PrivateChannel("user.{$this->application->user_id}");
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'agency' => $this->application->assigned_agency,
            'mission_id' => $this->application->owner_mission_id,
            'tier' => $this->application->tier,
            'notes' => $this->notes,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'application.status.changed';
    }
}