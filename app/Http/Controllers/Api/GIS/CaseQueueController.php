<?php

namespace App\Http\Controllers\Api\GIS;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\RealTimeDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseQueueController extends Controller
{
    public function __construct(
        protected RealTimeDashboardService $dashboardService,
    ) {}

    /**
     * Get the GIS case queue with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Application::where('assigned_agency', 'gis')
            ->with([
                'visaType:id,name', 
                'assignedOfficer:id,first_name,last_name',
                'reviewingOfficer:id,first_name,last_name',
                'approvalOfficer:id,first_name,last_name',
            ]);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($queue = $request->query('queue')) {
            $query->where('current_queue', $queue);
        }

        if ($tier = $request->query('tier')) {
            $query->where('tier', $tier);
        }

        if ($search = $request->query('search')) {
            $query->where('reference_number', 'like', "%{$search}%");
        }

        $applications = $query->orderByRaw("
            CASE
                WHEN status = 'escalated' THEN 1
                WHEN status = 'pending_approval' THEN 2
                WHEN status = 'under_review' THEN 3
                WHEN status = 'submitted' THEN 4
                ELSE 5
            END
        ")->orderBy('sla_deadline', 'asc')
          ->paginate(20);

        return response()->json([
            'data' => \App\Http\Resources\GIS\CaseListResource::collection($applications->items()),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * Get a single case for review with all details.
     * SECURITY: Verify application belongs to GIS queue.
     */
    public function show(Application $application): JsonResponse
    {
        // IDOR Protection: Ensure application is in GIS queue
        if ($application->assigned_agency !== 'gis') {
            abort(403, 'This application is not assigned to GIS');
        }

        $application->load([
            'visaType:id,name,processing_time_days',
            'documents:id,application_id,document_type,verification_status,created_at',
            'statusHistory.changedByUser:id,first_name,last_name',
            'internalNotes.user:id,first_name,last_name',
            'payment:id,application_id,amount,status,gateway,paid_at',
            'user:id,first_name,last_name,email',
            'riskAssessment:id,application_id,risk_score,risk_level,risk_reasons',
            'assignedOfficer:id,first_name,last_name',
            'reviewingOfficer:id,first_name,last_name',
            'approvalOfficer:id,first_name,last_name',
        ]);

        return response()->json(new \App\Http\Resources\GIS\CaseDetailResource($application));
    }

    /**
     * Assign a case to the current officer.
     */
    public function assignToSelf(Request $request, Application $application): JsonResponse
    {
        if ($application->assigned_agency !== 'gis') {
            return response()->json(['message' => __('case.not_gis_case')], 422);
        }

        $application->update(['assigned_officer_id' => $request->user()->id]);
        broadcast(new \App\Events\OfficerAssigned($application->fresh(), $request->user()));

        return response()->json([
            'message'     => __('case.assigned'),
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Get dashboard metrics for GIS officers.
     */
    public function metrics(): JsonResponse
    {
        $metrics = $this->dashboardService->getCachedMetrics('gis');

        return response()->json($metrics);
    }

    /**
     * Get batch processing statistics.
     */
    public function batchStats(): JsonResponse
    {
        $stats = [
            'available_for_batch' => Application::where('assigned_agency', 'gis')
                ->whereIn('status', ['submitted', 'under_review'])
                ->count(),
            'by_status' => Application::where('assigned_agency', 'gis')
                ->whereIn('status', ['submitted', 'under_review', 'pending_approval'])
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'unassigned' => Application::where('assigned_agency', 'gis')
                ->whereNull('assigned_officer_id')
                ->whereIn('status', ['submitted', 'under_review'])
                ->count(),
        ];

        return response()->json(['stats' => $stats]);
    }
}