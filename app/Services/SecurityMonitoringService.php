<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Government-Grade Security Monitoring Service
 * Implements breach detection, anomaly detection, and security alerting.
 */
class SecurityMonitoringService
{
    /**
     * Thresholds for anomaly detection.
     */
    protected array $thresholds = [
        'failed_logins_per_hour' => 10,
        'api_requests_per_minute' => 100,
        'document_downloads_per_hour' => 50,
        'status_changes_per_hour' => 20,
        'file_uploads_per_hour' => 30,
    ];

    /**
     * Track and analyze security events.
     */
    public function trackEvent(string $eventType, array $data): void
    {
        $key = $this->getEventKey($eventType, $data);
        $count = $this->incrementCounter($key);

        // Check for anomalies
        if ($this->isAnomalous($eventType, $count)) {
            $this->triggerAlert($eventType, $data, $count);
        }

        // Log event
        $this->logSecurityEvent($eventType, $data);
    }

    /**
     * Track failed login attempt.
     */
    public function trackFailedLogin(string $email, string $ipAddress): void
    {
        $this->trackEvent('failed_login', [
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);

        // Check for distributed brute force (same email, multiple IPs)
        $this->checkDistributedAttack($email);
    }

    /**
     * Track suspicious API usage.
     */
    public function trackApiUsage(string $endpoint, string $userId, string $ipAddress): void
    {
        $this->trackEvent('api_request', [
            'endpoint' => $endpoint,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Track document access.
     */
    public function trackDocumentAccess(int $documentId, int $userId, string $ipAddress): void
    {
        $this->trackEvent('document_access', [
            'document_id' => $documentId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
        ]);

        // Check for bulk download attempts
        $this->checkBulkDownload($userId);
    }

    /**
     * Track application status change.
     */
    public function trackStatusChange(int $applicationId, string $oldStatus, string $newStatus, int $userId): void
    {
        $this->trackEvent('status_change', [
            'application_id' => $applicationId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => $userId,
        ]);

        // Check for suspicious approval patterns
        if ($newStatus === 'approved') {
            $this->checkApprovalPattern($userId);
        }
    }

    /**
     * Check for distributed brute force attack.
     */
    protected function checkDistributedAttack(string $email): void
    {
        $key = 'security:distributed_attack:' . sha1($email);
        $ips = Cache::get($key, []);

        if (count($ips) >= 5) {
            $this->triggerCriticalAlert('Distributed brute force attack detected', [
                'email' => $email,
                'unique_ips' => count($ips),
                'ips' => array_slice($ips, 0, 10),
            ]);
        }
    }

    /**
     * Check for bulk document download attempts.
     */
    protected function checkBulkDownload(int $userId): void
    {
        $key = 'security:bulk_download:' . $userId;
        $count = Cache::get($key, 0);

        if ($count >= $this->thresholds['document_downloads_per_hour']) {
            $this->triggerAlert('bulk_download_attempt', [
                'user_id' => $userId,
                'downloads' => $count,
            ], $count);
        }
    }

    /**
     * Check for suspicious approval patterns.
     */
    protected function checkApprovalPattern(int $userId): void
    {
        $key = 'security:approvals:' . $userId;
        $count = $this->incrementCounter($key, 3600);

        // Alert if officer approves too many applications in short time
        if ($count >= 20) {
            $this->triggerAlert('suspicious_approval_pattern', [
                'user_id' => $userId,
                'approvals_per_hour' => $count,
            ], $count);
        }
    }

    /**
     * Get event key for caching.
     */
    protected function getEventKey(string $eventType, array $data): string
    {
        $identifier = $data['ip_address'] ?? $data['user_id'] ?? 'unknown';
        return "security:{$eventType}:" . sha1($identifier);
    }

    /**
     * Increment counter with TTL.
     */
    protected function incrementCounter(string $key, int $ttl = 3600): int
    {
        $count = Cache::get($key, 0) + 1;
        Cache::put($key, $count, $ttl);
        return $count;
    }

    /**
     * Check if event count is anomalous.
     */
    protected function isAnomalous(string $eventType, int $count): bool
    {
        $thresholdMap = [
            'failed_login' => $this->thresholds['failed_logins_per_hour'],
            'api_request' => $this->thresholds['api_requests_per_minute'],
            'document_access' => $this->thresholds['document_downloads_per_hour'],
            'status_change' => $this->thresholds['status_changes_per_hour'],
        ];

        $threshold = $thresholdMap[$eventType] ?? 100;
        return $count >= $threshold;
    }

    /**
     * Trigger security alert.
     */
    protected function triggerAlert(string $eventType, array $data, int $count): void
    {
        Log::channel('security')->warning("Security alert: {$eventType}", [
            'event_type' => $eventType,
            'count' => $count,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
            'severity' => 'HIGH',
        ]);

        // Store alert for dashboard
        Cache::put('security:alert:' . uniqid(), [
            'type' => $eventType,
            'data' => $data,
            'count' => $count,
            'timestamp' => now(),
        ], 86400);
    }

    /**
     * Trigger critical security alert.
     */
    protected function triggerCriticalAlert(string $message, array $data): void
    {
        Log::channel('security')->critical("CRITICAL SECURITY ALERT: {$message}", [
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
            'severity' => 'CRITICAL',
            'action_required' => true,
        ]);

        // In production, this would:
        // 1. Send email to security team
        // 2. Send SMS alert
        // 3. Create incident ticket
        // 4. Potentially auto-block IP
    }

    /**
     * Log security event to audit log.
     */
    protected function logSecurityEvent(string $eventType, array $data): void
    {
        AuditLog::create([
            'user_id' => $data['user_id'] ?? null,
            'action' => 'security.' . $eventType,
            'auditable_type' => 'Security',
            'auditable_id' => 0,
            'old_values' => null,
            'new_values' => $data,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get security dashboard metrics.
     */
    public function getDashboardMetrics(): array
    {
        return [
            'failed_logins_24h' => $this->getMetric('failed_login', 86400),
            'suspicious_activities' => $this->getActiveAlerts(),
            'blocked_ips' => $this->getBlockedIps(),
            'anomalies_detected' => $this->getAnomalyCount(),
        ];
    }

    /**
     * Get metric count for time period.
     */
    protected function getMetric(string $eventType, int $seconds): int
    {
        // In production, query from time-series database
        return Cache::get("security:metric:{$eventType}", 0);
    }

    /**
     * Get active security alerts.
     */
    protected function getActiveAlerts(): array
    {
        // In production, query from alerts table
        return [];
    }

    /**
     * Get blocked IP addresses.
     */
    protected function getBlockedIps(): array
    {
        // In production, query from blocklist
        return [];
    }

    /**
     * Get anomaly count.
     */
    protected function getAnomalyCount(): int
    {
        return Cache::get('security:anomaly_count', 0);
    }
}
