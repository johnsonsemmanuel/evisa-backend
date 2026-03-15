<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // All tables with Laravel timestamps should have NOT NULL created_at/updated_at
        $tables = [
            'ai_conversation_contexts',
            'analytics_audit_logs',
            'application_status_histories',
            'boarding_authorizations',
            'border_crossings',
            'eta_applications',
            'fee_waivers',
            'internal_notes',
            'interpol_checks',
            'mfa_missions',
            'mission_country_mapping',
            'mission_country_mappings',
            'notification_logs',
            'notifications',
            'payment_audit_logs',
            'payment_reconciliation_issues',
            'permissions',
            'personal_access_tokens',
            'reason_codes',
            'refund_requests',
            'risk_assessments',
            'roles',
            'routing_rules',
            'service_tiers',
            'sumsub_verifications',
            'support_messages',
            'support_tickets',
            'tier_rules',
            'travel_authorizations',
            'visa_fees',
            'visa_types',
            'watchlists',
            'webhook_events'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'created_at')) {
                        // Fix any NULL created_at values first - with database driver compatibility
                        if (DB::getDriverName() === 'mysql') {
                            DB::statement("UPDATE `{$tableName}` SET created_at = NOW() WHERE created_at IS NULL");
                        } else {
                            // SQLite
                            DB::statement("UPDATE `{$tableName}` SET created_at = datetime('now') WHERE created_at IS NULL");
                        }
                        $table->timestamp('created_at')->nullable(false)->change();
                    }
                    if (Schema::hasColumn($tableName, 'updated_at')) {
                        // Fix any NULL updated_at values first
                        DB::statement("UPDATE `{$tableName}` SET updated_at = created_at WHERE updated_at IS NULL");
                        $table->timestamp('updated_at')->nullable(false)->change();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'ai_conversation_contexts',
            'analytics_audit_logs',
            'application_status_histories',
            'boarding_authorizations',
            'border_crossings',
            'eta_applications',
            'fee_waivers',
            'internal_notes',
            'interpol_checks',
            'mfa_missions',
            'mission_country_mapping',
            'mission_country_mappings',
            'notification_logs',
            'notifications',
            'payment_audit_logs',
            'payment_reconciliation_issues',
            'permissions',
            'personal_access_tokens',
            'reason_codes',
            'refund_requests',
            'risk_assessments',
            'roles',
            'routing_rules',
            'service_tiers',
            'sumsub_verifications',
            'support_messages',
            'support_tickets',
            'tier_rules',
            'travel_authorizations',
            'visa_fees',
            'visa_types',
            'watchlists',
            'webhook_events'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'created_at')) {
                        $table->timestamp('created_at')->nullable()->change();
                    }
                    if (Schema::hasColumn($table->getTable(), 'updated_at')) {
                        $table->timestamp('updated_at')->nullable()->change();
                    }
                });
            }
        }
    }
};