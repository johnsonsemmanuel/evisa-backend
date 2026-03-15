<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get comprehensive dashboard analytics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $days = $request->query('days', 30);

        return response()->json([
            'analytics' => $this->analyticsService->getDashboardAnalytics((int) $days),
        ]);
    }

    /**
     * Get officer performance metrics.
     */
    public function officerPerformance(Request $request): JsonResponse
    {
        $days = $request->query('days', 30);

        return response()->json([
            'officers' => $this->analyticsService->getOfficerPerformance((int) $days),
        ]);
    }

    /**
     * Export applications to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'status']);

        $csv = $this->analyticsService->exportApplicationsCsv($filters);
        $filename = 'applications_export_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ==========================================
    // FINANCIAL ANALYTICS ENDPOINTS
    // ==========================================

    /**
     * Get total revenue for a date range.
     */
    public function getRevenue(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $total = $this->analyticsService->getTotalRevenue($startDate, $endDate);
        
        // FIX: Use COUNT query instead of loading all payments into memory
        $count = \App\Models\Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();
        
        $average = $count > 0 ? $total / $count : 0;

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'revenue_total',
            $validated,
            null,
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => [
                'total' => round($total, 2),
                'count' => $count,
                'average' => round($average, 2),
                'currency' => 'GHS',
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ],
            'metadata' => [
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    /**
     * Get revenue breakdown by visa type.
     */
    public function getRevenueByVisaType(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $breakdown = $this->analyticsService->getRevenueByVisaType($startDate, $endDate);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'revenue_by_visa_type',
            $validated,
            $breakdown->count(),
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $breakdown,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    /**
     * Get revenue breakdown by country.
     */
    public function getRevenueByCountry(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $breakdown = $this->analyticsService->getRevenueByCountry($startDate, $endDate);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'revenue_by_country',
            $validated,
            $breakdown->count(),
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $breakdown,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    /**
     * Get revenue breakdown by processing tier.
     */
    public function getRevenueByTier(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $breakdown = $this->analyticsService->getRevenueByTier($startDate, $endDate);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'revenue_by_tier',
            $validated,
            $breakdown->count(),
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $breakdown,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    /**
     * Get revenue trends over time.
     */
    public function getRevenueTrends(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'period' => 'sometimes|in:daily,weekly,monthly,yearly',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);
        $period = $validated['period'] ?? 'daily';

        $trends = $this->analyticsService->getRevenueTrends($startDate, $endDate, $period);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'revenue_trends',
            $validated,
            $trends->count(),
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $trends,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'period' => $period,
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    // ==========================================
    // VISITOR ANALYTICS ENDPOINTS
    // ==========================================

    /**
     * Get visitors/applications by country.
     */
    public function getVisitorsByCountry(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $visitors = $this->analyticsService->getVisitorsByCountry($startDate, $endDate);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'visitors_by_country',
            $validated,
            $visitors->count(),
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $visitors,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    /**
     * Get approval and denial rates by country.
     */
    public function getApprovalRates(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $rates = $this->analyticsService->getApprovalRatesByCountry($startDate, $endDate);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'approval_rates',
            $validated,
            $rates->count(),
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $rates,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    /**
     * Get visitor trends over time.
     */
    public function getVisitorTrends(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'period' => 'sometimes|in:daily,weekly,monthly,yearly',
            'status' => 'sometimes|string',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);
        $period = $validated['period'] ?? 'daily';
        $status = $validated['status'] ?? null;

        $trends = $this->analyticsService->getVisitorTrends($startDate, $endDate, $period, $status);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'visitor_trends',
            $validated,
            $trends->count(),
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $trends,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'period' => $period,
                'status' => $status,
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

    /**
     * Get demographic breakdown.
     */
    public function getDemographics(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['end_date']);

        $demographics = $this->analyticsService->getDemographicBreakdown($startDate, $endDate);

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        $this->auditLogService->logAnalyticsAccess(
            $request->user(),
            'demographics',
            $validated,
            null,
            $executionTime
        );

        return response()->json([
            'success' => true,
            'data' => $demographics,
            'metadata' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'query_time_ms' => $executionTime,
            ],
        ]);
    }

}
