<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\ExportService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected ExportService $exportService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Export analytics data to Excel.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'report_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'filters' => 'sometimes|array',
        ]);

        $data = $this->fetchReportData($validated);
        
        $metadata = [
            'admin_name' => $request->user()->name,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ];

        $filePath = $this->exportService->exportToExcel(
            $validated['report_type'],
            $data,
            $metadata
        );

        $this->auditLogService->logExport(
            $request->user(),
            $validated['report_type'],
            $validated,
            'excel'
        );

        return response()->streamDownload(function () use ($filePath) {
            echo file_get_contents($filePath);
            unlink($filePath); // Clean up after download
        }, basename($filePath), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export analytics data to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'report_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'filters' => 'sometimes|array',
        ]);

        $data = $this->fetchReportData($validated);
        
        $metadata = [
            'admin_name' => $request->user()->name,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ];

        $filePath = $this->exportService->exportToCsv(
            $validated['report_type'],
            $data,
            $metadata
        );

        $this->auditLogService->logExport(
            $request->user(),
            $validated['report_type'],
            $validated,
            'csv'
        );

        return response()->streamDownload(function () use ($filePath) {
            echo file_get_contents($filePath);
            unlink($filePath); // Clean up after download
        }, basename($filePath), [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Fetch report data based on report type.
     */
    protected function fetchReportData(array $params): array
    {
        $startDate = new \DateTime($params['start_date']);
        $endDate = new \DateTime($params['end_date']);

        return match($params['report_type']) {
            'revenue_total' => [
                'total' => $this->analyticsService->getTotalRevenue($startDate, $endDate),
            ],
            'revenue_by_visa_type' => $this->analyticsService->getRevenueByVisaType($startDate, $endDate)->toArray(),
            'revenue_by_country' => $this->analyticsService->getRevenueByCountry($startDate, $endDate)->toArray(),
            'revenue_by_tier' => $this->analyticsService->getRevenueByTier($startDate, $endDate)->toArray(),
            'revenue_trends' => $this->analyticsService->getRevenueTrends($startDate, $endDate, $params['filters']['period'] ?? 'daily')->toArray(),
            'visitors_by_country' => $this->analyticsService->getVisitorsByCountry($startDate, $endDate)->toArray(),
            'approval_rates' => $this->analyticsService->getApprovalRatesByCountry($startDate, $endDate)->toArray(),
            'visitor_trends' => $this->analyticsService->getVisitorTrends($startDate, $endDate, $params['filters']['period'] ?? 'daily')->toArray(),
            'demographics' => $this->analyticsService->getDemographicBreakdown($startDate, $endDate),
            default => [],
        };
    }
}
