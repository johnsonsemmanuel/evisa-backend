<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * VerifyAuditIntegrity Command
 * 
 * Daily verification of audit log integrity.
 * Implements ISO 27001 A.8.15 (Logging) - detect tampering with audit logs.
 * 
 * SECURITY: Computes checksums of audit logs and compares with previous checksums.
 * 
 * @package App\Console\Commands
 */
class VerifyAuditIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:verify-integrity 
                            {--date= : Specific date to verify (YYYY-MM-DD), defaults to yesterday}
                            {--alert : Send alert notifications if tampering detected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify audit log integrity by computing and comparing checksums';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') 
            ? \Carbon\Carbon::parse($this->option('date'))->toDateString()
            : now()->subDay()->toDateString();

        $this->info("Verifying audit log integrity for: {$date}");

        // Fetch all audit logs for the date
        $auditLogs = AuditLog::whereDate('created_at', $date)
            ->orderBy('id')
            ->get(['id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'created_at']);

        if ($auditLogs->isEmpty()) {
            $this->warn("No audit logs found for {$date}");
            return self::SUCCESS;
        }

        $recordCount = $auditLogs->count();
        $this->info("Found {$recordCount} audit log records");

        // Compute checksum of all IDs
        $idString = $auditLogs->pluck('id')->implode(',');
        $checksum = hash('sha256', $idString);

        // Compute data hash of critical fields
        $dataString = $auditLogs->map(function ($log) {
            return implode('|', [
                $log->id,
                $log->user_id ?? 'null',
                $log->action,
                $log->auditable_type ?? 'null',
                $log->auditable_id ?? 'null',
                $log->created_at->toIso8601String(),
            ]);
        })->implode('||');
        $dataHash = hash('sha256', $dataString);

        $this->line("Checksum: {$checksum}");
        $this->line("Data Hash: {$dataHash}");

        // Check if we have a previous checksum for this date
        $existingChecksum = DB::connection('audit')
            ->table('audit_checksums')
            ->where('audit_date', $date)
            ->first();

        if ($existingChecksum) {
            // Compare checksums
            if ($existingChecksum->checksum !== $checksum || $existingChecksum->data_hash !== $dataHash) {
                $this->error("⚠️  TAMPERING DETECTED!");
                $this->error("Expected checksum: {$existingChecksum->checksum}");
                $this->error("Actual checksum:   {$checksum}");
                $this->error("Expected data hash: {$existingChecksum->data_hash}");
                $this->error("Actual data hash:   {$dataHash}");
                $this->error("Expected record count: {$existingChecksum->record_count}");
                $this->error("Actual record count:   {$recordCount}");

                if ($this->option('alert')) {
                    $this->sendTamperingAlert($date, $existingChecksum, $checksum, $dataHash, $recordCount);
                }

                return self::FAILURE;
            }

            $this->info("✓ Audit log integrity verified - no tampering detected");
            $this->line("Previous verification: {$existingChecksum->verified_at}");
            
            return self::SUCCESS;
        }

        // No existing checksum - store the first one
        DB::connection('audit')->table('audit_checksums')->insert([
            'audit_date' => $date,
            'record_count' => $recordCount,
            'checksum' => $checksum,
            'data_hash' => $dataHash,
            'verified_at' => now(),
            'verified_by' => 'system',
        ]);

        $this->info("✓ Initial checksum stored for {$date}");
        $this->line("Future verifications will compare against this baseline");

        return self::SUCCESS;
    }

    /**
     * Send tampering alert to security team
     */
    private function sendTamperingAlert(
        string $date,
        object $expected,
        string $actualChecksum,
        string $actualDataHash,
        int $actualCount
    ): void {
        $this->warn("Sending tampering alert notification...");

        // Log to security channel
        \Log::channel('security')->critical('Audit log tampering detected', [
            'date' => $date,
            'expected_checksum' => $expected->checksum,
            'actual_checksum' => $actualChecksum,
            'expected_data_hash' => $expected->data_hash,
            'actual_data_hash' => $actualDataHash,
            'expected_count' => $expected->record_count,
            'actual_count' => $actualCount,
            'verified_at' => now()->toIso8601String(),
        ]);

        // In production, send notification to security team
        // Notification::route('mail', config('app.security_email'))
        //     ->notify(new AuditTamperingDetectedNotification($date, ...));

        $this->info("Alert logged to security channel");
    }
}
