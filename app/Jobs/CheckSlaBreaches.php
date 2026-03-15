<?php

namespace App\Jobs;

use App\Services\SlaService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSlaBreaches extends BaseJob
{
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(SlaService $slaService): void
    {
        $approaching = $slaService->getApproachingBreach(6);
        foreach ($approaching as $application) {
            Log::warning("SLA approaching breach: {$application->reference_number} — {$application->slaHoursRemaining()}h remaining");

            SendNotification::dispatch(
                $application,
                'sla_warning',
                ['hours_remaining' => round($application->slaHoursRemaining(), 1)]
            )->onQueue('default');
        }

        $breached = $slaService->getBreached();
        foreach ($breached as $application) {
            Log::error("SLA BREACHED: {$application->reference_number}");

            SendNotification::dispatch(
                $application,
                'sla_breached',
                ['breached_by_hours' => round(abs($application->slaHoursRemaining()), 1)]
            )->onQueue('default');
        }

        Log::info("SLA check completed: {$approaching->count()} approaching, {$breached->count()} breached");
    }
}
