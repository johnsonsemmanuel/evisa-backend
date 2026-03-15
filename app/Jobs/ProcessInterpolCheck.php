<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\InterpolCheck;
use App\Services\AeropassService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInterpolCheck extends CriticalJob implements ShouldBeUnique
{
    use SerializesModels;

    public int $uniqueFor = 3600; // Lock for 1 hour

    public function __construct(
        private int $applicationId
    ) {
        $this->onQueue('critical');
    }

    public function uniqueId(): string
    {
        return 'aeropass:' . $this->applicationId;
    }

    protected function getApplicationId(): ?int
    {
        return $this->applicationId;
    }

    protected function getApplication(): ?Application
    {
        return Application::find($this->applicationId);
    }

    public function handle(AeropassService $aeropassService): void
    {
        $application = Application::find($this->applicationId);

        if (!$application) {
            Log::error('Application not found for Interpol check', ['application_id' => $this->applicationId]);
            return;
        }

        if (!config('services.aeropass.enabled')) {
            Log::info('Aeropass integration disabled, skipping Interpol check', ['application_id' => $this->applicationId]);
            return;
        }

        if (!in_array($application->status->value ?? $application->status, ['submitted', 'under_review', 'pending_approval'])) {
            Log::info('Application status does not require Interpol check', [
                'application_id' => $this->applicationId,
                'status' => $application->status->value ?? $application->status,
            ]);
            return;
        }

        $interpolCheck = $aeropassService->submitInterpolCheck($application);

        Log::info('Interpol check job completed', [
            'application_id' => $this->applicationId,
            'interpol_check_id' => $interpolCheck->id,
            'status' => $interpolCheck->status,
        ]);
    }

    protected function handleApplicationFailure(Application $application, Throwable $exception): void
    {
        InterpolCheck::where('application_id', $application->id)->update([
            'status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);
        if ($application->risk_screening_notes) {
            $application->update([
                'risk_screening_notes' => $application->risk_screening_notes . ' [Aeropass check permanently failed: ' . $exception->getMessage() . ']',
            ]);
        } else {
            $application->update(['risk_screening_notes' => 'Aeropass check permanently failed. Manual review required.']);
        }
    }
}
