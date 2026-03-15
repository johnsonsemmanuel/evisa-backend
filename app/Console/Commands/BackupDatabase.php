<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database
                            {--script= : Path to backup-database.sh (default: base_path/../scripts/backup-database.sh)}';

    protected $description = 'Run encrypted database backup (wraps scripts/backup-database.sh). Alerts on failure.';

    public function handle(): int
    {
        $scriptPath = $this->option('script') ?? base_path('../scripts/backup-database.sh');

        if (!is_readable($scriptPath)) {
            $this->error("Backup script not found or not readable: {$scriptPath}");
            $this->alertBackupFailed("Backup script not found: {$scriptPath}");
            return self::FAILURE;
        }

        $env = [
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => config('database.connections.mysql.password'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_PORT' => config('database.connections.mysql.port', '3306'),
            'BACKUP_ENCRYPTION_PASSWORD' => config('backup.encryption_password') ?? env('BACKUP_ENCRYPTION_PASSWORD'),
            'BACKUP_S3_BUCKET' => config('backup.s3_bucket') ?? env('BACKUP_S3_BUCKET'),
            'BACKUP_DIR' => config('backup.local_path') ?? env('BACKUP_DIR', '/var/backups/evisa'),
            'BACKUP_ENV_FILE' => base_path('.env'),
            'RETENTION_DAYS' => (string) config('backup.retention_days_local', 30),
        ];

        $process = new Process([$scriptPath], base_path('..'), array_filter($env));
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $process->getErrorOutput() ?: $process->getOutput();
            $this->error('Backup failed: ' . $output);
            $this->alertBackupFailed($output);
            return self::FAILURE;
        }

        $this->info('Backup completed successfully.');
        return self::SUCCESS;
    }

    protected function alertBackupFailed(string $details): void
    {
        $notification = new \App\Notifications\BackupFailedNotification($details);

        $adminEmail = config('mail.finance_alert') ?? config('security.monitoring.alert_email') ?? config('mail.from.address');
        if ($adminEmail) {
            try {
                Notification::route('mail', $adminEmail)->notify($notification);
            } catch (\Throwable $e) {
            }
        }

        $webhookUrl = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
        if ($webhookUrl) {
            try {
                Notification::route('slack', $webhookUrl)->notify($notification);
            } catch (\Throwable $e) {
            }
        }
    }
}
