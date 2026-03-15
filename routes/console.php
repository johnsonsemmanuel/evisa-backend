<?php

use App\Notifications\ReconciliationFailedNotification;
use App\Jobs\CheckSlaBreaches;
use App\Jobs\ExpireEtas;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// GCB availability (every 5 min) — cache + notify finance on state change (SLA)
// ---------------------------------------------------------------------------
Schedule::command('gcb:check-availability')
    ->everyFiveMinutes()
    ->withoutOverlapping(4)
    ->appendOutputTo(storage_path('logs/gcb-availability.log'));

// ---------------------------------------------------------------------------
// Scheduled jobs (queue-based)
// ---------------------------------------------------------------------------
Schedule::job(new CheckSlaBreaches)->everyFifteenMinutes();
Schedule::job(new ExpireEtas)->daily();
Schedule::job(new \App\Jobs\CleanupExpiredBacs)->daily();

// ---------------------------------------------------------------------------
// CRITICAL — Payment reconciliation (daily, off-peak hours)
// ---------------------------------------------------------------------------
Schedule::command('reconcile:payments --gateway=gcb')
    ->dailyAt('02:00')
    ->withoutOverlapping(120)
    ->onFailure(function () {
        $email = config('mail.finance_alert');
        if ($email) {
            Notification::route('mail', $email)
                ->notify(new ReconciliationFailedNotification('gcb', 'Reconciliation command failed or did not complete.'));
        }
    })
    ->appendOutputTo(storage_path('logs/reconciliation.log'));

Schedule::command('reconcile:payments --gateway=paystack')
    ->dailyAt('02:30')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/reconciliation.log'));

// ---------------------------------------------------------------------------
// CRITICAL — Expired application cleanup
// ---------------------------------------------------------------------------
Schedule::command('applications:expire-pending')
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/cleanup.log'));

// ---------------------------------------------------------------------------
// HIGH — Aeropass retry for failed checks
// ---------------------------------------------------------------------------
Schedule::command('aeropass:retry-failed-checks')
    ->everyThirtyMinutes()
    ->withoutOverlapping(25)
    ->appendOutputTo(storage_path('logs/aeropass.log'));

// ---------------------------------------------------------------------------
// MEDIUM — Daily report generation (finance/management dashboard)
// ---------------------------------------------------------------------------
Schedule::command('reports:generate --type=daily')
    ->dailyAt('06:00')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/reports.log'));

// ---------------------------------------------------------------------------
// MEDIUM — Weekly summary report
// ---------------------------------------------------------------------------
Schedule::command('reports:generate --type=weekly')
    ->weeklyOn(1, '07:00')
    ->appendOutputTo(storage_path('logs/reports.log'));

// ---------------------------------------------------------------------------
// MEDIUM — Failed job monitoring (every 15 min)
// ---------------------------------------------------------------------------
Schedule::command('queue:monitor-failed-jobs --minutes=15')
    ->everyFifteenMinutes()
    ->appendOutputTo(storage_path('logs/queue.log'));

// ---------------------------------------------------------------------------
// Expire pending payments older than 24 hours
// ---------------------------------------------------------------------------
Schedule::command('payments:expire-pending')
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/cleanup.log'));

// ---------------------------------------------------------------------------
// LOW — Data retention policy (monthly, deep off-peak)
// ---------------------------------------------------------------------------
Schedule::command('data:apply-retention --execute')
    ->monthly()
    ->at('03:00')
    ->appendOutputTo(storage_path('logs/retention.log'));

// ---------------------------------------------------------------------------
// LOW — Audit log integrity check (daily)
// ---------------------------------------------------------------------------
Schedule::command('audit:verify-integrity')
    ->dailyAt('04:00')
    ->appendOutputTo(storage_path('logs/audit.log'));

// ---------------------------------------------------------------------------
// LOW — Cache warming after midnight maintenance window
// ---------------------------------------------------------------------------
Schedule::command('cache:warm')
    ->dailyAt('05:00')
    ->appendOutputTo(storage_path('logs/cache.log'));

// ---------------------------------------------------------------------------
// CRITICAL — Daily encrypted database backup (off-site + local retention)
// ---------------------------------------------------------------------------
Schedule::command('backup:database')
    ->dailyAt('01:00')
    ->timezone('UTC')
    ->withoutOverlapping(90)
    ->onFailure(function () {
        $email = config('mail.finance_alert') ?? config('mail.from.address');
        if ($email) {
            Notification::route('mail', $email)
                ->notify(new \App\Notifications\BackupFailedNotification('Scheduled backup:database command failed.'));
        }
        $webhook = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
        if ($webhook) {
            Notification::route('slack', $webhook)
                ->notify(new \App\Notifications\BackupFailedNotification('Scheduled backup:database command failed.'));
        }
    })
    ->appendOutputTo(storage_path('logs/backup.log'));

// ---------------------------------------------------------------------------
// CRITICAL — Weekly backup restore test (verify backups are restorable)
// ---------------------------------------------------------------------------
Schedule::command('backup:test-restore')
    ->weeklyOn(0, '04:00')
    ->timezone('UTC')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/backup-restore-test.log'));

// ---------------------------------------------------------------------------
// CRITICAL — Off-site file storage backup (documents: passport, visa)
// ---------------------------------------------------------------------------
Schedule::command('backup:files')
    ->everyFourHours()
    ->timezone('UTC')
    ->withoutOverlapping(120)
    ->onFailure(function () {
        $email = config('mail.finance_alert') ?? config('mail.from.address');
        if ($email) {
            Notification::route('mail', $email)
                ->notify(new \App\Notifications\BackupFailedNotification('[File storage] Scheduled backup:files failed.'));
        }
        $webhook = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
        if ($webhook) {
            Notification::route('slack', $webhook)
                ->notify(new \App\Notifications\BackupFailedNotification('[File storage] Scheduled backup:files failed.'));
        }
    })
    ->appendOutputTo(storage_path('logs/backup-files.log'));

// ---------------------------------------------------------------------------
// MEDIUM — Weekly storage integrity (orphaned DB records / orphaned files)
// ---------------------------------------------------------------------------
Schedule::command('storage:verify-integrity --report=' . storage_path('logs/storage-integrity') . ' --notify')
    ->weeklyOn(1, '03:00')
    ->timezone('UTC')
    ->appendOutputTo(storage_path('logs/storage-integrity.log'));
