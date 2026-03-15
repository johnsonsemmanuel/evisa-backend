<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ⚠️ WARNING: This migration drops JSON columns that have been normalized.
     * 
     * ONLY run this migration AFTER:
     * 1. Running all normalization table migrations (2026_03_15_000001 through 000008)
     * 2. Running data migration: php artisan data:migrate-json-to-normalized
     * 3. Verifying data integrity: php artisan data:verify-normalized-migration
     * 4. Updating all application code to use new relationships
     * 5. Testing thoroughly in staging environment
     * 
     * This migration is IRREVERSIBLE in production (data loss).
     */
    public function up(): void
    {
        // Drop applications JSON columns
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'denial_reason_codes',
                'risk_reasons',
            ]);
        });

        // Drop visa_types JSON columns
        Schema::table('visa_types', function (Blueprint $table) {
            $table->dropColumn([
                'eligible_nationalities',
                'blacklisted_nationalities',
                'required_documents',
            ]);
        });

        // Drop fee_waivers JSON column
        Schema::table('fee_waivers', function (Blueprint $table) {
            $table->dropColumn('nationality_codes');
        });

        // Drop routing_rules JSON column
        Schema::table('routing_rules', function (Blueprint $table) {
            $table->dropColumn('nationalities');
        });

        // Drop mfa_missions JSON columns
        Schema::table('mfa_missions', function (Blueprint $table) {
            $table->dropColumn([
                'covered_nationalities',
                'visa_types_handled',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     * 
     * ⚠️ WARNING: This will restore the columns but NOT the data.
     * Data recovery requires restoring from backup.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('denial_reason_codes')->nullable()->after('decision_notes');
            $table->json('risk_reasons')->nullable()->after('risk_level');
        });

        Schema::table('visa_types', function (Blueprint $table) {
            $table->json('required_documents')->nullable();
            $table->json('eligible_nationalities')->nullable();
            $table->json('blacklisted_nationalities')->nullable();
        });

        Schema::table('fee_waivers', function (Blueprint $table) {
            $table->json('nationality_codes')->nullable();
        });

        Schema::table('routing_rules', function (Blueprint $table) {
            $table->json('nationalities')->nullable();
        });

        Schema::table('mfa_missions', function (Blueprint $table) {
            $table->json('covered_nationalities')->nullable();
            $table->json('visa_types_handled')->nullable();
        });
    }
};
