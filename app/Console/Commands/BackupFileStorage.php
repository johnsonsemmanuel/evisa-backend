<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

class BackupFileStorage extends Command
{
    protected $signature = 'backup:files
                            {--script= : Path to backup-files.sh (default: base_path/../scripts/backup-files.sh)}';

    protected $description = 'Replicate private file storage (documents) to off-site. Incremental; alerts on failure or if last backup > 5 hours.';

    public function handle(): int
    {
        $maxAgeHours = config('backup.files_max_age_hours', 5);
        $markerPath = storage_path('app/' . config('backup.files_last_success_marker', 'backup-files-last-success.txt'));

        // Verify last backup completed within the last N hours
        if (is_file($markerPath)) {
            $mtime = filemtime($markerPath);
            $ageHours = (time() - $mtime) / 3600;
            if ($ageHours > $maxAgeHours) {
                $msg = "Last file backup was " . round($ageHours, 1) . " hours ago (max {$maxAgeHours}h). Run backup:files.";
                $this->warn($msg);
                $this->alertFileBackupFailed($msg);
                // Still run this backup below
            }
        } else {
            $this->info('No previous file backup marker found; running first backup.');
        }

        $scriptPath = $this->option('script') ?? base_path('../scripts/backup-files.sh');

        if (!is_readable($scriptPath)) {
            $this->error("Backup script not found or not readable: {$scriptPath}");
            $this->alertFileBackupFailed("Backup script not found: {$scriptPath}");
            return self::FAILURE;
        }

        $sourceDir = config('backup.files_source') ?: storage_path('app/private');
        $env = [
            'BACKUP_FILES_SOURCE' => $sourceDir,
            'BACKUP_FILES_S3_BUCKET' => config('backup.files_s3_bucket') ?? env('BACKUP_FILES_S3_BUCKET'),
            'BACKUP_FILES_S3_PREFIX' => config('backup.files_s3_prefix') ?? env('BACKUP_FILES_S3_PREFIX', 'files'),
            'BACKUP_FILES_RSYNC_DEST' => config('backup.files_rsync_dest') ?? env('BACKUP_FILES_RSYNC_DEST'),
            'BACKUP_ENV_FILE' => base_path('.env'),
        ];

        $process = new Process([$scriptPath], base_path('..'), array_filter($env));
        $process->setTimeout(7200); // 2 hours max for large syncs
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $process->getErrorOutput() ?: $process->getOutput();
            $this->error('File backup failed: ' . $output);
            $this->alertFileBackupFailed($output);
            return self::FAILURE;
        }

        touch($markerPath);
        $this->info('File backup completed successfully.');
        return self::SUCCESS;
    }

    protected function alertFileBackupFailed(string $details): void
    {
        $notification = new \App\Notifications\BackupFailedNotification(
            '[File storage] ' . $details
        );

        $adminEmail = config('mail.finance_alert') ?? config('security.monitoring.alert_email') ?? config('mail.from.address');
        if ($adminEmail) {
            try {
                Notification::route('mail', $adminEmail)->notify($notification);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $webhookUrl = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
        if ($webhookUrl) {
            try {
                Notification::route('slack', $webhookUrl)->notify($notification);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
