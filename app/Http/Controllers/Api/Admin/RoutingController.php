<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\AdvancedRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutingController extends Controller
{
    public function __construct(
        protected AdvancedRoutingService $routingService
    ) {}

    /**
     * Get routing configuration for an application.
     */
    public function getRouting(Request $request, Application $application): JsonResponse
    {
        $routing = $this->routingService->determineRouting($application);
        $suggestion = $this->routingService->suggestRouting($application);

        return response()->json([
            'application' => [
                'reference_number' => $application->reference_number,
                'visa_type' => $application->visaType?->name,
                'service_tier' => $application->serviceTier?->name,
                'nationality' => $application->nationality,
                'purpose' => $application->purpose_of_visit,
            ],
            'current_routing' => [
                'agency' => $application->assigned_agency,
                'sla_deadline' => $application->sla_deadline?->toIso8601String(),
            ],
            'determined_routing' => $routing,
            'suggestion' => $suggestion,
        ]);
    }

    /**
     * Route an application manually.
     */
    public function routeApplication(Request $request, Application $application): JsonResponse
    {
        $routedApp = $this->routingService->routeApplication($application);

        return response()->json([
            'message' => 'Application routed successfully',
            'application' => [
                'reference_number' => $routedApp->reference_number,
                'assigned_agency' => $routedApp->assigned_agency,
                'sla_deadline' => $routedApp->sla_deadline?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Re-route an application.
     */
    public function reRouteApplication(Request $request, Application $application): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $routedApp = $this->routingService->reRouteApplication($application, $validated['reason']);

        return response()->json([
            'message' => 'Application re-routed successfully',
            'application' => [
                'reference_number' => $routedApp->reference_number,
                'assigned_agency' => $routedApp->assigned_agency,
                'sla_deadline' => $routedApp->sla_deadline?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get routing statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->routingService->getRoutingStatistics();

        return response()->json(['statistics' => $stats]);
    }

    /**
     * Test routing for a hypothetical application.
     */
    public function testRouting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visa_type_id' => 'required|integer|exists:visa_types,id',
            'service_tier_id' => 'nullable|integer|exists:service_tiers,id',
            'nationality' => 'required|string|size:2',
            'purpose_of_visit' => 'required|string',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Create temporary application for testing
        $testApp = new Application([
            'visa_type_id' => $validated['visa_type_id'],
            'service_tier_id' => $validated['service_tier_id'],
            'nationality' => $validated['nationality'],
            'purpose_of_visit' => $validated['purpose_of_visit'],
            'duration_days' => $validated['duration_days'] ?? 30,
        ]);

        // Load relationships
        $testApp->visaType = \App\Models\VisaType::find($validated['visa_type_id']);
        if ($validated['service_tier_id']) {
            $testApp->serviceTier = \App\Models\ServiceTier::find($validated['service_tier_id']);
        }

        $routing = $this->routingService->determineRouting($testApp);
        $suggestion = $this->routingService->suggestRouting($testApp);

        return response()->json([
            'test_parameters' => $validated,
            'routing_result' => $routing,
            'recommendations' => $suggestion['recommendations'] ?? [],
        ]);
    }
}
