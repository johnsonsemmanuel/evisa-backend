<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Enums\ApplicationStatus;
use App\Enums\PaymentStatus;

class VerifyDatabaseIntegrity extends Command
{
    protected $signature = 'db:verify-integrity {--verbose : Show detailed output}';
    protected $description = 'Verify database integrity constraints and relationships';

    private int $passed = 0;
    private int $failed = 0;
    private int $warnings = 0;

    public function handle(): int
    {
        $this->info('========================================');
        $this->info('DATABASE INTEGRITY VERIFICATION');
        $this->info('========================================');
        $this->newLine();

        // Run all checks
        $this->checkMonetaryColumns();
        $this->checkForeignKeyIndexes();
        $this->checkOrphanedPayments();
        $this->checkBorderVerificationOneToOne();
        $this->checkRiskScoreIntegrity();
        $this->checkSoftDeleteIntegrity();
        $this->checkEnumConsistency();
        $this->checkTimestampIntegrity();
        $this->checkUniqueConstraints();

        // Display results
        $this->newLine();
        $this->info('========================================');
        $this->info('VERIFICATION RESULTS');
        $this->info('========================================');
        $this->line("<fg=green>Passed: {$this->passed}</>");
        $this->line("<fg=red>Failed: {$this->failed}</>");
        $this->line("<fg=yellow>Warnings: {$this->warnings}</>");
        $this->newLine();

        if ($this->failed === 0) {
            $this->info('✓ All critical integrity checks passed!');
            if ($this->warnings > 0) {
                $this->warnCheck('⚠ Some warnings were found - review recommended');
            }
            return Command::SUCCESS;
        } else {
            $this->error('✗ Some critical checks failed - immediate action required');
            return Command::FAILURE;
        }
    }

    private function checkMonetaryColumns(): void
    {
        $this->info('1. MONETARY COLUMNS CHECK');
        $this->line('-----------------------------------');

        $columns = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND COLUMN_TYPE IN ('decimal(8,2)','decimal(10,2)','float','double')
              AND COLUMN_NAME IN ('amount','fee','price','total','balance')
        ");

        if (empty($columns)) {
            $this->pass('No DECIMAL monetary columns found');
        } else {
            $this->failCheck('Found DECIMAL monetary columns:');
            foreach ($columns as $col) {
                $this->line("  - {$col->TABLE_NAME}.{$col->COLUMN_NAME} ({$col->COLUMN_TYPE})");
            }
        }

        $this->newLine();
    }

    private function checkForeignKeyIndexes(): void
    {
        $this->info('2. FOREIGN KEY INDEXES CHECK');
        $this->line('-----------------------------------');

        $missing = DB::select("
            SELECT fk.TABLE_NAME, fk.COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE fk
            LEFT JOIN INFORMATION_SCHEMA.STATISTICS idx 
                ON fk.TABLE_NAME = idx.TABLE_NAME 
                AND fk.COLUMN_NAME = idx.COLUMN_NAME
                AND idx.TABLE_SCHEMA = DATABASE()
            WHERE fk.TABLE_SCHEMA = DATABASE()
                AND fk.REFERENCED_TABLE_NAME IS NOT NULL
                AND idx.INDEX_NAME IS NULL
        ");

        if (empty($missing)) {
            $this->pass('All FK columns have indexes');
        } else {
            $this->failCheck('FK columns missing indexes:');
            foreach ($missing as $col) {
                $this->line("  - {$col->TABLE_NAME}.{$col->COLUMN_NAME}");
            }
        }

        $this->newLine();
    }

    private function checkOrphanedPayments(): void
    {
        $this->info('3. ORPHANED PAYMENT RECORDS CHECK');
        $this->line('-----------------------------------');

        $count = DB::table('payments as p')
            ->leftJoin('applications as a', 'p.application_id', '=', 'a.id')
            ->whereNull('a.id')
            ->count();

        if ($count === 0) {
            $this->pass('No orphaned payment records');
        } else {
            $this->failCheck("Found {$count} orphaned payment records");
        }

        $this->newLine();
    }

    private function checkBorderVerificationOneToOne(): void
    {
        $this->info('4. BORDER VERIFICATION ONE-TO-ONE CHECK');
        $this->line('-----------------------------------');

        $duplicates = DB::table('border_verifications')
            ->select('application_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('application_id')
            ->having('cnt', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->pass('All border verifications are one-to-one');
        } else {
            $this->failCheck('Found applications with multiple border verifications:');
            foreach ($duplicates as $dup) {
                $this->line("  - Application ID {$dup->application_id} has {$dup->cnt} verifications");
            }
        }

        $this->newLine();
    }

    private function checkRiskScoreIntegrity(): void
    {
        $this->info('5. RISK SCORE HISTORY INTEGRITY CHECK');
        $this->line('-----------------------------------');

        $invalid = DB::table('risk_scores')
            ->select('application_id', DB::raw('SUM(is_current) as current_count'))
            ->groupBy('application_id')
            ->havingRaw('current_count != 1')
            ->get();

        if ($invalid->isEmpty()) {
            $this->pass('All applications have exactly one current risk score');
        } else {
            $this->failCheck('Applications with invalid risk score state:');
            foreach ($invalid as $inv) {
                $this->line("  - Application ID {$inv->application_id} has {$inv->current_count} current risk scores");
            }
        }

        $this->newLine();
    }

    private function checkSoftDeleteIntegrity(): void
    {
        $this->info('6. SOFT DELETE INTEGRITY CHECK');
        $this->line('-----------------------------------');

        $orphaned = DB::table('payments as p')
            ->join('applications as a', 'p.application_id', '=', 'a.id')
            ->whereNotNull('a.deleted_at')
            ->whereNull('p.deleted_at')
            ->count();

        if ($orphaned === 0) {
            $this->pass('No active payments reference deleted applications');
        } else {
            $this->warnCheck("Found {$orphaned} active payments referencing deleted applications");
        }

        $this->newLine();
    }

    private function checkEnumConsistency(): void
    {
        $this->info('7. ENUM VALUE CONSISTENCY CHECK');
        $this->line('-----------------------------------');

        // Check application statuses
        $validStatuses = array_map(fn($case) => $case->value, ApplicationStatus::cases());
        $invalidAppStatuses = DB::table('applications')
            ->whereNotIn('status', $validStatuses)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        if ($invalidAppStatuses->isEmpty()) {
            $this->pass('All application statuses are valid');
        } else {
            $this->failCheck('Found invalid application statuses:');
            foreach ($invalidAppStatuses as $status) {
                $this->line("  - \"{$status->status}\" ({$status->count} records)");
            }
        }

        // Check payment statuses
        $validPaymentStatuses = array_map(fn($case) => $case->value, PaymentStatus::cases());
        $invalidPaymentStatuses = DB::table('payments')
            ->whereNotIn('status', $validPaymentStatuses)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        if ($invalidPaymentStatuses->isEmpty()) {
            $this->pass('All payment statuses are valid');
        } else {
            $this->failCheck('Found invalid payment statuses:');
            foreach ($invalidPaymentStatuses as $status) {
                $this->line("  - \"{$status->status}\" ({$status->count} records)");
            }
        }

        $this->newLine();
    }

    private function checkTimestampIntegrity(): void
    {
        $this->info('8. TIMESTAMP INTEGRITY CHECK');
        $this->line('-----------------------------------');

        $tables = ['applications', 'payments', 'users', 'application_documents'];
        $issues = [];

        foreach ($tables as $table) {
            $count = DB::table($table)
                ->whereColumn('created_at', '>', 'updated_at')
                ->count();
            
            if ($count > 0) {
                $issues[] = "{$table}: {$count} records";
            }
        }

        if (empty($issues)) {
            $this->pass('All timestamps are consistent');
        } else {
            $this->failCheck('Found timestamp inconsistencies:');
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
        }

        $this->newLine();
    }

    private function checkUniqueConstraints(): void
    {
        $this->info('9. UNIQUE CONSTRAINT VERIFICATION');
        $this->line('-----------------------------------');

        $duplicates = DB::table('applications')
            ->select('reference_number', DB::raw('COUNT(*) as count'))
            ->groupBy('reference_number')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->pass('All reference numbers are unique');
        } else {
            $this->failCheck('Found duplicate reference numbers:');
            foreach ($duplicates as $dup) {
                $this->line("  - {$dup->reference_number} ({$dup->count} times)");
            }
        }

        $this->newLine();
    }

    private function pass(string $message): void
    {
        $this->line("<fg=green>✓ PASSED</> - {$message}");
        $this->passed++;
    }

    protected function failCheck(string $message): void
    {
        $this->line("<fg=red>✗ FAILED</> - {$message}");
        $this->failed++;
    }

    protected function warnCheck(string $message): void
    {
        $this->line("<fg=yellow>⚠ WARNING</> - {$message}");
        $this->warnings++;
    }
}
