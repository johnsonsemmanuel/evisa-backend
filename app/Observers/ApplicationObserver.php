<?php

namespace App\Observers;

use App\Models\Application;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

/**
 * Application Observer for cache busting.
 * 
 * SECURITY: Never cache PII, payment data, or audit logs.
 * Only bust caches for computed/aggregated data.
 */
class ApplicationObserver
{
    /**
     * Handle the Application "updated" event.
     */
    public function updated(Application $application): void
    {
        // Bust dashboard cache when application status changes
        if ($application->isDirty('status')) {
            CacheService::bustDashboard();
            
            Log::info('Dashboard cache busted due to application status change', [
                'application_id' => $application->id,
                'old_status' => $application->getOriginal('status'),
                'new_status' => $application->status,
            ]);
        }

        // Bust application-specific caches
        CacheService::bustApplication($application->id);
    }

    /**
     * Handle the Application "created" event.
     */
    public function created(Application $application): void
    {
        // Bust dashboard cache when new application is created
        CacheService::bustDashboard();
        
        Log::info('Dashboard cache busted due to new application', [
            'application_id' => $application->id,
        ]);
    }

    /**
     * Handle the Application "deleted" event.
     */
    public function deleted(Application $application): void
    {
        // Bust dashboard cache when application is deleted
        CacheService::bustDashboard();
        
        // Bust application-specific caches
        CacheService::bustApplication($application->id);
        
        Log::info('Caches busted due to application deletion', [
            'application_id' => $application->id,
        ]);
    }
}
