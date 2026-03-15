<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\FeeWaiver;
use App\Models\MfaMission;
use App\Models\RoutingRule;
use App\Models\VisaType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateJsonToNormalizedTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:migrate-json-to-normalized
                            {--table= : Specific table to migrate (all if omitted)}
                            {--dry-run : Preview changes without applying}
                            {--chunk=500 : Chunk size for batch processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate JSON array columns to normalized pivot tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $table = $this->option('table');
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be applied');
        }

        $this->info('Starting JSON to normalized table migration...');
        $this->newLine();

        $migrations = [
            'application_denial_reasons' => fn() => $this->migrateApplicationDenialReasons($dryRun, $chunkSize),
            'application_risk_factors' => fn() => $this->migrateApplicationRiskFactors($dryRun, $chunkSize),
            'visa_type_nationality_eligibility' => fn() => $this->migrateVisaTypeNationalities($dryRun, $chunkSize),
            'fee_waiver_nationalities' => fn() => $this->migrateFeeWaiverNationalities($dryRun, $chunkSize),
            'routing_rule_nationalities' => fn() => $this->migrateRoutingRuleNationalities($dryRun, $chunkSize),
            'mfa_mission_nationalities' => fn() => $this->migrateMfaMissionNationalities($dryRun, $chunkSize),
            'mfa_mission_visa_types' => fn() => $this->migrateMfaMissionVisaTypes($dryRun, $chunkSize),
        ];

        if ($table) {
            if (!isset($migrations[$table])) {
                $this->error("Unknown table: {$table}");
                $this->info('Available tables: ' . implode(', ', array_keys($migrations)));
                return self::FAILURE;
            }
            
            $migrations[$table]();
        } else {
            foreach ($migrations as $tableName => $migration) {
                $migration();
                $this->newLine();
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info('✅ Dry run completed. Run without --dry-run to apply changes.');
        } else {
            $this->info('✅ Migration completed successfully!');
        }

        return self::SUCCESS;
    }

    /**
     * Migrate applications.denial_reason_codes to application_denial_reasons
     */
    protected function migrateApplicationDenialReasons(bool $dryRun, int $chunkSize): void
    {
        $this->info('📋 Migrating application denial reasons...');

        $totalCount = Application::whereNotNull('denial_reason_codes')
            ->where('denial_reason_codes', '!=', '[]')
            ->where('denial_reason_codes', '!=', 'null')
            ->count();

        if ($totalCount === 0) {
            $this->warn('  No applications with denial reasons found.');
            return;
        }

        $this->info("  Found {$totalCount} applications with denial reasons");

        $migratedCount = 0;
        $insertedRows = 0;

        Application::whereNotNull('denial_reason_codes')
            ->where('denial_reason_codes', '!=', '[]')
            ->where('denial_reason_codes', '!=', 'null')
            ->chunk($chunkSize, function ($applications) use ($dryRun, &$migratedCount, &$insertedRows) {
                $inserts = [];

                foreach ($applications as $application) {
                    $reasonCodes = $application->denial_reason_codes;
                    
                    if (empty($reasonCodes) || !is_array($reasonCodes)) {
                        continue;
                    }

                    foreach ($reasonCodes as $reasonCode) {
                        // Check if reason code exists (could be ID or code string)
                        $reasonCodeId = is_numeric($reasonCode) 
                            ? $reasonCode 
                            : DB::table('reason_codes')->where('code', $reasonCode)->value('id');

                        if (!$reasonCodeId) {
                            $this->warn("  ⚠️  Reason code '{$reasonCode}' not found for application {$application->id}");
                            continue;
                        }

                        $inserts[] = [
                            'application_id' => $application->id,
                            'reason_code_id' => $reasonCodeId,
                            'added_by' => $application->assigned_officer_id,
                            'created_at' => $application->decided_at ?? $application->updated_at,
                            'updated_at' => $application->decided_at ?? $application->updated_at,
                        ];
                        $insertedRows++;
                    }

                    $migratedCount++;
                }

                if (!$dryRun && !empty($inserts)) {
                    DB::table('application_denial_reasons')->insertOrIgnore($inserts);
                }

                $this->info("  Processed {$migratedCount}/{$totalCount} applications...");
            });

        $this->info("  ✅ Migrated {$migratedCount} applications ({$insertedRows} denial reason records)");
    }

    /**
     * Migrate applications.risk_reasons to application_risk_factors
     */
    protected function migrateApplicationRiskFactors(bool $dryRun, int $chunkSize): void
    {
        $this->info('📋 Migrating application risk factors...');

        $totalCount = Application::whereNotNull('risk_reasons')
            ->where('risk_reasons', '!=', '[]')
            ->where('risk_reasons', '!=', 'null')
            ->count();

        if ($totalCount === 0) {
            $this->warn('  No applications with risk reasons found.');
            return;
        }

        $this->info("  Found {$totalCount} applications with risk reasons");

        $migratedCount = 0;
        $insertedRows = 0;

        Application::whereNotNull('risk_reasons')
            ->where('risk_reasons', '!=', '[]')
            ->where('risk_reasons', '!=', 'null')
            ->chunk($chunkSize, function ($applications) use ($dryRun, &$migratedCount, &$insertedRows) {
                $inserts = [];

                foreach ($applications as $application) {
                    $riskReasons = $application->risk_reasons;
                    
                    if (empty($riskReasons) || !is_array($riskReasons)) {
                        continue;
                    }

                    foreach ($riskReasons as $riskReason) {
                        $factorCode = is_string($riskReason) ? $riskReason : ($riskReason['code'] ?? 'unknown');
                        $factorName = $this->getRiskFactorName($factorCode);
                        $severity = $this->getRiskFactorSeverity($factorCode, $application->risk_level);

                        $inserts[] = [
                            'application_id' => $application->id,
                            'factor_code' => $factorCode,
                            'factor_name' => $factorName,
                            'factor_description' => is_array($riskReason) ? ($riskReason['description'] ?? null) : null,
                            'severity' => $severity,
                            'score_impact' => is_array($riskReason) ? ($riskReason['score'] ?? 0) : 0,
                            'detected_at' => $application->risk_last_updated ?? $application->updated_at,
                            'detected_by' => 'system',
                            'is_resolved' => false,
                            'created_at' => $application->risk_last_updated ?? $application->updated_at,
                            'updated_at' => $application->risk_last_updated ?? $application->updated_at,
                        ];
                        $insertedRows++;
                    }

                    $migratedCount++;
                }

                if (!$dryRun && !empty($inserts)) {
                    DB::table('application_risk_factors')->insertOrIgnore($inserts);
                }

                $this->info("  Processed {$migratedCount}/{$totalCount} applications...");
            });

        $this->info("  ✅ Migrated {$migratedCount} applications ({$insertedRows} risk factor records)");
    }

    /**
     * Migrate visa_types.eligible_nationalities and blacklisted_nationalities
     */
    protected function migrateVisaTypeNationalities(bool $dryRun, int $chunkSize): void
    {
        $this->info('📋 Migrating visa type nationality eligibility...');

        $visaTypes = VisaType::all();
        $migratedCount = 0;
        $insertedRows = 0;

        foreach ($visaTypes as $visaType) {
            $inserts = [];

            // Migrate eligible nationalities
            if (!empty($visaType->eligible_nationalities) && is_array($visaType->eligible_nationalities)) {
                foreach ($visaType->eligible_nationalities as $countryCode) {
                    $inserts[] = [
                        'visa_type_id' => $visaType->id,
                        'country_code' => strtoupper($countryCode),
                        'eligibility_type' => 'eligible',
                        'created_at' => $visaType->created_at,
                        'updated_at' => $visaType->updated_at,
                    ];
                    $insertedRows++;
                }
            }

            // Migrate blacklisted nationalities
            if (!empty($visaType->blacklisted_nationalities) && is_array($visaType->blacklisted_nationalities)) {
                foreach ($visaType->blacklisted_nationalities as $countryCode) {
                    $inserts[] = [
                        'visa_type_id' => $visaType->id,
                        'country_code' => strtoupper($countryCode),
                        'eligibility_type' => 'blacklisted',
                        'created_at' => $visaType->created_at,
                        'updated_at' => $visaType->updated_at,
                    ];
                    $insertedRows++;
                }
            }

            if (!$dryRun && !empty($inserts)) {
                DB::table('visa_type_nationality_eligibility')->insertOrIgnore($inserts);
            }

            $migratedCount++;
        }

        $this->info("  ✅ Migrated {$migratedCount} visa types ({$insertedRows} nationality eligibility records)");
    }

    /**
     * Migrate fee_waivers.nationality_codes
     */
    protected function migrateFeeWaiverNationalities(bool $dryRun, int $chunkSize): void
    {
        $this->info('📋 Migrating fee waiver nationalities...');

        $waivers = FeeWaiver::all();
        $migratedCount = 0;
        $insertedRows = 0;

        foreach ($waivers as $waiver) {
            if (empty($waiver->nationality_codes) || !is_array($waiver->nationality_codes)) {
                continue;
            }

            $inserts = [];
            foreach ($waiver->nationality_codes as $countryCode) {
                $inserts[] = [
                    'fee_waiver_id' => $waiver->id,
                    'country_code' => strtoupper($countryCode),
                    'effective_from' => $waiver->effective_from,
                    'effective_until' => $waiver->effective_until,
                    'created_at' => $waiver->created_at,
                    'updated_at' => $waiver->updated_at,
                ];
                $insertedRows++;
            }

            if (!$dryRun && !empty($inserts)) {
                DB::table('fee_waiver_nationalities')->insertOrIgnore($inserts);
            }

            $migratedCount++;
        }

        $this->info("  ✅ Migrated {$migratedCount} fee waivers ({$insertedRows} nationality records)");
    }

    /**
     * Migrate routing_rules.nationalities
     */
    protected function migrateRoutingRuleNationalities(bool $dryRun, int $chunkSize): void
    {
        $this->info('📋 Migrating routing rule nationalities...');

        $rules = RoutingRule::all();
        $migratedCount = 0;
        $insertedRows = 0;

        foreach ($rules as $rule) {
            if (empty($rule->nationalities) || !is_array($rule->nationalities)) {
                continue;
            }

            $inserts = [];
            foreach ($rule->nationalities as $countryCode) {
                $inserts[] = [
                    'routing_rule_id' => $rule->id,
                    'country_code' => strtoupper($countryCode),
                    'created_at' => $rule->created_at,
                    'updated_at' => $rule->updated_at,
                ];
                $insertedRows++;
            }

            if (!$dryRun && !empty($inserts)) {
                DB::table('routing_rule_nationalities')->insertOrIgnore($inserts);
            }

            $migratedCount++;
        }

        $this->info("  ✅ Migrated {$migratedCount} routing rules ({$insertedRows} nationality records)");
    }

    /**
     * Migrate mfa_missions.covered_nationalities
     */
    protected function migrateMfaMissionNationalities(bool $dryRun, int $chunkSize): void
    {
        $this->info('📋 Migrating MFA mission nationalities...');

        $missions = MfaMission::all();
        $migratedCount = 0;
        $insertedRows = 0;

        foreach ($missions as $mission) {
            if (empty($mission->covered_nationalities) || !is_array($mission->covered_nationalities)) {
                continue;
            }

            $inserts = [];
            foreach ($mission->covered_nationalities as $countryCode) {
                $inserts[] = [
                    'mfa_mission_id' => $mission->id,
                    'country_code' => strtoupper($countryCode),
                    'offers_visa_services' => true,
                    'created_at' => $mission->created_at,
                    'updated_at' => $mission->updated_at,
                ];
                $insertedRows++;
            }

            if (!$dryRun && !empty($inserts)) {
                DB::table('mfa_mission_nationalities')->insertOrIgnore($inserts);
            }

            $migratedCount++;
        }

        $this->info("  ✅ Migrated {$migratedCount} MFA missions ({$insertedRows} nationality records)");
    }

    /**
     * Migrate mfa_missions.visa_types_handled
     */
    protected function migrateMfaMissionVisaTypes(bool $dryRun, int $chunkSize): void
    {
        $this->info('📋 Migrating MFA mission visa types...');

        $missions = MfaMission::all();
        $migratedCount = 0;
        $insertedRows = 0;

        foreach ($missions as $mission) {
            if (empty($mission->visa_types_handled) || !is_array($mission->visa_types_handled)) {
                continue;
            }

            $inserts = [];
            foreach ($mission->visa_types_handled as $visaTypeId) {
                $inserts[] = [
                    'mfa_mission_id' => $mission->id,
                    'visa_type_id' => $visaTypeId,
                    'is_accepting_applications' => $mission->is_active,
                    'average_processing_days' => $mission->default_sla_hours ? (int)($mission->default_sla_hours / 24) : null,
                    'created_at' => $mission->created_at,
                    'updated_at' => $mission->updated_at,
                ];
                $insertedRows++;
            }

            if (!$dryRun && !empty($inserts)) {
                DB::table('mfa_mission_visa_types')->insertOrIgnore($inserts);
            }

            $migratedCount++;
        }

        $this->info("  ✅ Migrated {$migratedCount} MFA missions ({$insertedRows} visa type records)");
    }

    /**
     * Helper: Get human-readable risk factor name
     */
    protected function getRiskFactorName(string $code): string
    {
        $names = [
            'passport_expiry_soon' => 'Passport Expiring Soon',
            'high_risk_nationality' => 'High Risk Nationality',
            'watchlist_match' => 'Watchlist Match',
            'previous_overstay' => 'Previous Overstay',
            'incomplete_documents' => 'Incomplete Documents',
            'suspicious_travel_pattern' => 'Suspicious Travel Pattern',
            'interpol_match' => 'Interpol Match',
            'duplicate_application' => 'Duplicate Application',
        ];

        return $names[$code] ?? ucwords(str_replace('_', ' ', $code));
    }

    /**
     * Helper: Determine risk factor severity
     */
    protected function getRiskFactorSeverity(string $code, ?string $applicationRiskLevel): string
    {
        $criticalFactors = ['watchlist_match', 'interpol_match'];
        $highFactors = ['previous_overstay', 'high_risk_nationality'];
        
        if (in_array($code, $criticalFactors)) {
            return 'critical';
        }
        
        if (in_array($code, $highFactors)) {
            return 'high';
        }
        
        // Use application's overall risk level as fallback
        return match($applicationRiskLevel) {
            'Critical' => 'critical',
            'High' => 'high',
            'Medium' => 'medium',
            default => 'low'
        };
    }
}
