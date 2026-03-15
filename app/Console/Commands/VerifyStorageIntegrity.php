<?php

namespace App\Console\Commands;

use App\Models\ApplicationDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VerifyStorageIntegrity extends Command
{
    protected $signature = 'storage:verify-integrity
                            {--report= : Path to write JSON report (optional)}
                            {--notify : Send notification if issues found}';

    protected $description = 'Weekly: verify document paths in DB exist on disk; report orphan DB records and orphan files.';

    public function handle(): int
    {
        $privateRoot = storage_path('app/private');
        $orphanedDb = [];
        $orphanedFiles = [];

        // 1) Orphaned DB records: stored_path in application_documents but file missing
        $documents = ApplicationDocument::query()->select('id', 'application_id', 'document_type', 'stored_path')->get();
        foreach ($documents as $doc) {
            $path = $doc->stored_path;
            if ($path === '' || $path === null) {
                continue;
            }
            $fullPath = $privateRoot . '/' . ltrim($path, '/');
            if (!is_file($fullPath)) {
                $orphanedDb[] = [
                    'id' => $doc->id,
                    'application_id' => $doc->application_id,
                    'document_type' => $doc->document_type,
                    'stored_path' => $path,
                ];
            }
        }

        // 2) Orphaned files: file on disk but no application_documents record
        $dbPaths = $documents->pluck('stored_path')->map(function ($p) {
            return $p ? trim($p, '/') : null;
        })->filter()->values()->unique()->flip()->all();

        $documentsDir = $privateRoot . '/documents';
        if (is_dir($documentsDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($documentsDir, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $fullPath = $item->getPathname();
                $relative = ltrim(str_replace($privateRoot, '', $fullPath), '/\\');
                $relative = str_replace('\\', '/', $relative);
                if (isset($dbPaths[$relative])) {
                    continue;
                }
                $orphanedFiles[] = [
                    'path' => $relative,
                    'size' => $item->getSize(),
                ];
            }
        }

        $orphanDbCount = count($orphanedDb);
        $orphanFileCount = count($orphanedFiles);

        $this->info("Documents in DB: " . $documents->count());
        $this->info("Orphaned DB records (path in DB, file missing): {$orphanDbCount}");
        $this->info("Orphaned files (file on disk, no DB record): {$orphanFileCount}");

        if ($orphanDbCount > 0) {
            $this->table(
                ['id', 'application_id', 'document_type', 'stored_path'],
                array_slice($orphanedDb, 0, 20)
            );
            if ($orphanDbCount > 20) {
                $this->line('... and ' . ($orphanDbCount - 20) . ' more.');
            }
        }

        if ($orphanFileCount > 0) {
            $this->line('Sample orphaned files (first 20):');
            foreach (array_slice($orphanedFiles, 0, 20) as $f) {
                $this->line('  ' . $f['path'] . ' (' . $f['size'] . ' bytes)');
            }
            if ($orphanFileCount > 20) {
                $this->line('... and ' . ($orphanFileCount - 20) . ' more.');
            }
        }

        $report = [
            'checked_at' => now()->toIso8601String(),
            'documents_in_db' => $documents->count(),
            'orphaned_db_records' => $orphanDbCount,
            'orphaned_files' => $orphanFileCount,
            'orphaned_db' => $orphanedDb,
            'orphaned_files_list' => $orphanedFiles,
        ];

        $reportPath = $this->option('report');
        if ($reportPath !== null && $reportPath !== '') {
            $reportPath = pathinfo($reportPath, PATHINFO_EXTENSION) === 'json'
                ? $reportPath
                : rtrim($reportPath, '/') . '/storage-integrity-' . now()->format('Y-m-d') . '.json';
            $dir = dirname($reportPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Report written to: {$reportPath}");
        }

        Log::info('Storage integrity check completed', [
            'orphaned_db' => $orphanDbCount,
            'orphaned_files' => $orphanFileCount,
        ]);

        if (($orphanDbCount > 0 || $orphanFileCount > 0) && $this->option('notify')) {
            $this->notifyIssues($orphanDbCount, $orphanFileCount, $reportPath ?? '');
        }

        return self::SUCCESS;
    }

    protected function notifyIssues(int $orphanDb, int $orphanFiles, string $reportPath): void
    {
        try {
            $notification = new \App\Notifications\BackupFailedNotification(
                "Storage integrity: {$orphanDb} orphaned DB record(s), {$orphanFiles} orphaned file(s). Report: {$reportPath}"
            );
            $adminEmail = config('mail.finance_alert') ?? config('security.monitoring.alert_email') ?? config('mail.from.address');
            if ($adminEmail) {
                \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)->notify($notification);
            }
            $webhook = config('logging.channels.slack.url') ?? env('LOG_SLACK_WEBHOOK_URL');
            if ($webhook) {
                \Illuminate\Support\Facades\Notification::route('slack', $webhook)->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('Storage integrity notification failed', ['error' => $e->getMessage()]);
        }
    }
}
