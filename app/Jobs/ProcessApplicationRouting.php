<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\ApplicationRoutingService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessApplicationRouting extends BaseJob
{
    use SerializesModels;

    public function __construct(
        public Application $application,
    ) {
        $this->onQueue('default');
    }

    protected function getApplicationId(): ?int
    {
        return $this->application->id;
    }

    protected function getApplication(): ?Application
    {
        return $this->application;
    }

    public function handle(ApplicationRoutingService $routingService): void
    {
        Log::info("Routing application {$this->application->reference_number}");

        $routingService->route($this->application);

        Log::info("Application {$this->application->reference_number} routed to {$this->application->assigned_agency} as {$this->application->tier}");

        SendNotification::dispatch(
            $this->application,
            'new_application_assigned',
            ['agency' => $this->application->assigned_agency?->value ?? $this->application->assigned_agency]
        )->onQueue('default');
    }
}
