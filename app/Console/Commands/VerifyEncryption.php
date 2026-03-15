<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * VerifyEncryption Command
 * 
 * Spot-checks random records to verify encryption/decryption works correctly.
 * This command helps ensure:
 * - Encryption is working properly
 * - Decryption round-trips correctly
 * - Blind indexes match encrypted values
 * - No data corruption occurred during migration
 * 
 * USAGE:
 * php artisan pii:verify
 * php artisan pii:verify --count=20  (check 20 random records)
 * php artisan pii:verify --verbose   (show detailed output)
 */
class VerifyEncryption extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pii:verify
                            {--count=10 : Number of random records to check}
                            {--verbose : Show detailed verification output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify PII encryption/decryption works correctly';

    /**
     * Fields to verify.
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
     * Verification statistics.
     */
    protected int $totalChecked = 0;
    protected int $totalPassed = 0;
    protected int $totalFailed = 0;
    protected array $failures = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        $verbose = $this->option('verbose');

        $this->info('===========================================');
        $this->info('  PII Encryption Verification');
        $this->info('===========================================');
        $this->newLine();

        // Get random sample of applications
        $applications = Application::withoutGlobalScopes()
            ->inRandomOrder()
            ->limit($count)
            ->get();

        if ($applications->isEmpty()) {
            $this->warn('No applications found to verify');
            return self::SUCCESS;
        }

        $this->info("Checking {$applications->count()} random records...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($applications->count());
        $progressBar->start();

        foreach ($applications as $application) {
            $this->verifyApplication($application, $verbose);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayResults($verbose);

        return $this->totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Verify encryption for a single application.
     */
    protected function verifyApplication(Application $application, bool $verbose): void
    {
        $this->totalChecked++;
        $applicationPassed = true;
        $applicationFailures = [];

        foreach ($this->encryptedFields as $field) {
            // Get raw encrypted value from database
            $encryptedValue = $application->getAttributeFromArray($field);

            // Skip null/empty values
            if ($encryptedValue === null || $encryptedValue === '') {
                continue;
            }

            // Test 1: Verify value is encrypted
            if (!$this->isEncrypted($encryptedValue)) {
                $applicationPassed = false;
                $applicationFailures[] = "{$field}: NOT ENCRYPTED";
                continue;
            }

            // Test 2: Verify decryption works
            try {
                $decrypted = Crypt::decryptString($encryptedValue);

                // Test 3: Verify re-encryption produces valid result
                $reEncrypted = Crypt::encryptString($decrypted);
                $reDecrypted = Crypt::decryptString($reEncrypted);

                if ($decrypted !== $reDecrypted) {
                    $applicationPassed = false;
                    $applicationFailures[] = "{$field}: ROUND-TRIP FAILED";
                }

                // Test 4: Verify blind index (for searchable fields)
                if ($field === 'passport_number_encrypted') {
                    $this->verifyBlindIndex($application, 'passport_number_idx', $decrypted, $applicationPassed, $applicationFailures);
                } elseif ($field === 'email_encrypted') {
                    $this->verifyBlindIndex($application, 'email_idx', $decrypted, $applicationPassed, $applicationFailures);
                } elseif ($field === 'phone_encrypted') {
                    $this->verifyBlindIndex($application, 'phone_idx', $decrypted, $applicationPassed, $applicationFailures);
                }

            } catch (\Exception $e) {
                $applicationPassed = false;
                $applicationFailures[] = "{$field}: DECRYPTION FAILED - {$e->getMessage()}";
            }
        }

        if ($applicationPassed) {
            $this->totalPassed++;
        } else {
            $this->totalFailed++;
            $this->failures[] = [
                'id' => $application->id,
                'reference' => $application->reference_number,
                'failures' => $applicationFailures,
            ];
        }
    }

    /**
     * Verify blind index matches the decrypted value.
     */
    protected function verifyBlindIndex(Application $application, string $indexField, string $plaintext, bool &$passed, array &$failures): void
    {
        $storedIndex = $application->getAttributeFromArray($indexField);

        if ($storedIndex === null) {
            // Index not generated yet - not a failure
            return;
        }

        $expectedIndex = $this->generateBlindIndex($plaintext);

        if ($storedIndex !== $expectedIndex) {
            $passed = false;
            $failures[] = "{$indexField}: INDEX MISMATCH";
        }
    }

    /**
     * Check if a value is encrypted.
     */
    protected function isEncrypted(string $value): bool
    {
        // Laravel encrypted strings start with "eyJpdiI6"
        return str_starts_with($value, 'eyJpdiI6');
    }

    /**
     * Generate blind index for comparison.
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
     * Display verification results.
     */
    protected function displayResults(bool $verbose): void
    {
        $this->info('===========================================');
        $this->info('  Verification Results');
        $this->info('===========================================');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Checked', $this->totalChecked],
                ['Passed', $this->totalPassed],
                ['Failed', $this->totalFailed],
            ]
        );

        $this->newLine();

        if ($this->totalFailed > 0) {
            $this->error("✗ {$this->totalFailed} records failed verification");
            $this->newLine();

            if ($verbose) {
                $this->warn('Failed Records:');
                foreach ($this->failures as $failure) {
                    $this->line("  Application ID: {$failure['id']} ({$failure['reference']})");
                    foreach ($failure['failures'] as $issue) {
                        $this->line("    - {$issue}");
                    }
                    $this->newLine();
                }
            } else {
                $this->info('Run with --verbose to see detailed failure information');
            }

            $this->newLine();
            $this->warn('⚠ CRITICAL: Encryption verification failed!');
            $this->warn('   Check APP_KEY and BLIND_INDEX_KEY configuration');
            $this->warn('   Review logs for detailed error information');
        } else {
            $this->info('✓ All records passed verification');
            $this->info('  Encryption/decryption is working correctly');
        }

        $this->newLine();
    }
}
