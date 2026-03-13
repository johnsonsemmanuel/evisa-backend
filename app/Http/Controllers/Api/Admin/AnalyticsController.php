<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
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
}
