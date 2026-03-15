<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyNormalizedDataMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:verify-normalized-migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that JSON data was correctly migrated to normalized tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Verifying normalized data migration...');
        $this->newLine();

        $allPassed = true;

        $allPassed &= $this->verifyApplicationDenialReasons();
        $allPassed &= $this->verifyApplicationRiskFactors();
        $allPassed &= $this->verifyVisaTypeNationalities();
        $allPassed &= $this->verifyFeeWaiverNationalities();
        $allPassed &= $this->verifyRoutingRuleNationalities();
        $allPassed &= $this->verifyMfaMissionNationalities();
        $allPassed &= $this->verifyMfaMissionVisaTypes();

        $this->newLine();
        if ($allPassed) {
            $this->info('✅ All verification checks passed!');
            $this->info('You can now safely run the DROP column migrations.');
            return self::SUCCESS;
        } else {
            $this->error('❌ Some verification checks failed. Review the output above.');
            $this->warn('DO NOT run the DROP column migrations until all checks pass.');
            return self::FAILURE;
        }
    }

    protected function verifyApplicationDenialReasons(): bool
    {
        $this->info('📋 Verifying application_denial_reasons...');

        // Count applications with denial reasons in JSON
        $jsonCount = DB::table('applications')
            ->whereNotNull('denial_reason_codes')
            ->where('denial_reason_codes', '!=', '[]')
            ->where('denial_reason_codes', '!=', 'null')
            ->count();

        // Count distinct applications in normalized table
        $normalizedCount = DB::table('application_denial_reasons')
            ->distinct('application_id')
            ->count('application_id');

        $this->info("  JSON records: {$jsonCount}");
        $this->info("  Normalized records: {$normalizedCount}");

        if ($jsonCount !== $normalizedCount) {
            $this->error("  ❌ Mismatch! Expected {$jsonCount}, got {$normalizedCount}");
            return false;
        }

        // Check for orphaned records
        $orphaned = DB::table('application_denial_reasons as adr')
            ->leftJoin('applications as a', 'adr.application_id', '=', 'a.id')
            ->whereNull('a.id')
            ->count();

        if ($orphaned > 0) {
            $this->error("  ❌ Found {$orphaned} orphaned records (application doesn't exist)");
            return false;
        }

        // Check for invalid reason codes
        $invalidReasons = DB::table('application_denial_reasons as adr')
            ->leftJoin('reason_codes as rc', 'adr.reason_code_id', '=', 'rc.id')
            ->whereNull('rc.id')
            ->count();

        if ($invalidReasons > 0) {
            $this->error("  ❌ Found {$invalidReasons} invalid reason code references");
            return false;
        }

        $this->info('  ✅ Passed');
        return true;
    }

    protected function verifyApplicationRiskFactors(): bool
    {
        $this->info('📋 Verifying application_risk_factors...');

        $jsonCount = DB::table('applications')
            ->whereNotNull('risk_reasons')
            ->where('risk_reasons', '!=', '[]')
            ->where('risk_reasons', '!=', 'null')
            ->count();

        $normalizedCount = DB::table('application_risk_factors')
            ->distinct('application_id')
            ->count('application_id');

        $this->info("  JSON records: {$jsonCount}");
        $this->info("  Normalized records: {$normalizedCount}");

        if ($jsonCount !== $normalizedCount) {
            $this->error("  ❌ Mismatch! Expected {$jsonCount}, got {$normalizedCount}");
            return false;
        }

        // Check for orphaned records
        $orphaned = DB::table('application_risk_factors as arf')
            ->leftJoin('applications as a', 'arf.application_id', '=', 'a.id')
            ->whereNull('a.id')
            ->count();

        if ($orphaned > 0) {
            $this->error("  ❌ Found {$orphaned} orphaned records");
            return false;
        }

        $this->info('  ✅ Passed');
        return true;
    }

    protected function verifyVisaTypeNationalities(): bool
    {
        $this->info('📋 Verifying visa_type_nationality_eligibility...');

        // Count visa types with nationality data
        $jsonCount = DB::table('visa_types')
            ->where(function($q) {
                $q->whereNotNull('eligible_nationalities')
                  ->orWhereNotNull('blacklisted_nationalities');
            })
            ->count();

        $normalizedCount = DB::table('visa_type_nationality_eligibility')
            ->distinct('visa_type_id')
            ->count('visa_type_id');

        $this->info("  Visa types with nationality data: {$jsonCount}");
        $this->info("  Visa types in normalized table: {$normalizedCount}");

        // Check for orphaned records
        $orphaned = DB::table('visa_type_nationality_eligibility as vtne')
            ->leftJoin('visa_types as vt', 'vtne.visa_type_id', '=', 'vt.id')
            ->whereNull('vt.id')
            ->count();

        if ($orphaned > 0) {
            $this->error("  ❌ Found {$orphaned} orphaned records");
            return false;
        }

        $this->info('  ✅ Passed');
        return true;
    }

    protected function verifyFeeWaiverNationalities(): bool
    {
        $this->info('📋 Verifying fee_waiver_nationalities...');

        $jsonCount = DB::table('fee_waivers')
            ->whereNotNull('nationality_codes')
            ->where('nationality_codes', '!=', '[]')
            ->count();

        $normalizedCount = DB::table('fee_waiver_nationalities')
            ->distinct('fee_waiver_id')
            ->count('fee_waiver_id');

        $this->info("  JSON records: {$jsonCount}");
        $this->info("  Normalized records: {$normalizedCount}");

        if ($jsonCount !== $normalizedCount) {
            $this->error("  ❌ Mismatch! Expected {$jsonCount}, got {$normalizedCount}");
            return false;
        }

        $this->info('  ✅ Passed');
        return true;
    }

    protected function verifyRoutingRuleNationalities(): bool
    {
        $this->info('📋 Verifying routing_rule_nationalities...');

        $jsonCount = DB::table('routing_rules')
            ->whereNotNull('nationalities')
            ->where('nationalities', '!=', '[]')
            ->count();

        $normalizedCount = DB::table('routing_rule_nationalities')
            ->distinct('routing_rule_id')
            ->count('routing_rule_id');

        $this->info("  JSON records: {$jsonCount}");
        $this->info("  Normalized records: {$normalizedCount}");

        if ($jsonCount !== $normalizedCount) {
            $this->error("  ❌ Mismatch! Expected {$jsonCount}, got {$normalizedCount}");
            return false;
        }

        $this->info('  ✅ Passed');
        return true;
    }

    protected function verifyMfaMissionNationalities(): bool
    {
        $this->info('📋 Verifying mfa_mission_nationalities...');

        $jsonCount = DB::table('mfa_missions')
            ->whereNotNull('covered_nationalities')
            ->where('covered_nationalities', '!=', '[]')
            ->count();

        $normalizedCount = DB::table('mfa_mission_nationalities')
            ->distinct('mfa_mission_id')
            ->count('mfa_mission_id');

        $this->info("  JSON records: {$jsonCount}");
        $this->info("  Normalized records: {$normalizedCount}");

        if ($jsonCount !== $normalizedCount) {
            $this->error("  ❌ Mismatch! Expected {$jsonCount}, got {$normalizedCount}");
            return false;
        }

        $this->info('  ✅ Passed');
        return true;
    }

    protected function verifyMfaMissionVisaTypes(): bool
    {
        $this->info('📋 Verifying mfa_mission_visa_types...');

        $jsonCount = DB::table('mfa_missions')
            ->whereNotNull('visa_types_handled')
            ->where('visa_types_handled', '!=', '[]')
            ->count();

        $normalizedCount = DB::table('mfa_mission_visa_types')
            ->distinct('mfa_mission_id')
            ->count('mfa_mission_id');

        $this->info("  JSON records: {$jsonCount}");
        $this->info("  Normalized records: {$normalizedCount}");

        if ($jsonCount !== $normalizedCount) {
            $this->error("  ❌ Mismatch! Expected {$jsonCount}, got {$normalizedCount}");
            return false;
        }

        $this->info('  ✅ Passed');
        return true;
    }
}
