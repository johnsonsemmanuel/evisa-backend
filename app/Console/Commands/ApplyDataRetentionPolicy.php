<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Payment;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\DataRetentionReportNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ApplyDataRetentionPolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:apply-retention 
                            {--execute : Actually apply the retention policy (dry-run by default)}
                            {--chunk=500 : Number of records to process in each chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply data retention policy according to Ghana Data Protection Commission requirements';

    /**
     * Data retention rules based on Ghana DPC requirements.
     */
    protected array $retentionRules = [
        'approved' => ['years' => 7, 'action' => 'anonymize'],
        'issued' => ['years' => 7, 'action' => 'anonymize'],
        'rejected' => ['years' => 3, 'action' => 'anonymize'],
        'denied' => ['years' => 3, 'action' => 'anonymize'],
        'withdrawn' => ['years' => 1, 'action' => 'delete'],
        'cancelled' => ['years' => 1, 'action' => 'delete'],
        'abandoned' => ['months' => 6, 'action' => 'delete'], // No activity for 6 months
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('app.data_retention_enabled', false)) {
            $this->error('Data retention policy is disabled. Set DATA_RETENTION_ENABLED=true in .env');
            return 1;
        }

        $isDryRun = !$this->option('execute');
        $chunkSize = (int) $this->option('chunk');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->info('Use --execute flag to actually apply retention policy');
        } else {
            $this->warn('⚠️  EXECUTING DATA RETENTION POLICY');
            if (!$this->confirm('This will permanently modify/delete data. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('📊 Analyzing data for retention policy application...');

        $stats = [
            'anonymized' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // Process each retention rule
        foreach ($this->retentionRules as $status => $rule) {
            $this->info("Processing {$status} applications...");
            
            $cutoffDate = $this->calculateCutoffDate($rule);
            $this->line("  Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");

            if ($status === 'abandoned') {
                $applications = $this->getAbandonedApplications($cutoffDate);
            } else {
                $applications = $this->getApplicationsByStatus($status, $cutoffDate);
            }

            $count = $applications->count();
            $this->line("  Found {$count} applications to process");

            if ($count === 0) {
                continue;
            }

            // Process in chunks
            $applications->chunk($chunkSize, function ($chunk) use ($rule, $isDryRun, &$stats) {
                foreach ($chunk as $application) {
                    try {
                        if ($rule['action'] === 'anonymize') {
                            if (!$isDryRun) {
                                $this->anonymizeApplication($application);
                            }
                            $stats['anonymized']++;
                        } elseif ($rule['action'] === 'delete') {
                            if (!$isDryRun) {
                                $this->deleteApplication($application);
                            }
                            $stats['deleted']++;
                        }
                    } catch (\Exception $e) {
                        $this->error("Error processing application {$application->id}: {$e->getMessage()}");
                        $stats['errors']++;
                        Log::error('Data retention policy error', [
                            'application_id' => $application->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            });
        }

        // Display summary
        $this->displaySummary($stats, $isDryRun);

        // Create audit log entry
        if (!$isDryRun) {
            $this->createAuditLogEntry($stats);
            $this->sendReportToSuperAdmin($stats);
        }

        return 0;
    }

    /**
     * Calculate cutoff date based on retention rule.
     */
    protected function calculateCutoffDate(array $rule): Carbon
    {
        $now = Carbon::now();
        
        if (isset($rule['years'])) {
            return $now->subYears($rule['years']);
        } elseif (isset($rule['months'])) {
            return $now->subMonths($rule['months']);
        }
        
        throw new \InvalidArgumentException('Invalid retention rule: must specify years or months');
    }

    /**
     * Get applications by status and cutoff date.
     */
    protected function getApplicationsByStatus(string $status, Carbon $cutoffDate)
    {
        return Application::where('status', $status)
            ->where('updated_at', '<', $cutoffDate)
            ->whereNull('anonymized_at'); // Don't process already anonymized records
    }

    /**
     * Get abandoned applications (no activity for specified period).
     */
    protected function getAbandonedApplications(Carbon $cutoffDate)
    {
        return Application::whereIn('status', ['draft', 'submitted_awaiting_payment', 'pending_payment'])
            ->where('updated_at', '<', $cutoffDate)
            ->whereNull('anonymized_at');
    }

    /**
     * Anonymize an application by removing PII while keeping statistical data.
     */
    protected function anonymizeApplication(Application $application): void
    {
        DB::transaction(function () use ($application) {
            // Anonymize PII fields
            $application->update([
                'passport_number' => 'REDACTED',
                'first_name' => 'ANONYMIZED',
                'last_name' => 'ANONYMIZED',
                'email' => "anon_{$application->id}@evisa.gov.gh",
                'date_of_birth' => null,
                'address' => null,
                'phone' => null,
                'emergency_contact_name' => null,
                'emergency_contact_phone' => null,
                'emergency_contact_relationship' => null,
                'passport_issue_date' => null,
                'passport_expiry_date' => null,
                'passport_issuing_authority' => null,
                'nationality' => 'REDACTED',
                'place_of_birth' => null,
                'mother_maiden_name' => null,
                'father_name' => null,
                'mother_name' => null,
                'spouse_name' => null,
                'employer_name' => null,
                'employer_address' => null,
                'employer_phone' => null,
                'accommodation_name' => null,
                'accommodation_address' => null,
                'accommodation_phone' => null,
                'host_name' => null,
                'host_phone' => null,
                'host_address' => null,
                'anonymized_at' => now(),
                
                // Keep statistical data
                // visa_type_id, processing_dates, status, payment amounts remain
            ]);

            // Anonymize related user if it's an applicant
            $user = $application->user;
            if ($user && $user->role === 'applicant') {
                $user->update([
                    'first_name' => 'ANONYMIZED',
                    'last_name' => 'ANONYMIZED',
                    'email' => "anon_user_{$user->id}@evisa.gov.gh",
                    'phone' => null,
                    'date_of_birth' => null,
                    'address' => null,
                    'anonymized_at' => now(),
                ]);
            }

            Log::info('Application anonymized for data retention', [
                'application_id' => $application->id,
                'original_status' => $application->status,
                'anonymized_at' => now(),
            ]);
        });
    }

    /**
     * Delete an application and related data.
     */
    protected function deleteApplication(Application $application): void
    {
        DB::transaction(function () use ($application) {
            $applicationId = $application->id;
            $userId = $application->user_id;

            // Delete related records first (foreign key constraints)
            $application->documents()->delete();
            $application->statusHistory()->delete();
            $application->comments()->delete();
            
            // Delete the application
            $application->delete();

            // Delete related user if it's an applicant with no other applications
            $user = User::find($userId);
            if ($user && $user->role === 'applicant') {
                $otherApplications = Application::where('user_id', $userId)->count();
                if ($otherApplications === 0) {
                    $user->delete();
                }
            }

            Log::info('Application deleted for data retention', [
                'application_id' => $applicationId,
                'user_id' => $userId,
                'deleted_at' => now(),
            ]);
        });
    }

    /**
     * Display summary of retention policy application.
     */
    protected function displaySummary(array $stats, bool $isDryRun): void
    {
        $this->info('');
        $this->info('📋 DATA RETENTION POLICY SUMMARY');
        $this->info('================================');
        
        if ($isDryRun) {
            $this->info('Mode: DRY RUN (no changes made)');
        } else {
            $this->info('Mode: EXECUTED');
        }
        
        $this->info("Records anonymized: {$stats['anonymized']}");
        $this->info("Records deleted: {$stats['deleted']}");
        $this->info("Records skipped: {$stats['skipped']}");
        $this->info("Errors encountered: {$stats['errors']}");
        
        $total = $stats['anonymized'] + $stats['deleted'];
        $this->info("Total records processed: {$total}");
        
        if ($stats['errors'] > 0) {
            $this->warn("⚠️  {$stats['errors']} errors occurred. Check logs for details.");
        }
        
        if (!$isDryRun && $total > 0) {
            $this->info('✅ Data retention policy applied successfully');
        }
    }

    /**
     * Create audit log entry for retention policy application.
     */
    protected function createAuditLogEntry(array $stats): void
    {
        $now = now();
        AuditLog::create([
            'user_id' => null, // System action
            'action' => 'data_retention_policy_applied',
            'auditable_type' => 'System',
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'anonymized_count' => $stats['anonymized'],
                'deleted_count' => $stats['deleted'],
                'error_count' => $stats['errors'],
                'applied_at' => $now,
            ],
            'url' => 'console:data:apply-retention',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Laravel Artisan',
            'tags' => ['data_retention', 'privacy', 'compliance'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Log::info('Data retention policy applied', [
            'anonymized' => $stats['anonymized'],
            'deleted' => $stats['deleted'],
            'errors' => $stats['errors'],
            'timestamp' => now(),
        ]);
    }

    /**
     * Send report to super admin users.
     */
    protected function sendReportToSuperAdmin(array $stats): void
    {
        $superAdmins = User::where('role', 'admin')
            ->where('is_active', true)
            ->get();

        foreach ($superAdmins as $admin) {
            try {
                $admin->notify(new DataRetentionReportNotification($stats));
            } catch (\Exception $e) {
                Log::error('Failed to send data retention report', [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}