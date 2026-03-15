<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MonitorFailedJobs extends Command
{
    protected $signature = 'queue:monitor-failed-jobs
                            {--minutes=15 : Look back this many minutes for new failures}
                            {--dry-run : Log only, do not send notifications}';

    protected $description = 'Check failed_jobs for new failures in the last N minutes and send digest alert to admin.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = $this->option('dry-run');

        $since = now()->subMinutes($minutes);
        $driver = config('queue.failed.driver');
        $table = config('queue.failed.table', 'failed_jobs');
        $connection = config('queue.failed.database') ?? config('database.default');

        if ($driver !== 'database-uuids' && $driver !== 'database') {
            $this->warn("Failed jobs driver is {$driver}. Monitoring uses failed_jobs table.");
        }

        $query = DB::connection($connection)->table($table)->where('failed_at', '>=', $since);

        $failed = $query->orderBy('failed_at', 'desc')->get();

        if ($failed->isEmpty()) {
            if ($this->output->isVerbose()) {
                $this->info("No failed jobs in the last {$minutes} minutes.");
            }
            return self::SUCCESS;
        }

        $count = $failed->count();
        $jobTypes = $failed->groupBy(function ($row) {
            $payload = json_decode($row->payload, true);
            return $payload['displayName'] ?? $payload['data']['commandName'] ?? 'Unknown';
        })->map->count()->sortDesc();

        $this->error("Found {$count} failed job(s) in the last {$minutes} minutes.");
        foreach ($jobTypes as $name => $c) {
            $this->line("  - " . class_basename($name) . ": {$c}");
        }

        Log::warning('Failed jobs digest', [
            'count' => $count,
            'minutes' => $minutes,
            'job_types' => $jobTypes->all(),
            'failed_at_range' => [$since->toIso8601String(), now()->toIso8601String()],
        ]);

        if (!$dryRun) {
            $this->sendDigestAlert($count, $jobTypes, $minutes);
        } else {
            $this->info('Dry run: no notification sent.');
        }

        return self::SUCCESS;
    }

    private function sendDigestAlert(int $count, $jobTypes, int $minutes): void
    {
        $webhookUrl = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
        $adminEmail = config('security.monitoring.alert_email') ?: config('mail.from.address');

        $summary = $jobTypes->map(fn ($c, $name) => class_basename($name) . ': ' . $c)->implode(', ');

        if ($webhookUrl) {
            try {
                $notifiable = (new \Illuminate\Notifications\AnonymousNotifiable())->route('slack', $webhookUrl);
                $notifiable->notify(new \App\Notifications\FailedJobsDigestNotification($count, $jobTypes->all(), $minutes));
            } catch (\Throwable $e) {
                Log::warning('MonitorFailedJobs: failed to send Slack digest', ['error' => $e->getMessage()]);
            }
        }

        if ($adminEmail) {
            try {
                Notification::route('mail', $adminEmail)
                    ->notify(new \App\Notifications\FailedJobsDigestNotification($count, $jobTypes->all(), $minutes));
            } catch (\Throwable $e) {
                Log::warning('MonitorFailedJobs: failed to send email digest', ['error' => $e->getMessage()]);
            }
        }
    }
}
