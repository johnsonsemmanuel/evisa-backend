<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Central cache key registry and caching utilities.
 * 
 * SECURITY: Never cache PII, payment data, audit logs, or authentication tokens.
 */
class CacheService
{
    // TTL Constants (in seconds)
    public const REFERENCE_DATA_TTL = 3600;      // 1 hour - visa types, countries, fees
    public const DASHBOARD_TTL = 900;             // 15 minutes - dashboard stats
    public const APPLICATION_TTL = 1800;          // 30 minutes - per-application computed data
    public const SHORT_TTL = 300;                 // 5 minutes - frequently changing data

    // Cache Key Prefixes
    public const PREFIX_VISA_TYPE = 'visa_types';
    public const PREFIX_VISA_FEE = 'visa_fees';
    public const PREFIX_FEE_WAIVER = 'fee_waivers';
    public const PREFIX_REASON_CODE = 'reason_codes';
    public const PREFIX_SERVICE_TIER = 'service_tiers';
    public const PREFIX_DASHBOARD = 'dashboard';
    public const PREFIX_APPLICATION = 'application';
    public const PREFIX_NATIONALITY = 'nationality';

    // Cache Tags
    public const TAG_REFERENCE_DATA = 'reference_data';
    public const TAG_DASHBOARD = 'dashboard';
    public const TAG_APPLICATIONS = 'applications';
    public const TAG_FEES = 'fees';

    /**
     * Generate cache key for visa types list.
     */
    public static function visaTypesKey(bool $activeOnly = true): string
    {
        return self::PREFIX_VISA_TYPE . ':' . ($activeOnly ? 'active' : 'all');
    }

    /**
     * Generate cache key for single visa type.
     */
    public static function visaTypeKey(int $id): string
    {
        return self::PREFIX_VISA_TYPE . ":{$id}";
    }

    /**
     * Generate cache key for visa fees.
     */
    public static function visaFeesKey(int $visaTypeId, string $nationality, string $tier): string
    {
        return self::PREFIX_VISA_FEE . ":{$visaTypeId}:{$nationality}:{$tier}";
    }

    /**
     * Generate cache key for fee waivers.
     */
    public static function feeWaiversKey(string $nationality): string
    {
        return self::PREFIX_FEE_WAIVER . ":{$nationality}";
    }

    /**
     * Generate cache key for reason codes.
     */
    public static function reasonCodesKey(?string $actionType = null): string
    {
        return self::PREFIX_REASON_CODE . ':' . ($actionType ?? 'all');
    }

    /**
     * Generate cache key for service tiers.
     */
    public static function serviceTiersKey(): string
    {
        return self::PREFIX_SERVICE_TIER . ':all';
    }

    /**
     * Generate cache key for dashboard metrics.
     */
    public static function dashboardKey(string $agency, ?int $missionId = null): string
    {
        $key = self::PREFIX_DASHBOARD . ":{$agency}";
        if ($missionId) {
            $key .= ":{$missionId}";
        }
        return $key;
    }

    /**
     * Generate cache key for application risk score.
     */
    public static function applicationRiskScoreKey(int $applicationId): string
    {
        return self::PREFIX_APPLICATION . ":{$applicationId}:risk_score";
    }

    /**
     * Generate cache key for application document checklist.
     */
    public static function applicationDocumentChecklistKey(int $applicationId): string
    {
        return self::PREFIX_APPLICATION . ":{$applicationId}:document_checklist";
    }

    /**
     * Generate cache key for nationality category.
     */
    public static function nationalityCategoryKey(string $nationality): string
    {
        return self::PREFIX_NATIONALITY . ":{$nationality}:category";
    }

    /**
     * Remember with graceful degradation.
     * Falls back to database if Redis is unavailable.
     */
    public static function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        try {
            if (empty($tags)) {
                return Cache::remember($key, $ttl, $callback);
            }
            
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache unavailable, falling back to database', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            return $callback();
        }
    }

    /**
     * Forget cache key with error handling.
     */
    public static function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning('Failed to forget cache key', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Flush cache tags with error handling.
     */
    public static function flushTags(array $tags): bool
    {
        try {
            Cache::tags($tags)->flush();
            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to flush cache tags', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Bust all reference data caches.
     */
    public static function bustReferenceData(): void
    {
        self::flushTags([self::TAG_REFERENCE_DATA, self::TAG_FEES]);
        
        Log::info('Reference data cache busted');
    }

    /**
     * Bust all dashboard caches.
     */
    public static function bustDashboard(): void
    {
        self::flushTags([self::TAG_DASHBOARD]);
        
        Log::info('Dashboard cache busted');
    }

    /**
     * Bust application-specific caches.
     */
    public static function bustApplication(int $applicationId): void
    {
        self::forget(self::applicationRiskScoreKey($applicationId));
        self::forget(self::applicationDocumentChecklistKey($applicationId));
        
        Log::info('Application cache busted', ['application_id' => $applicationId]);
    }
}
