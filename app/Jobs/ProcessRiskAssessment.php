<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\RiskScoringService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued risk scoring calculation for an application.
 * Dispatched on submission when config('services.risk_scoring.enabled') is true.
 */
class ProcessRiskAssessment extends CriticalJob
{
    use SerializesModels;

    public function __construct(
        public Application $application
    ) {
        $this->onQueue('critical');
    }

    protected function getApplicationId(): ?int
    {
        return $this->application->id;
    }

    protected function getApplication(): ?Application
    {
        return $this->application;
    }

    public function handle(RiskScoringService $riskScoringService): void
    {
        if (!config('services.risk_scoring.enabled', false)) {
            Log::info('Risk scoring disabled, skipping', ['application_id' => $this->application->id]);
            return;
        }

        $riskScoringService->updateApplicationRiskScore($this->application);

        Log::info('Risk assessment completed', [
            'application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
        ]);
    }

    protected function handleApplicationFailure(Application $application, Throwable $exception): void
    {
        $application->update([
            'risk_screening_notes' => ($application->risk_screening_notes ? $application->risk_screening_notes . ' ' : '') .
                '[Risk assessment job failed: ' . $exception->getMessage() . ']',
        ]);
    }
}
