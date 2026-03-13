<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class SlaMonitoringService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get SLA dashboard metrics.
     */
    public function getDashboardMetrics(): array
    {
        $activeApplications = Application::whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft'])
            ->whereNotNull('sla_deadline')
            ->get();

        $now = now();
        $breached = 0;
        $criticalWarning = 0; // < 2 hours
        $warning = 0; // < 8 hours
        $onTrack = 0;

        foreach ($activeApplications as $app) {
            $hoursRemaining = $now->diffInHours($app->sla_deadline, false);
            
            if ($hoursRemaining < 0) {
                $breached++;
            } elseif ($hoursRemaining < 2) {
                $criticalWarning++;
            } elseif ($hoursRemaining < 8) {
                $warning++;
            } else {
                $onTrack++;
            }
        }

        return [
            'total_active' => $activeApplications->count(),
            'breached' => $breached,
            'critical_warning' => $criticalWarning,
            'warning' => $warning,
            'on_track' => $onTrack,
            'breach_rate' => $activeApplications->count() > 0 
                ? round(($breached / $activeApplications->count()) * 100, 1) 
                : 0,
            'by_agency' => $this->getMetricsByAgency(),
            'by_officer' => $this->getMetricsByOfficer(),
        ];
    }

    /**
     * Get applications approaching SLA breach.
     */
    public function getAtRiskApplications(int $hoursThreshold = 8): Collection
    {
        $deadline = now()->addHours($hoursThreshold);

        return Application::whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft'])
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<=', $deadline)
            ->where('sla_deadline', '>', now())
            ->orderBy('sla_deadline', 'asc')
            ->with(['visaType', 'assignedOfficer'])
            ->get()
            ->map(function ($app) {
                return [
                    'id' => $app->id,
                    'reference_number' => $app->reference_number,
                    'applicant_name' => $app->first_name . ' ' . $app->last_name,
                    'visa_type' => $app->visaType?->name,
                    'status' => $app->status,
                    'assigned_agency' => $app->assigned_agency,
                    'assigned_officer' => $app->assignedOfficer?->full_name,
                    'sla_deadline' => $app->sla_deadline->format('Y-m-d H:i'),
                    'hours_remaining' => now()->diffInHours($app->sla_deadline, false),
                    'submitted_at' => $app->submitted_at?->format('Y-m-d H:i'),
                ];
            });
    }

    /**
     * Get breached applications.
     */
    public function getBreachedApplications(): Collection
    {
        return Application::whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft'])
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->orderBy('sla_deadline', 'asc')
            ->with(['visaType', 'assignedOfficer'])
            ->get()
            ->map(function ($app) {
                return [
                    'id' => $app->id,
                    'reference_number' => $app->reference_number,
                    'applicant_name' => $app->first_name . ' ' . $app->last_name,
                    'visa_type' => $app->visaType?->name,
                    'status' => $app->status,
                    'assigned_agency' => $app->assigned_agency,
                    'assigned_officer' => $app->assignedOfficer?->full_name,
                    'sla_deadline' => $app->sla_deadline->format('Y-m-d H:i'),
                    'hours_overdue' => abs(now()->diffInHours($app->sla_deadline, false)),
                    'submitted_at' => $app->submitted_at?->format('Y-m-d H:i'),
                ];
            });
    }

    /**
     * Get metrics grouped by agency.
     */
    protected function getMetricsByAgency(): array
    {
        $agencies = ['gis', 'mfa'];
        $metrics = [];

        foreach ($agencies as $agency) {
            $apps = Application::where('assigned_agency', $agency)
                ->whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft'])
                ->whereNotNull('sla_deadline')
                ->get();

            $breached = $apps->filter(fn($a) => $a->sla_deadline < now())->count();
            $total = $apps->count();

            $metrics[$agency] = [
                'total' => $total,
                'breached' => $breached,
                'on_track' => $total - $breached,
                'compliance_rate' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100,
            ];
        }

        return $metrics;
    }

    /**
     * Get metrics grouped by officer.
     */
    protected function getMetricsByOfficer(): array
    {
        return Application::whereNotNull('assigned_officer_id')
            ->whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft'])
            ->whereNotNull('sla_deadline')
            ->with('assignedOfficer')
            ->get()
            ->groupBy('assigned_officer_id')
            ->map(function ($apps, $officerId) {
                $officer = $apps->first()->assignedOfficer;
                $breached = $apps->filter(fn($a) => $a->sla_deadline < now())->count();
                $total = $apps->count();

                return [
                    'officer_id' => $officerId,
                    'officer_name' => $officer?->full_name ?? 'Unknown',
                    'agency' => $officer?->agency ?? 'N/A',
                    'total' => $total,
                    'breached' => $breached,
                    'on_track' => $total - $breached,
                    'compliance_rate' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 100,
                ];
            })
            ->sortByDesc('breached')
            ->values()
            ->take(10)
            ->toArray();
    }

    /**
     * Send SLA warning notifications.
     */
    public function sendSlaWarnings(): array
    {
        $results = ['warnings_sent' => 0, 'critical_sent' => 0];

        // Warning: 4-8 hours remaining
        $warningApps = Application::whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft'])
            ->whereNotNull('sla_deadline')
            ->whereNotNull('assigned_officer_id')
            ->whereBetween('sla_deadline', [now()->addHours(4), now()->addHours(8)])
            ->get();

        foreach ($warningApps as $app) {
            $this->notificationService->notifySlaWarning($app, $app->slaHoursRemaining());
            $results['warnings_sent']++;
        }

        // Critical: < 2 hours remaining
        $criticalApps = Application::whereNotIn('status', ['approved', 'denied', 'cancelled', 'draft'])
            ->whereNotNull('sla_deadline')
            ->whereNotNull('assigned_officer_id')
            ->whereBetween('sla_deadline', [now(), now()->addHours(2)])
            ->get();

        foreach ($criticalApps as $app) {
            $this->notificationService->notifySlaWarning($app, $app->slaHoursRemaining());
            $results['critical_sent']++;
        }

        Log::info('SLA warnings sent', $results);

        return $results;
    }

    /**
     * Get historical SLA performance.
     */
    public function getHistoricalPerformance(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $completed = Application::whereIn('status', ['approved', 'denied'])
            ->where('decided_at', '>=', $startDate)
            ->whereNotNull('sla_deadline')
            ->get();

        $onTime = $completed->filter(fn($a) => $a->decided_at <= $a->sla_deadline)->count();
        $total = $completed->count();

        // Daily breakdown
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayApps = $completed->filter(fn($a) => $a->decided_at->format('Y-m-d') === $date);
            $dayOnTime = $dayApps->filter(fn($a) => $a->decided_at <= $a->sla_deadline)->count();
            
            $daily[] = [
                'date' => $date,
                'total' => $dayApps->count(),
                'on_time' => $dayOnTime,
                'breached' => $dayApps->count() - $dayOnTime,
            ];
        }

        return [
            'period_days' => $days,
            'total_completed' => $total,
            'on_time' => $onTime,
            'breached' => $total - $onTime,
            'compliance_rate' => $total > 0 ? round(($onTime / $total) * 100, 1) : 100,
            'avg_processing_hours' => $completed->avg(fn($a) => $a->submitted_at?->diffInHours($a->decided_at)),
            'daily' => $daily,
        ];
    }
}
