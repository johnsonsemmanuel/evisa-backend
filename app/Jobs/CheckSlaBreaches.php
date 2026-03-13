<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\SlaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSlaBreaches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('sla');
    }

    /**
     * Scheduled job: checks for approaching and breached SLAs.
     * Should be run every 15 minutes via scheduler.
     */
    public function handle(SlaService $slaService): void
    {
        // Notify on approaching breaches (< 6 hours remaining)
        $approaching = $slaService->getApproachingBreach(6);
        foreach ($approaching as $application) {
            Log::warning("SLA approaching breach: {$application->reference_number} — {$application->slaHoursRemaining()}h remaining");

            SendNotification::dispatch(
                $application,
                'sla_warning',
                ['hours_remaining' => round($application->slaHoursRemaining(), 1)]
            );
        }

        // Log breached applications
        $breached = $slaService->getBreached();
        foreach ($breached as $application) {
            Log::error("SLA BREACHED: {$application->reference_number}");

            SendNotification::dispatch(
                $application,
                'sla_breached',
                ['breached_by_hours' => round(abs($application->slaHoursRemaining()), 1)]
            );
        }

        Log::info("SLA check completed: {$approaching->count()} approaching, {$breached->count()} breached");
    }
}
