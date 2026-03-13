<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\AeropassService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInterpolCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // 1 minute backoff between retries

    public function __construct(
        private int $applicationId
    ) {}

    public function handle(AeropassService $aeropassService): void
    {
        $application = Application::find($this->applicationId);
        
        if (!$application) {
            Log::error('Application not found for Interpol check', [
                'application_id' => $this->applicationId,
            ]);
            return;
        }

        // Skip if Aeropass is disabled
        if (!config('services.aeropass.enabled')) {
            Log::info('Aeropass integration disabled, skipping Interpol check', [
                'application_id' => $this->applicationId,
            ]);
            return;
        }

        // Skip if application is not in a state that requires Interpol check
        if (!in_array($application->status, ['submitted', 'under_review', 'pending_approval'])) {
            Log::info('Application status does not require Interpol check', [
                'application_id' => $this->applicationId,
                'status' => $application->status,
            ]);
            return;
        }

        try {
            $interpolCheck = $aeropassService->submitInterpolCheck($application);
            
            Log::info('Interpol check job completed', [
                'application_id' => $this->applicationId,
                'interpol_check_id' => $interpolCheck->id,
                'status' => $interpolCheck->status,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Interpol check job failed', [
                'application_id' => $this->applicationId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Interpol check job failed permanently', [
            'application_id' => $this->applicationId,
            'error' => $exception->getMessage(),
        ]);
    }
}