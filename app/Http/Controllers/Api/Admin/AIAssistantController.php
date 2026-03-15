<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\AIQueryProcessor;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIAssistantController extends Controller
{
    public function __construct(
        protected AIQueryProcessor $queryProcessor,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Process a natural language query.
     */
    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
            'conversation_id' => 'sometimes|string|uuid',
        ]);

        $startTime = microtime(true);
        
        $result = $this->queryProcessor->processQuery(
            $validated['query'],
            $request->user(),
            $validated['conversation_id'] ?? null
        );

        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        // Log the query
        $this->auditLogService->logAIQuery(
            $request->user(),
            $validated['query'],
            [
                'intent' => $result['query_intent'] ?? null,
                'entities' => $result['entities'] ?? [],
                'success' => $result['success'] ?? false,
            ],
            $executionTime
        );

        return response()->json($result);
    }

    /**
     * Clear conversation context.
     */
    public function clearContext(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|string|uuid',
        ]);

        $cleared = $this->queryProcessor->clearContext(
            $validated['conversation_id'],
            $request->user()
        );

        return response()->json([
            'success' => $cleared,
            'message' => $cleared ? 'Conversation context cleared' : 'No context found',
        ]);
    }

    /**
     * Get query suggestions.
     */
    public function getSuggestions(Request $request): JsonResponse
    {
        $suggestions = [
            'revenue' => [
                'How much revenue did we make last month?',
                'Show revenue breakdown by visa type',
                'Show revenue trend this year',
                'Compare revenue this month to last month',
            ],
            'visitors' => [
                'How many visitors last month?',
                'Show visitors by country',
                'Show visitors from Nigeria',
                'Show visitor trends this year',
            ],
            'approval_rates' => [
                'What\'s the approval rate for US applicants?',
                'Show approval rates by country',
                'Show denial rates last month',
            ],
            'demographics' => [
                'Show demographics breakdown',
                'Show age groups of applicants',
                'Show purpose of visit breakdown',
            ],
            'trends' => [
                'Show revenue trend last 30 days',
                'When was revenue highest this year?',
                'Show busiest period for applications',
            ],
        ];

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }
}
