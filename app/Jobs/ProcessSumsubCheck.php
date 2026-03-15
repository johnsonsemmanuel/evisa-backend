<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\SumsubVerification;
use App\Services\SumsubService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued job to check/fetch Sumsub KYC status for an application.
 * Idempotent: only one running per application at a time.
 */
class ProcessSumsubCheck extends CriticalJob implements ShouldBeUnique
{
    use SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public Application $application
    ) {
        $this->onQueue('critical');
    }

    public function uniqueId(): string
    {
        return 'sumsub:' . $this->application->id;
    }

    protected function getApplicationId(): ?int
    {
        return $this->application->id;
    }

    protected function getApplication(): ?Application
    {
        return $this->application;
    }

    public function handle(SumsubService $sumsubService): void
    {
        if (!config('sumsub.enabled', false)) {
            Log::info('Sumsub disabled, skipping status check', ['application_id' => $this->application->id]);
            return;
        }

        $verification = SumsubVerification::where('application_id', $this->application->id)->first();
        if (!$verification || !$verification->applicant_id) {
            Log::info('No Sumsub verification with applicant_id for application', ['application_id' => $this->application->id]);
            return;
        }

        $result = $sumsubService->getApplicantStatus($verification->applicant_id);
        if (!empty($result['success']) && !empty($result['data'])) {
            $sumsubService->handleWebhook($result['data']);
        }

        Log::info('Sumsub status check completed', ['application_id' => $this->application->id]);
    }

    protected function handleApplicationFailure(Application $application, Throwable $exception): void
    {
        Log::error('Sumsub check permanently failed for application', [
            'application_id' => $application->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
