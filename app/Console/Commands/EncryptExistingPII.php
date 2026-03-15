<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EncryptExistingPII Command
 * 
 * Encrypts existing plaintext PII in the database using AES-256.
 * This command is IDEMPOTENT - safe to run multiple times.
 * 
 * SECURITY:
 * - Processes records in chunks (1000 at a time) to avoid memory issues
 * - Uses database transactions per chunk for data integrity
 * - Skips already-encrypted records (detects Laravel's encryption format)
 * - Generates blind indexes for searchable fields
 * - Logs progress and failures (without logging plaintext values)
 * - Can be safely interrupted and resumed
 * 
 * USAGE:
 * php artisan pii:encrypt
 * php artisan pii:encrypt --dry-run  (preview without making changes)
 * php artisan pii:encrypt --chunk=500 (custom chunk size)
 */
class EncryptExistingPII extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pii:encrypt
                            {--dry-run : Preview changes without encrypting}
                            {--chunk=1000 : Number of records to process per chunk}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing plaintext PII in the database (idempotent)';

    /**
     * Fields to encrypt in the applications table.
     */
    protected array $encryptedFields = [
        'first_name_encrypted',
        'last_name_encrypted',
        'date_of_birth_encrypted',
        'passport_number_encrypted',
        'nationality_encrypted',
        'email_encrypted',
        'phone_encrypted',
        'profession_encrypted',
    ];

    /**
     * Statistics tracking.
     */
    protected int $totalProcessed = 0;
    protected int $totalEncrypted = 0;
    protected int $totalSkipped = 0;
    protected int $totalFailed = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $force = $this->option('force');

        $this->info('===========================================');
        $this->info('  PII Encryption Migration');
        $this->info('===========================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Count total records
        $totalRecords = Application::withoutGlobalScopes()->count();
        $this->info("Total applications: {$totalRecords}");
        $this->info("Chunk size: {$chunkSize}");
        $this->newLine();

        // Confirmation prompt
        if (!$force && !$dryRun) {
            if (!$this->confirm('This will encrypt PII in the database. Continue?')) {
                $this->warn('Operation cancelled.');
                return self::FAILURE;
            }
            $this->newLine();
        }

        // Start processing
        $startTime = now();
        $this->info('Starting encryption process...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->start();

        try {
            // Process in chunks to avoid memory issues
            Application::withoutGlobalScopes()
                ->chunkById($chunkSize, function ($applications) use ($dryRun, $progressBar) {
                    $this->processChunk($applications, $dryRun);
                    $progressBar->advance($applications->count());
                });

            $progressBar->finish();
            $this->newLine(2);

            // Display results
            $duration = now()->diffInSeconds($startTime);
            $this->displayResults($duration, $dryRun);

            Log::info('PII encryption completed', [
                'total_processed' => $this->totalProcessed,
                'total_encrypted' => $this->totalEncrypted,
                'total_skipped' => $this->totalSkipped,
                'total_failed' => $this->totalFailed,
                'duration_seconds' => $duration,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('Encryption process failed: ' . $e->getMessage());
            Log::error('PII encryption failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Process a chunk of applications.
     */
    protected function processChunk($applications, bool $dryRun): void
    {
        foreach ($applications as $application) {
            $this->totalProcessed++;

            try {
                $needsEncryption = false;
                $updates = [];

                // Check each encrypted field
                foreach ($this->encryptedFields as $field) {
                    $value = $application->getAttributeFromArray($field);

                    // Skip if null or empty
                    if ($value === null || $value === '') {
                        continue;
                    }

                    // Check if already encrypted
                    if ($this->isAlreadyEncrypted($value)) {
                        continue;
                    }

                    // Mark for encryption
                    $needsEncryption = true;

                    if (!$dryRun) {
                        // Encrypt the value
                        $updates[$field] = Crypt::encryptString($value);

                        // Generate blind index for searchable fields
                        if ($field === 'passport_number_encrypted') {
                            $updates['passport_number_idx'] = $this->generateBlindIndex($value);
                        } elseif ($field === 'email_encrypted') {
                            $updates['email_idx'] = $this->generateBlindIndex($value);
                        } elseif ($field === 'phone_encrypted') {
                            $updates['phone_idx'] = $this->generateBlindIndex($value);
                        }
                    }
                }

                if ($needsEncryption) {
                    if (!$dryRun) {
                        // Use DB transaction for each record
                        DB::transaction(function () use ($application, $updates) {
                            // Update using query builder to bypass model events
                            DB::table('applications')
                                ->where('id', $application->id)
                                ->update($updates);
                        });
                    }

                    $this->totalEncrypted++;
                } else {
                    $this->totalSkipped++;
                }

            } catch (\Exception $e) {
                $this->totalFailed++;

                Log::error('Failed to encrypt PII for application', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check if a value is already encrypted.
     */
    protected function isAlreadyEncrypted(string $value): bool
    {
        // Laravel encrypted strings start with "eyJpdiI6"
        if (str_starts_with($value, 'eyJpdiI6')) {
            return true;
        }

        // Try to decrypt - if successful, it's encrypted
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate blind index for searchable fields.
     */
    protected function generateBlindIndex(string $plaintext): string
    {
        $key = config('app.blind_index_key');

        if (!$key) {
            throw new \RuntimeException('BLIND_INDEX_KEY not configured');
        }

        return hash_hmac('sha256', strtoupper(trim($plaintext)), $key);
    }

    /**
     * Display results summary.
     */
    protected function displayResults(int $duration, bool $dryRun): void
    {
        $this->info('===========================================');
        $this->info('  Encryption Results');
        $this->info('===========================================');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', number_format($this->totalProcessed)],
                ['Encrypted', number_format($this->totalEncrypted)],
                ['Skipped (already encrypted)', number_format($this->totalSkipped)],
                ['Failed', number_format($this->totalFailed)],
                ['Duration', "{$duration} seconds"],
            ]
        );

        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN - No changes were made');
        } else {
            if ($this->totalFailed > 0) {
                $this->warn("⚠ {$this->totalFailed} records failed. Check logs for details.");
            } else {
                $this->info('✓ All records processed successfully');
            }
        }

        $this->newLine();
    }
}
