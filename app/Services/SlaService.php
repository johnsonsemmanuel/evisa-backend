<?php

namespace App\Services;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlaService
{
    /**
     * Calculate a deadline from now + given hours (business hours aware).
     */
    public function calculateDeadline(int $hours): Carbon
    {
        return now()->addHours($hours);
    }

    /**
     * Extend an existing deadline by additional hours.
     */
    public function extendDeadline(?Carbon $currentDeadline, int $additionalHours): Carbon
    {
        $base = $currentDeadline ?? now();
        return $base->copy()->addHours($additionalHours);
    }

    /**
     * Check if an application has breached its SLA.
     */
    public function isBreached(Application $application): bool
    {
        if (!$application->sla_deadline) {
            return false;
        }

        return now()->gt($application->sla_deadline)
            && !in_array($application->status, ['approved', 'denied', 'cancelled']);
    }

    /**
     * Get remaining hours for an application's SLA.
     */
    public function remainingHours(Application $application): ?float
    {
        return $application->slaHoursRemaining();
    }

    /**
     * Get all applications nearing SLA breach (within threshold hours).
     */
    public function getApproachingBreach(int $thresholdHours = 6): Collection
    {
        return Application::whereNotNull('sla_deadline')
            ->whereNotIn('status', ['approved', 'denied', 'cancelled'])
            ->where('sla_deadline', '<=', now()->addHours($thresholdHours))
            ->where('sla_deadline', '>', now())
            ->orderBy('sla_deadline', 'asc')
            ->get();
    }

    /**
     * Get all applications that have breached SLA.
     */
    public function getBreached(): Collection
    {
        return Application::whereNotNull('sla_deadline')
            ->whereNotIn('status', ['approved', 'denied', 'cancelled'])
            ->where('sla_deadline', '<', now())
            ->orderBy('sla_deadline', 'asc')
            ->get();
    }

    /**
     * Get SLA statistics for reporting.
     */
    public function getStats(): array
    {
        $total = Application::whereNotNull('sla_deadline')->count();
        $breached = $this->getBreached()->count();
        $approaching = $this->getApproachingBreach()->count();

        $decidedWithinSla = Application::whereNotNull('sla_deadline')
            ->whereNotNull('decided_at')
            ->whereColumn('decided_at', '<=', 'sla_deadline')
            ->count();

        $totalDecided = Application::whereNotNull('decided_at')->count();

        return [
            'total_tracked'       => $total,
            'currently_breached'  => $breached,
            'approaching_breach'  => $approaching,
            'compliance_rate'     => $totalDecided > 0 ? round(($decidedWithinSla / $totalDecided) * 100, 1) : 100,
            'avg_processing_hours' => Application::whereNotNull('decided_at')
                ->whereNotNull('submitted_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, decided_at)) as avg_hours')
                ->value('avg_hours') ?? 0,
        ];
    }
}
