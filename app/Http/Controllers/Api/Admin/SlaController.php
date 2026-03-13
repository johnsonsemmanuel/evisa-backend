<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SlaMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaController extends Controller
{
    public function __construct(
        protected SlaMonitoringService $slaService
    ) {}

    /**
     * Get SLA dashboard metrics.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'metrics' => $this->slaService->getDashboardMetrics(),
        ]);
    }

    /**
     * Get applications at risk of SLA breach.
     */
    public function atRisk(Request $request): JsonResponse
    {
        $hours = $request->query('hours', 8);

        return response()->json([
            'applications' => $this->slaService->getAtRiskApplications((int) $hours),
        ]);
    }

    /**
     * Get breached applications.
     */
    public function breached(): JsonResponse
    {
        return response()->json([
            'applications' => $this->slaService->getBreachedApplications(),
        ]);
    }

    /**
     * Get historical SLA performance.
     */
    public function history(Request $request): JsonResponse
    {
        $days = $request->query('days', 30);

        return response()->json([
            'performance' => $this->slaService->getHistoricalPerformance((int) $days),
        ]);
    }

    /**
     * Trigger SLA warning notifications (for scheduled job).
     */
    public function sendWarnings(): JsonResponse
    {
        $results = $this->slaService->sendSlaWarnings();

        return response()->json([
            'message' => 'SLA warnings processed',
            'results' => $results,
        ]);
    }
}
