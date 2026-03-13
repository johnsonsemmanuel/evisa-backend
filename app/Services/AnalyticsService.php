<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Get comprehensive dashboard analytics.
     */
    public function getDashboardAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'summary' => $this->getSummaryMetrics($startDate),
            'applications' => $this->getApplicationMetrics($startDate),
            'revenue' => $this->getRevenueMetrics($startDate),
            'processing' => $this->getProcessingMetrics($startDate),
            'trends' => $this->getTrendData($days),
        ];
    }

    /**
     * Get summary metrics.
     */
    protected function getSummaryMetrics(\DateTime $startDate): array
    {
        $totalApplications = Application::where('created_at', '>=', $startDate)->count();
        $approvedApplications = Application::where('status', 'approved')
            ->where('decided_at', '>=', $startDate)->count();
        $totalRevenue = Payment::where('status', 'completed')
            ->where('paid_at', '>=', $startDate)->sum('amount');
        $avgProcessingTime = Application::whereIn('status', ['approved', 'denied'])
            ->whereNotNull('submitted_at')
            ->whereNotNull('decided_at')
            ->where('decided_at', '>=', $startDate)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, decided_at)) as avg_hours')
            ->value('avg_hours');

        return [
            'total_applications' => $totalApplications,
            'approved_applications' => $approvedApplications,
            'approval_rate' => $totalApplications > 0 
                ? round(($approvedApplications / $totalApplications) * 100, 1) 
                : 0,
            'total_revenue' => round($totalRevenue, 2),
            'avg_processing_hours' => round($avgProcessingTime ?? 0, 1),
        ];
    }

    /**
     * Get application metrics breakdown.
     */
    protected function getApplicationMetrics(\DateTime $startDate): array
    {
        $byStatus = Application::where('created_at', '>=', $startDate)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byVisaType = Application::where('created_at', '>=', $startDate)
            ->join('visa_types', 'applications.visa_type_id', '=', 'visa_types.id')
            ->selectRaw('visa_types.name, COUNT(*) as count')
            ->groupBy('visa_types.name')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'name')
            ->toArray();

        $byNationality = Application::where('applications.created_at', '>=', $startDate)
            ->selectRaw('nationality_encrypted, COUNT(*) as count')
            ->groupBy('nationality_encrypted')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'nationality' => $item->nationality ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        $byAgency = Application::where('created_at', '>=', $startDate)
            ->whereNotNull('assigned_agency')
            ->selectRaw('assigned_agency, COUNT(*) as count')
            ->groupBy('assigned_agency')
            ->pluck('count', 'assigned_agency')
            ->toArray();

        return [
            'by_status' => $byStatus,
            'by_visa_type' => $byVisaType,
            'by_nationality' => $byNationality,
            'by_agency' => $byAgency,
        ];
    }

    /**
     * Get revenue metrics.
     */
    protected function getRevenueMetrics(\DateTime $startDate): array
    {
        $totalRevenue = Payment::where('status', 'completed')
            ->where('paid_at', '>=', $startDate)
            ->sum('amount');

        $revenueByVisaType = Payment::where('payments.status', 'completed')
            ->where('payments.paid_at', '>=', $startDate)
            ->join('applications', 'payments.application_id', '=', 'applications.id')
            ->join('visa_types', 'applications.visa_type_id', '=', 'visa_types.id')
            ->selectRaw('visa_types.name, SUM(payments.amount) as total')
            ->groupBy('visa_types.name')
            ->orderByDesc('total')
            ->pluck('total', 'name')
            ->toArray();

        $dailyRevenue = Payment::where('status', 'completed')
            ->where('paid_at', '>=', $startDate)
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'total' => round($r->total, 2)]);

        $paymentMethods = Payment::where('status', 'completed')
            ->where('paid_at', '>=', $startDate)
            ->selectRaw('payment_provider, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_provider')
            ->get()
            ->map(fn($p) => [
                'provider' => $p->payment_provider,
                'count' => $p->count,
                'total' => round($p->total, 2),
            ]);

        return [
            'total' => round($totalRevenue, 2),
            'by_visa_type' => $revenueByVisaType,
            'daily' => $dailyRevenue,
            'by_payment_method' => $paymentMethods,
        ];
    }

    /**
     * Get processing time metrics.
     */
    protected function getProcessingMetrics(\DateTime $startDate): array
    {
        $completedApps = Application::whereIn('status', ['approved', 'denied'])
            ->whereNotNull('submitted_at')
            ->whereNotNull('decided_at')
            ->where('decided_at', '>=', $startDate);

        $avgByAgency = (clone $completedApps)
            ->whereNotNull('assigned_agency')
            ->selectRaw('assigned_agency, AVG(TIMESTAMPDIFF(HOUR, submitted_at, decided_at)) as avg_hours')
            ->groupBy('assigned_agency')
            ->pluck('avg_hours', 'assigned_agency')
            ->map(fn($h) => round($h, 1))
            ->toArray();

        $avgByVisaType = (clone $completedApps)
            ->join('visa_types', 'applications.visa_type_id', '=', 'visa_types.id')
            ->selectRaw('visa_types.name, AVG(TIMESTAMPDIFF(HOUR, submitted_at, decided_at)) as avg_hours')
            ->groupBy('visa_types.name')
            ->pluck('avg_hours', 'name')
            ->map(fn($h) => round($h, 1))
            ->toArray();

        $slaCompliance = $completedApps
            ->whereNotNull('sla_deadline')
            ->get();

        $onTime = $slaCompliance->filter(fn($a) => $a->decided_at <= $a->sla_deadline)->count();
        $total = $slaCompliance->count();

        return [
            'avg_by_agency' => $avgByAgency,
            'avg_by_visa_type' => $avgByVisaType,
            'sla_compliance_rate' => $total > 0 ? round(($onTime / $total) * 100, 1) : 100,
            'on_time_count' => $onTime,
            'breached_count' => $total - $onTime,
        ];
    }

    /**
     * Get trend data for charts.
     */
    protected function getTrendData(int $days): array
    {
        $daily = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            
            $applications = Application::whereDate('created_at', $date)->count();
            $submitted = Application::whereDate('submitted_at', $date)->count();
            $approved = Application::where('status', 'approved')->whereDate('decided_at', $date)->count();
            $denied = Application::where('status', 'denied')->whereDate('decided_at', $date)->count();
            $revenue = Payment::where('status', 'completed')->whereDate('paid_at', $date)->sum('amount');

            $daily[] = [
                'date' => $date,
                'applications' => $applications,
                'submitted' => $submitted,
                'approved' => $approved,
                'denied' => $denied,
                'revenue' => round($revenue, 2),
            ];
        }

        return $daily;
    }

    /**
     * Get officer performance metrics.
     */
    public function getOfficerPerformance(int $days = 30): Collection
    {
        $startDate = now()->subDays($days);

        return Application::whereNotNull('assigned_officer_id')
            ->whereIn('status', ['approved', 'denied'])
            ->where('decided_at', '>=', $startDate)
            ->with('assignedOfficer:id,first_name,last_name,agency')
            ->get()
            ->groupBy('assigned_officer_id')
            ->map(function ($apps, $officerId) {
                $officer = $apps->first()->assignedOfficer;
                $approved = $apps->where('status', 'approved')->count();
                $denied = $apps->where('status', 'denied')->count();
                $total = $apps->count();

                $avgHours = $apps->filter(fn($a) => $a->submitted_at && $a->decided_at)
                    ->avg(fn($a) => $a->submitted_at->diffInHours($a->decided_at));

                $onTime = $apps->filter(fn($a) => $a->sla_deadline && $a->decided_at <= $a->sla_deadline)->count();

                return [
                    'officer_id' => $officerId,
                    'officer_name' => $officer?->full_name ?? 'Unknown',
                    'agency' => $officer?->agency,
                    'total_processed' => $total,
                    'approved' => $approved,
                    'denied' => $denied,
                    'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
                    'avg_processing_hours' => round($avgHours ?? 0, 1),
                    'sla_compliance' => $total > 0 ? round(($onTime / $total) * 100, 1) : 100,
                ];
            })
            ->sortByDesc('total_processed')
            ->values();
    }

    /**
     * Export applications to CSV.
     */
    public function exportApplicationsCsv(array $filters = []): string
    {
        $query = Application::with(['visaType', 'assignedOfficer', 'payment']);

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $applications = $query->get();

        $csv = "Reference,Applicant,Visa Type,Status,Submitted,Decided,Agency,Officer,Amount\n";

        foreach ($applications as $app) {
            $csv .= sprintf(
                "%s,\"%s %s\",%s,%s,%s,%s,%s,%s,%s\n",
                $app->reference_number,
                $app->first_name,
                $app->last_name,
                $app->visaType?->name ?? 'N/A',
                $app->status,
                $app->submitted_at?->format('Y-m-d H:i') ?? 'N/A',
                $app->decided_at?->format('Y-m-d H:i') ?? 'N/A',
                strtoupper($app->assigned_agency ?? 'N/A'),
                $app->assignedOfficer?->full_name ?? 'Unassigned',
                $app->payment?->amount ?? '0.00'
            );
        }

        return $csv;
    }
}
