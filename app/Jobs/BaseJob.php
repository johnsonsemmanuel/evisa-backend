<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Base job with production-grade retry, timeout, backoff and failed() handling.
 * All application jobs should extend this (or a subclass) for consistent behaviour.
 */
abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of job attempts */
    public int $tries = 3;

    /** Seconds after which the job is killed */
    public int $timeout = 120;

    /** Maximum number of unhandled exceptions before failing */
    public int $maxExceptions = 3;

    /** Backoff delays in seconds between retries (exponential: 30s, 2min, 5min) */
    public array $backoff = [30, 120, 300];

    /**
     * Get the application ID for logging (override in subclasses that have an application).
     */
    protected function getApplicationId(): ?int
    {
        return null;
    }

    /**
     * Get the user ID for logging (override in subclasses that have a user).
     */
    protected function getUserId(): ?int
    {
        return null;
    }

    /**
     * Get the application model for failed() handling (override if job has an application).
     */
    protected function getApplication(): ?\App\Models\Application
    {
        return null;
    }

    /**
     * Called when all retries are exhausted. Logs, notifies admin, and optionally
     * updates application state for business-critical jobs.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Job permanently failed', [
            'job' => static::class,
            'application_id' => $this->getApplicationId(),
            'user_id' => $this->getUserId(),
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $webhookUrl = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
        $adminEmail = config('security.monitoring.alert_email') ?: config('mail.from.address');
        if ($webhookUrl || $adminEmail) {
            try {
                $notifiable = (new \Illuminate\Notifications\AnonymousNotifiable());
                if ($webhookUrl) {
                    $notifiable->route('slack', $webhookUrl);
                }
                if ($adminEmail) {
                    $notifiable->route('mail', $adminEmail);
                }
                $notifiable->notify(new \App\Notifications\JobFailedNotification($this, $exception));
            } catch (Throwable $e) {
                Log::warning('Failed to send JobFailedNotification', ['error' => $e->getMessage()]);
            }
        }

        $application = $this->getApplication();
        if ($application) {
            $this->handleApplicationFailure($application, $exception);
        }
    }

    /**
     * Override in subclasses to update application status when job fails permanently
     * (e.g. mark Aeropass check failed, notify officer).
     */
    protected function handleApplicationFailure(\App\Models\Application $application, Throwable $exception): void
    {
        // Default: no application-level update. Override in ProcessInterpolCheck, etc.
    }
}
