<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\ApplicationRoutingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessApplicationRouting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Application $application,
    ) {
        $this->onQueue('routing');
    }

    public function handle(ApplicationRoutingService $routingService): void
    {
        Log::info("Routing application {$this->application->reference_number}");

        $routingService->route($this->application);

        Log::info("Application {$this->application->reference_number} routed to {$this->application->assigned_agency} as {$this->application->tier}");

        // Dispatch notification to the assigned agency
        SendNotification::dispatch(
            $this->application,
            'new_application_assigned',
            ['agency' => $this->application->assigned_agency]
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to route application {$this->application->reference_number}: {$exception->getMessage()}");
    }
}
