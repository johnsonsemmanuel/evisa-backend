<?php

namespace App\Services;

use App\Events\ApplicationStatusChanged;
use App\Events\DashboardMetricsUpdated;
use App\Events\PaymentCompleted;
use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RealTimeDashboardService
{
    /**
     * Broadcast application status change and update metrics.
     */
    public function broadcastApplicationStatusChange(
        Application $application,
        string $previousStatus,
        string $newStatus,
        ?string $notes = null
    ): void {
        // Broadcast the status change event
        broadcast(new ApplicationStatusChanged($application, $previousStatus, $newStatus, $notes));
        
        // Update and broadcast metrics for affected agencies
        $this->updateAndBroadcastMetrics($application);
    }

    /**
     * Broadcast payment completion and update admin metrics.
     */
    public function broadcastPaymentCompleted(Payment $payment): void
    {
        broadcast(new PaymentCompleted($payment));
        
        // Update admin payment metrics
        $this->updateAndBroadcastPaymentMetrics();
    }

    /**
     * Update and broadcast metrics for all affected agencies.
     */
    public function updateAndBroadcastMetrics(Application $application): void
    {
        // Update GIS metrics if application is/was in GIS
        if ($application->assigned_agency === 'gis' || $application->wasRecentlyAssignedTo('gis')) {
            $gisMetrics = $this->calculateGisMetrics();
            $this->cacheMetrics('gis', $gisMetrics);
            broadcast(new DashboardMetricsUpdated('gis', $gisMetrics));
        }

        // Update MFA metrics if application is/was in MFA
        if ($application->assigned_agency === 'mfa' || $application->wasRecentlyAssignedTo('mfa')) {
            $mfaMetrics = $this->calculateMfaMetrics($application->owner_mission_id);
            $this->cacheMetrics('mfa', $mfaMetrics, $application->owner_mission_id);
            broadcast(new DashboardMetricsUpdated('mfa', $mfaMetrics, $application->owner_mission_id));
        }

        // Always update admin metrics
        $adminMetrics = $this->calculateAdminMetrics();
        $this->cacheMetrics('admin', $adminMetrics);
        broadcast(new DashboardMetricsUpdated('admin', $adminMetrics));
    }

    /**
     * Calculate GIS dashboard metrics.
     */
    public function calculateGisMetrics(): array
    {
        $base = Application::where('assigned_agency', 'gis');

        return [
            'pending_review'    => (clone $base)->whereIn('status', ['submitted', 'under_review'])->count(),
            'in_review'         => (clone $base)->where('status', 'under_review')->count(),
            'pending_approval'  => (clone $base)->where('status', 'pending_approval')->count(),
            'approved_today'    => (clone $base)->whereIn('status', ['approved', 'issued'])->whereDate('decided_at', today())->count(),
            'issued_today'      => (clone $base)->where('status', 'issued')->whereDate('updated_at', today())->count(),
            'total_approved'    => (clone $base)->whereIn('status', ['approved', 'issued'])->count(),
            'total_denied'      => (clone $base)->where('status', 'denied')->count(),
            'sla_breaches'      => (clone $base)->whereNotNull('sla_deadline')
                ->whereNotIn('status', ['approved', 'denied', 'issued', 'cancelled'])
                ->where('sla_deadline', '<', now())->count(),
            'review_queue'      => (clone $base)->where(function ($q) {
                $q->where('current_queue', 'review_queue')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('current_queue')
                         ->whereIn('status', ['submitted', 'under_review', 'additional_info_requested']);
                  });
            })->count(),
            'approval_queue'    => (clone $base)->where(function ($q) {
                $q->where('current_queue', 'approval_queue')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('current_queue')
                         ->where('status', 'pending_approval');
                  });
            })->count(),
            'flagged_etas'      => (clone $base)->where('visa_type_id', function($query) {
                $query->select('id')->from('visa_types')->where('code', 'ETA');
            })->where('status', 'flagged')->count(),
        ];
    }

    /**
     * Calculate MFA dashboard metrics.
     */
    public function calculateMfaMetrics(?int $missionId = null): array
    {
        $base = Application::where('assigned_agency', 'mfa');
        
        // Filter by mission if specified
        if ($missionId) {
            $base->where('owner_mission_id', $missionId);
        }

        return [
            'total_escalated'   => (clone $base)->count(),
            'pending_decision'  => (clone $base)->whereIn('status', ['escalated', 'under_review'])->count(),
            'pending_approval'  => (clone $base)->where('status', 'pending_approval')->count(),
            'approved_today'    => (clone $base)->whereIn('status', ['approved', 'issued'])->whereDate('decided_at', today())->count(),
            'denied_today'      => (clone $base)->where('status', 'denied')->whereDate('decided_at', today())->count(),
            'issued_today'      => (clone $base)->where('status', 'issued')->whereDate('updated_at', today())->count(),
            'total_approved'    => (clone $base)->whereIn('status', ['approved', 'issued'])->count(),
            'total_denied'      => (clone $base)->where('status', 'denied')->count(),
            'sla_breaches'      => (clone $base)->whereNotNull('sla_deadline')
                ->whereNotIn('status', ['approved', 'denied', 'issued', 'cancelled'])
                ->where('sla_deadline', '<', now())->count(),
            'review_queue'      => (clone $base)->where(function ($q) {
                $q->where('current_queue', 'review_queue')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('current_queue')
                         ->whereIn('status', ['escalated', 'under_review']);
                  });
            })->count(),
            'approval_queue'    => (clone $base)->where(function ($q) {
                $q->where('current_queue', 'approval_queue')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('current_queue')
                         ->where('status', 'pending_approval');
                  });
            })->count(),
            'flagged_etas'      => (clone $base)->where('visa_type_id', function($query) {
                $query->select('id')->from('visa_types')->where('code', 'ETA');
            })->where('status', 'flagged')->count(),
        ];
    }

    /**
     * Calculate admin dashboard metrics.
     */
    public function calculateAdminMetrics(): array
    {
        $applications = [
            'total'        => Application::count(),
            'draft'        => Application::where('status', 'draft')->count(),
            'submitted'    => Application::where('status', 'submitted')->count(),
            'under_review' => Application::where('status', 'under_review')->count(),
            'approved'     => Application::where('status', 'approved')->count(),
            'denied'       => Application::where('status', 'denied')->count(),
            'escalated'    => Application::where('status', 'escalated')->count(),
            'issued'       => Application::where('status', 'issued')->count(),
        ];

        $payments = [
            'total_collected' => Payment::where('status', 'completed')->sum('amount'),
            'completed'       => Payment::where('status', 'completed')->count(),
            'failed'          => Payment::where('status', 'failed')->count(),
            'pending'         => Payment::where('status', 'pending')->count(),
            'today_revenue'   => Payment::where('status', 'completed')->whereDate('updated_at', today())->sum('amount'),
            'today_count'     => Payment::where('status', 'completed')->whereDate('updated_at', today())->count(),
        ];

        $users = [
            'total_applicants'  => User::where('role', 'applicant')->count(),
            'total_officers'    => User::whereIn('role', ['gis_officer', 'gis_admin', 'mfa_reviewer', 'mfa_admin'])->count(),
            'active_officers'   => User::whereIn('role', ['gis_officer', 'gis_admin', 'mfa_reviewer', 'mfa_admin'])->where('is_active', true)->count(),
        ];

        return [
            'applications' => $applications,
            'payments' => $payments,
            'users' => $users,
        ];
    }

    /**
     * Update payment metrics for admin dashboard.
     */
    public function updateAndBroadcastPaymentMetrics(): void
    {
        $adminMetrics = $this->calculateAdminMetrics();
        $this->cacheMetrics('admin', $adminMetrics);
        broadcast(new DashboardMetricsUpdated('admin', $adminMetrics));
    }

    /**
     * Cache metrics for faster retrieval.
     */
    protected function cacheMetrics(string $agency, array $metrics, ?int $missionId = null): void
    {
        $cacheKey = $missionId ? "dashboard.metrics.{$agency}.mission.{$missionId}" : "dashboard.metrics.{$agency}";
        Cache::put($cacheKey, $metrics, now()->addMinutes(5));
    }

    /**
     * Get cached metrics or calculate fresh ones.
     */
    public function getCachedMetrics(string $agency, ?int $missionId = null): array
    {
        $cacheKey = $missionId ? "dashboard.metrics.{$agency}.mission.{$missionId}" : "dashboard.metrics.{$agency}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($agency, $missionId) {
            return match ($agency) {
                'gis' => $this->calculateGisMetrics(),
                'mfa' => $this->calculateMfaMetrics($missionId),
                'admin' => $this->calculateAdminMetrics(),
                default => [],
            };
        });
    }

    /**
     * Force refresh all dashboard metrics.
     */
    public function refreshAllMetrics(): void
    {
        // Clear all cached metrics
        Cache::forget('dashboard.metrics.gis');
        Cache::forget('dashboard.metrics.mfa');
        Cache::forget('dashboard.metrics.admin');
        
        // Calculate and broadcast fresh metrics
        $gisMetrics = $this->calculateGisMetrics();
        $this->cacheMetrics('gis', $gisMetrics);
        broadcast(new DashboardMetricsUpdated('gis', $gisMetrics));

        $mfaMetrics = $this->calculateMfaMetrics();
        $this->cacheMetrics('mfa', $mfaMetrics);
        broadcast(new DashboardMetricsUpdated('mfa', $mfaMetrics));

        $adminMetrics = $this->calculateAdminMetrics();
        $this->cacheMetrics('admin', $adminMetrics);
        broadcast(new DashboardMetricsUpdated('admin', $adminMetrics));
    }
}