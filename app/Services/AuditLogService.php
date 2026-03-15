<?php

namespace App\Services;

use App\Models\AnalyticsAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log analytics access.
     */
    public function logAnalyticsAccess(User $user, string $feature, array $params = [], ?int $resultCount = null, ?int $executionTimeMs = null): void
    {
        AnalyticsAuditLog::create([
            'user_id' => $user->id,
            'feature' => $feature,
            'action' => 'view',
            'parameters' => $params,
            'result_count' => $resultCount,
            'execution_time_ms' => $executionTimeMs,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Log AI assistant query.
     */
    public function logAIQuery(User $user, string $query, array $result, ?int $executionTimeMs = null): void
    {
        AnalyticsAuditLog::create([
            'user_id' => $user->id,
            'feature' => 'ai_assistant',
            'action' => 'query',
            'query_text' => $query,
            'parameters' => [
                'intent' => $result['intent'] ?? null,
                'entities' => $result['entities'] ?? [],
            ],
            'result_count' => isset($result['data']) && is_array($result['data']) ? count($result['data']) : null,
            'execution_time_ms' => $executionTimeMs,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Log export operation.
     */
    public function logExport(User $user, string $reportType, array $filters, string $format): void
    {
        AnalyticsAuditLog::create([
            'user_id' => $user->id,
            'feature' => 'export',
            'action' => $format,
            'parameters' => [
                'report_type' => $reportType,
                'filters' => $filters,
            ],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
