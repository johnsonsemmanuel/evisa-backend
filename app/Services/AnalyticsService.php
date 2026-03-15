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
        $totalRevenue = Payment::where('status', 'paid')
            ->where('paid_at', '>=', $startDate)->sum('amount');
        
        // Calculate average processing time (SQLite compatible)
        $avgProcessingTime = Application::whereIn('status', ['approved', 'denied'])
            ->whereNotNull('submitted_at')
            ->whereNotNull('decided_at')
            ->where('decided_at', '>=', $startDate)
            ->get()
            ->map(function ($app) {
                if ($app->submitted_at && $app->decided_at) {
                    return $app->submitted_at->diffInHours($app->decided_at);
                }
                return 0;
            })
            ->average();

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
     * Get application metrics.
     */
    protected function getApplicationMetrics(\DateTime $startDate): array
    {
        return [
            'total' => Application::where('created_at', '>=', $startDate)->count(),
            'submitted' => Application::where('status', 'submitted')->where('created_at', '>=', $startDate)->count(),
            'under_review' => Application::where('status', 'under_review')->where('created_at', '>=', $startDate)->count(),
            'approved' => Application::where('status', 'approved')->where('created_at', '>=', $startDate)->count(),
            'denied' => Application::where('status', 'denied')->where('created_at', '>=', $startDate)->count(),
            'escalated' => Application::where('status', 'escalated')->where('created_at', '>=', $startDate)->count(),
            'issued' => Application::where('status', 'issued')->where('created_at', '>=', $startDate)->count(),
        ];
    }

    /**
     * Get revenue metrics.
     */
    protected function getRevenueMetrics(\DateTime $startDate): array
    {
        $totalRevenue = Payment::where('status', 'paid')
            ->where('paid_at', '>=', $startDate)
            ->sum('amount');

        $revenueByVisaType = Payment::where('payments.status', 'paid')
            ->where('payments.paid_at', '>=', $startDate)
            ->join('applications', 'payments.application_id', '=', 'applications.id')
            ->join('visa_types', 'applications.visa_type_id', '=', 'visa_types.id')
            ->selectRaw('visa_types.name, SUM(payments.amount) as total')
            ->groupBy('visa_types.name')
            ->get()
            ->map(fn($v) => [
                'visa_type' => $v->name,
                'total' => round($v->total, 2),
            ]);

        $dailyRevenue = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $revenue = Payment::where('status', 'paid')
                ->whereDate('paid_at', $date)
                ->sum('amount');
            $dailyRevenue[] = [
                'date' => $date,
                'revenue' => round($revenue, 2),
            ];
        }

        $paymentMethods = Payment::where('status', 'paid')
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
            ->where('decided_at', '>=', $startDate)
            ->get();

        // Calculate average processing time by agency (SQLite compatible)
        $avgByAgency = $completedApps
            ->whereNotNull('assigned_agency')
            ->groupBy('assigned_agency')
            ->map(function ($apps) {
                $totalHours = $apps->sum(function ($app) {
                    return $app->submitted_at->diffInHours($app->decided_at);
                });
                return round($totalHours / $apps->count(), 1);
            })
            ->toArray();

        // Calculate average processing time by visa type (SQLite compatible)
        $avgByVisaType = $completedApps
            ->whereNotNull('visa_type_id')
            ->load('visaType')
            ->groupBy('visaType.name')
            ->map(function ($apps) {
                $totalHours = $apps->sum(function ($app) {
                    return $app->submitted_at->diffInHours($app->decided_at);
                });
                return round($totalHours / $apps->count(), 1);
            })
            ->toArray();

        // SLA compliance calculation
        $slaCompliance = $completedApps->whereNotNull('sla_deadline');
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
     * Get trend data for the last N days.
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
            $revenue = Payment::where('status', 'paid')->whereDate('paid_at', $date)->sum('amount');

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

    // ==========================================
    // REVENUE ANALYTICS METHODS
    // ==========================================

    /**
     * Get total revenue for a date range.
     */
    public function getTotalRevenue(\DateTime $startDate, \DateTime $endDate): float
    {
        return Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Get revenue breakdown by visa type.
     */
    public function getRevenueByVisaType(\DateTime $startDate, \DateTime $endDate): Collection
    {
        return Payment::where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->join('applications', 'payments.application_id', '=', 'applications.id')
            ->join('visa_types', 'applications.visa_type_id', '=', 'visa_types.id')
            ->selectRaw('
                visa_types.id,
                visa_types.name,
                COUNT(payments.id) as payment_count,
                SUM(payments.amount) as total_revenue,
                AVG(payments.amount) as avg_amount
            ')
            ->groupBy('visa_types.id', 'visa_types.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($item) {
                return [
                    'visa_type_id' => $item->id,
                    'visa_type' => $item->name,
                    'count' => $item->payment_count,
                    'total' => round($item->total_revenue, 2),
                    'average' => round($item->avg_amount, 2),
                ];
            });
    }

    /**
     * Get revenue breakdown by country.
     */
    public function getRevenueByCountry(\DateTime $startDate, \DateTime $endDate): Collection
    {
        // FIX: Use JOIN instead of N+1 queries
        $results = Payment::where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->join('applications', 'payments.application_id', '=', 'applications.id')
            ->selectRaw('
                applications.nationality,
                COUNT(payments.id) as payment_count,
                SUM(payments.amount) as total_revenue
            ')
            ->groupBy('applications.nationality')
            ->orderByDesc('total_revenue')
            ->get();

        $totalRevenue = $results->sum('total_revenue');

        return $results->map(function ($item) use ($totalRevenue) {
            return [
                'country' => $item->nationality ?? 'Unknown',
                'count' => $item->payment_count,
                'total' => round($item->total_revenue, 2),
                'percentage' => $totalRevenue > 0 ? round(($item->total_revenue / $totalRevenue) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Get revenue breakdown by processing tier.
     */
    public function getRevenueByTier(\DateTime $startDate, \DateTime $endDate): Collection
    {
        $results = Payment::where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->join('applications', 'payments.application_id', '=', 'applications.id')
            ->selectRaw('
                COALESCE(applications.tier, "standard") as tier,
                COUNT(payments.id) as payment_count,
                SUM(payments.amount) as total_revenue
            ')
            ->groupBy('tier')
            ->orderByDesc('total_revenue')
            ->get();

        $totalRevenue = $results->sum('total_revenue');

        return $results->map(function ($item) use ($totalRevenue) {
            return [
                'tier' => $item->tier,
                'count' => $item->payment_count,
                'total' => round($item->total_revenue, 2),
                'percentage' => $totalRevenue > 0 ? round(($item->total_revenue / $totalRevenue) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Get revenue trends over time.
     */
    public function getRevenueTrends(\DateTime $startDate, \DateTime $endDate, string $period = 'daily'): Collection
    {
        // Whitelist allowed date formats to prevent SQL injection
        $allowedFormats = [
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            'yearly' => '%Y',
        ];
        
        $dateFormat = $allowedFormats[$period] ?? $allowedFormats['daily'];

        $results = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw("
                DATE_FORMAT(paid_at, ?) as period,
                COUNT(*) as payment_count,
                SUM(amount) as total_revenue
            ", [$dateFormat])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Calculate percentage changes
        $trends = collect();
        $previous = null;

        foreach ($results as $index => $item) {
            $change = null;
            if ($previous) {
                $change = $previous->total_revenue > 0 
                    ? round((($item->total_revenue - $previous->total_revenue) / $previous->total_revenue) * 100, 1)
                    : 0;
            }

            $trends->push([
                'period' => $item->period,
                'count' => $item->payment_count,
                'total' => round($item->total_revenue, 2),
                'change_percentage' => $change,
            ]);

            $previous = $item;
        }

        return $trends;
    }

    // ==========================================
    // VISITOR ANALYTICS METHODS
    // ==========================================

    /**
     * Get visitor/application counts by country.
     */
    public function getVisitorsByCountry(\DateTime $startDate, \DateTime $endDate): Collection
    {
        // FIX: Use database aggregation instead of loading all applications
        return Application::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('nationality, COUNT(*) as count')
            ->groupBy('nationality')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) use ($startDate, $endDate) {
                // Get total for percentage calculation
                static $total = null;
                if ($total === null) {
                    $total = Application::whereBetween('created_at', [$startDate, $endDate])->count();
                }
                
                return [
                    'country' => $item->nationality ?: 'Unknown',
                    'count' => $item->count,
                    'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
                ];
            });
    }

    /**
     * Get approval and denial rates by country.
     */
    public function getApprovalRatesByCountry(\DateTime $startDate, \DateTime $endDate): Collection
    {
        // FIX: Use database aggregation instead of loading all applications
        return Application::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                nationality,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ("approved", "issued") THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = "denied" THEN 1 ELSE 0 END) as denied,
                SUM(CASE WHEN status IN ("submitted", "under_review", "pending_approval", "escalated") THEN 1 ELSE 0 END) as pending
            ')
            ->groupBy('nationality')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                return [
                    'country' => $item->nationality ?: 'Unknown',
                    'total' => $item->total,
                    'approved' => $item->approved,
                    'denied' => $item->denied,
                    'pending' => $item->pending,
                    'approval_rate' => $item->total > 0 ? round(($item->approved / $item->total) * 100, 1) : 0,
                    'denial_rate' => $item->total > 0 ? round(($item->denied / $item->total) * 100, 1) : 0,
                    'pending_rate' => $item->total > 0 ? round(($item->pending / $item->total) * 100, 1) : 0,
                ];
            });
    }

    /**
     * Get visitor trends over time.
     */
    public function getVisitorTrends(\DateTime $startDate, \DateTime $endDate, string $period = 'daily', ?string $status = null): Collection
    {
        // Whitelist allowed date formats to prevent SQL injection
        $allowedFormats = [
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            'yearly' => '%Y',
        ];
        
        $dateFormat = $allowedFormats[$period] ?? $allowedFormats['daily'];

        $query = Application::whereBetween('created_at', [$startDate, $endDate]);

        if ($status) {
            $query->where('status', $status);
        }

        $results = $query->selectRaw("
                DATE_FORMAT(created_at, ?) as period,
                COUNT(*) as application_count
            ", [$dateFormat])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Calculate percentage changes
        $trends = collect();
        $previous = null;

        foreach ($results as $item) {
            $change = null;
            if ($previous) {
                $change = $previous->application_count > 0 
                    ? round((($item->application_count - $previous->application_count) / $previous->application_count) * 100, 1)
                    : 0;
            }

            $trends->push([
                'period' => $item->period,
                'count' => $item->application_count,
                'change_percentage' => $change,
            ]);

            $previous = $item;
        }

        return $trends;
    }

    /**
     * Get demographic breakdown.
     */
    public function getDemographicBreakdown(\DateTime $startDate, \DateTime $endDate): array
    {
        // FIX: Use database aggregation for gender and purpose, limit age calculation
        $totalCount = Application::whereBetween('created_at', [$startDate, $endDate])->count();
        
        // Gender breakdown using database aggregation
        $genderCounts = Application::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->pluck('count', 'gender');
            
        $genderBreakdown = [];
        foreach (['male', 'female', 'other'] as $gender) {
            $count = $genderCounts->get($gender, 0);
            $genderBreakdown[$gender] = [
                'count' => $count,
                'percentage' => $totalCount > 0 ? round(($count / $totalCount) * 100, 1) : 0,
            ];
        }
        $notSpecified = $totalCount - array_sum(array_column($genderBreakdown, 'count'));
        $genderBreakdown['not_specified'] = [
            'count' => $notSpecified,
            'percentage' => $totalCount > 0 ? round(($notSpecified / $totalCount) * 100, 1) : 0,
        ];

        // Purpose of visit breakdown using database aggregation
        $purposeCounts = Application::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('purpose_of_visit, COUNT(*) as count')
            ->groupBy('purpose_of_visit')
            ->pluck('count', 'purpose_of_visit');
            
        $purposeBreakdown = $purposeCounts->map(function ($count) use ($totalCount) {
            return [
                'count' => $count,
                'percentage' => $totalCount > 0 ? round(($count / $totalCount) * 100, 1) : 0,
            ];
        })->toArray();

        // Age groups - Use database calculation where possible
        $ageGroups = [
            '18-25' => 0,
            '26-35' => 0,
            '36-45' => 0,
            '46-55' => 0,
            '56-65' => 0,
            '65+' => 0,
            'Not Specified' => 0,
        ];

        // Use raw SQL to calculate age groups efficiently
        $ageData = Application::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                SUM(CASE 
                    WHEN date_of_birth IS NULL THEN 1 
                    ELSE 0 
                END) as not_specified,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) BETWEEN 18 AND 25 THEN 1 
                    ELSE 0 
                END) as age_18_25,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) BETWEEN 26 AND 35 THEN 1 
                    ELSE 0 
                END) as age_26_35,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) BETWEEN 36 AND 45 THEN 1 
                    ELSE 0 
                END) as age_36_45,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) BETWEEN 46 AND 55 THEN 1 
                    ELSE 0 
                END) as age_46_55,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) BETWEEN 56 AND 65 THEN 1 
                    ELSE 0 
                END) as age_56_65,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) > 65 THEN 1 
                    ELSE 0 
                END) as age_65_plus
            ')
            ->first();

        if ($ageData) {
            $ageGroups['18-25'] = $ageData->age_18_25;
            $ageGroups['26-35'] = $ageData->age_26_35;
            $ageGroups['36-45'] = $ageData->age_36_45;
            $ageGroups['46-55'] = $ageData->age_46_55;
            $ageGroups['56-65'] = $ageData->age_56_65;
            $ageGroups['65+'] = $ageData->age_65_plus;
            $ageGroups['Not Specified'] = $ageData->not_specified;
        }

        return [
            'age_groups' => collect($ageGroups)->map(function ($count) use ($totalCount) {
                return [
                    'count' => $count,
                    'percentage' => $totalCount > 0 ? round(($count / $totalCount) * 100, 1) : 0,
                ];
            })->toArray(),
            'gender' => $genderBreakdown,
            'purpose_of_visit' => $purposeBreakdown,
        ];
    }
}