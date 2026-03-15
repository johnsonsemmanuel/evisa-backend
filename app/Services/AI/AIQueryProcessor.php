<?php

namespace App\Services\AI;

use App\Services\AnalyticsService;
use App\Models\AIConversationContext;
use App\Models\User;
use Illuminate\Support\Str;

class AIQueryProcessor
{
    public function __construct(
        protected IntentClassifier $intentClassifier,
        protected EntityExtractor $entityExtractor,
        protected ResponseFormatter $responseFormatter,
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Process a natural language query.
     */
    public function processQuery(string $query, User $user, ?string $conversationId = null): array
    {
        $startTime = microtime(true);
        
        // Load or create conversation context
        $conversationId = $conversationId ?? Str::uuid()->toString();
        $context = $this->loadContext($conversationId, $user);
        
        // Resolve references using context
        $resolvedQuery = $this->resolveReferences($query, $context);
        
        // Classify intent
        $intent = $this->intentClassifier->classify($resolvedQuery);
        $confidence = $this->intentClassifier->getConfidence($resolvedQuery, $intent);
        
        if ($intent === 'unknown' || $confidence < 50) {
            return $this->handleUnknownIntent($query, $conversationId);
        }
        
        // Extract entities
        $entities = $this->entityExtractor->extract($resolvedQuery, $intent);
        
        // Execute query
        try {
            $data = $this->executeQuery($intent, $entities);
        } catch (\Exception $e) {
            return $this->handleQueryError($e, $query, $conversationId);
        }
        
        // Format response
        $response = $this->responseFormatter->format($data, $intent, $entities);
        
        // Generate suggestions
        $suggestions = $this->generateSuggestions($intent, $entities);
        
        // Update context
        $this->updateContext($conversationId, $user, $query, $intent, $entities);
        
        $executionTime = (int)((microtime(true) - $startTime) * 1000);
        
        return [
            'success' => true,
            'answer' => $response['answer'],
            'structured_data' => $response['structured_data'],
            'visualization' => $response['visualization'],
            'query_intent' => $intent,
            'confidence' => $confidence,
            'entities' => $entities,
            'suggestions' => $suggestions,
            'conversation_id' => $conversationId,
            'execution_time_ms' => $executionTime,
        ];
    }

    /**
     * Execute query based on intent and entities.
     */
    protected function executeQuery(string $intent, array $entities): mixed
    {
        $startDate = $entities['time_period']['start'] ?? now()->subDays(30);
        $endDate = $entities['time_period']['end'] ?? now();
        
        return match($intent) {
            'revenue_total' => $this->analyticsService->getTotalRevenue($startDate, $endDate),
            'revenue_breakdown' => $this->getRevenueBreakdown($startDate, $endDate, $entities),
            'revenue_trend' => $this->analyticsService->getRevenueTrends(
                $startDate, 
                $endDate, 
                $entities['period_granularity'] ?? 'daily'
            ),
            'visitor_count' => $this->getVisitorCount($startDate, $endDate),
            'visitor_breakdown' => $this->analyticsService->getVisitorsByCountry($startDate, $endDate),
            'approval_rate' => $this->analyticsService->getApprovalRatesByCountry($startDate, $endDate),
            'demographics' => $this->analyticsService->getDemographicBreakdown($startDate, $endDate),
            'peak_analysis' => $this->analyticsService->identifyPeakPeriods(
                $startDate, 
                $endDate, 
                $entities['metric'] ?? 'revenue'
            ),
            default => throw new \Exception("Unsupported intent: {$intent}"),
        };
    }

    /**
     * Get revenue breakdown based on entities.
     */
    protected function getRevenueBreakdown($startDate, $endDate, array $entities)
    {
        // Determine breakdown type from query context
        if (isset($entities['country'])) {
            return $this->analyticsService->getRevenueByCountry($startDate, $endDate)
                ->where('country', $entities['country']);
        }
        
        // Default to visa type breakdown
        return $this->analyticsService->getRevenueByVisaType($startDate, $endDate);
    }

    /**
     * Get visitor count.
     */
    protected function getVisitorCount($startDate, $endDate): int
    {
        return \App\Models\Application::whereBetween('created_at', [$startDate, $endDate])->count();
    }

    /**
     * Resolve references in query using context.
     */
    protected function resolveReferences(string $query, ?AIConversationContext $context): string
    {
        if (!$context || !$context->context_data) {
            return $query;
        }
        
        $contextData = $context->context_data;
        
        // Resolve time references
        if (stripos($query, 'same period') !== false && isset($contextData['last_entities']['time_period'])) {
            $lastPeriod = $contextData['last_entities']['time_period']['label'];
            $query = str_ireplace('same period', $lastPeriod, $query);
        }
        
        return $query;
    }

    /**
     * Generate follow-up suggestions.
     */
    protected function generateSuggestions(string $intent, array $entities): array
    {
        return match($intent) {
            'revenue_total' => [
                'Show revenue breakdown by visa type',
                'Compare to previous month',
                'Show revenue trend',
            ],
            'revenue_breakdown' => [
                'Show revenue trend over time',
                'Compare to previous period',
                'Show top 5 countries',
            ],
            'visitor_count' => [
                'Show visitors by country',
                'Show approval rates',
                'Show visitor trends',
            ],
            'visitor_breakdown' => [
                'Show approval rates for these countries',
                'Show demographics',
                'Compare to previous month',
            ],
            'approval_rate' => [
                'Show demographics',
                'Show visitor trends',
                'Compare to previous period',
            ],
            default => [
                'Show total revenue',
                'Show visitor statistics',
                'Show approval rates',
            ],
        };
    }

    /**
     * Load conversation context.
     */
    protected function loadContext(string $conversationId, User $user): ?AIConversationContext
    {
        return AIConversationContext::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Update conversation context.
     */
    protected function updateContext(string $conversationId, User $user, string $query, string $intent, array $entities): void
    {
        $context = AIConversationContext::firstOrNew([
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
        ]);
        
        $contextData = $context->context_data ?? ['messages' => []];
        
        // Add new message to history
        $contextData['messages'][] = [
            'query' => $query,
            'intent' => $intent,
            'entities' => $entities,
            'timestamp' => now()->toIso8601String(),
        ];
        
        // Keep only last 10 messages
        if (count($contextData['messages']) > 10) {
            array_shift($contextData['messages']);
        }
        
        $contextData['last_intent'] = $intent;
        $contextData['last_entities'] = $entities;
        
        $context->context_data = $contextData;
        $context->last_query = $query;
        $context->last_intent = $intent;
        $context->message_count = count($contextData['messages']);
        $context->expires_at = now()->addMinutes(30);
        
        $context->save();
    }

    /**
     * Handle unknown intent.
     */
    protected function handleUnknownIntent(string $query, string $conversationId): array
    {
        return [
            'success' => false,
            'error' => 'I couldn\'t understand your question. Please try rephrasing it.',
            'answer' => "I'm not sure how to answer that. Here are some examples of questions I can help with:\n\n" .
                       "- How much revenue did we make last month?\n" .
                       "- Show me visitors from Nigeria\n" .
                       "- What's the approval rate for US applicants?\n" .
                       "- Show revenue trend this year\n" .
                       "- How many applications last week?",
            'suggestions' => [
                'Show total revenue last month',
                'Show visitors by country',
                'Show approval rates',
            ],
            'conversation_id' => $conversationId,
        ];
    }

    /**
     * Handle query execution error.
     */
    protected function handleQueryError(\Exception $e, string $query, string $conversationId): array
    {
        \Log::error('AI Query Error', [
            'query' => $query,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return [
            'success' => false,
            'error' => 'An error occurred while processing your query. Please try again or rephrase your question.',
            'answer' => 'Sorry, I encountered an error while processing your request. Please try a different question.',
            'suggestions' => [
                'Show total revenue',
                'Show visitor statistics',
                'Show approval rates',
            ],
            'conversation_id' => $conversationId,
        ];
    }

    /**
     * Clear conversation context.
     */
    public function clearContext(string $conversationId, User $user): bool
    {
        return AIConversationContext::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->delete() > 0;
    }
}
