<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\InteractsWithSockets;
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
        public ?string $notes = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('application.' . $this->application->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'application.status.changed';
    }

    public function broadcastWith(): array
    {
        $status = $this->application->status;
        $newStatusValue = $status && method_exists($status, 'value') ? $status->value : (string) $status;
        $newStatusLabel = $status && method_exists($status, 'label') ? $status->label() : $newStatusValue;

        return [
            'application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'new_status' => $newStatusValue,
            'new_status_label' => $newStatusLabel,
            'updated_at' => $this->application->updated_at?->toISOString() ?? now()->toISOString(),
            'message' => $this->notes ?? "Status updated to {$newStatusLabel}",
        ];
    }

    public function broadcastQueue(): string
    {
        return 'default';
    }
}