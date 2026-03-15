<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAeropassNominalCheck;
use App\Jobs\ProcessInterpolCheck;
use App\Models\Application;
use App\Models\InterpolCheck;
use App\Notifications\AeropassCheckFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RetryFailedAeropassChecks extends Command
{
    protected $signature = 'aeropass:retry-failed-checks
                            {--minutes=30 : Retry checks pending or failed for at least this many minutes}
                            {--limit=50 : Maximum number of retries per run}
                            {--async-timeout-hours=2 : Treat async nominal check as timed out after this many hours}';

    protected $description = 'Queue Aeropass/Interpol retries; handle async nominal check timeouts (re-submit or escalate)';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $limit = (int) $this->option('limit');
        $asyncTimeoutHours = (int) $this->option('async-timeout-hours');
        $cutoff = now()->subMinutes($minutes);
        $asyncCutoff = now()->subHours($asyncTimeoutHours);

        $queued = 0;

        // Legacy: InterpolCheck records (failed/pending)
        $checks = InterpolCheck::query()
            ->whereIn('status', ['failed', 'pending'])
            ->where('updated_at', '<', $cutoff)
            ->with('application')
            ->limit($limit)
            ->get();

        foreach ($checks as $check) {
            if (!$check->application) {
                continue;
            }
            ProcessInterpolCheck::dispatch($check->application_id)->onQueue('critical');
            $queued++;
        }

        // Async nominal check: status 'checking' and submitted > N hours ago — re-submit or escalate
        $staleChecking = Application::withoutGlobalScopes()
            ->where('aeropass_status', 'checking')
            ->where('aeropass_submitted_at', '<', $asyncCutoff)
            ->where('aeropass_retry_count', '<', 3)
            ->limit($limit)
            ->get();

        foreach ($staleChecking as $app) {
            $app->increment('aeropass_retry_count');
            ProcessAeropassNominalCheck::dispatch($app->fresh())->onQueue('critical');
            $queued++;
            Log::info('Aeropass async check re-submitted (timeout)', [
                'application_id' => $app->id,
                'retry_count' => $app->aeropass_retry_count,
            ]);
        }

        // After 3 re-submissions: mark check_failed and escalate to supervisor
        $escalate = Application::withoutGlobalScopes()
            ->where('aeropass_status', 'checking')
            ->where('aeropass_submitted_at', '<', $asyncCutoff)
            ->where('aeropass_retry_count', '>=', 3)
            ->limit($limit)
            ->get();

        foreach ($escalate as $app) {
            $app->update(['aeropass_status' => 'check_failed']);
            $email = config('security.monitoring.alert_email') ?? config('mail.from.address');
            if ($email) {
                try {
                    Notification::route('mail', $email)->notify(new AeropassCheckFailedNotification($app));
                } catch (\Throwable $e) {
                    Log::warning('Aeropass escalation notification failed', ['error' => $e->getMessage()]);
                }
            }
            Log::warning('Aeropass check marked check_failed after 3 timeouts — supervisor notified', [
                'application_id' => $app->id,
            ]);
        }

        if ($queued > 0 || $escalate->isNotEmpty()) {
            $this->info("Queued {$queued} Aeropass retries; escalated " . $escalate->count() . " to check_failed.");
        } else {
            $this->info('No Aeropass checks to retry.');
        }

        return self::SUCCESS;
    }
}
