<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Convert all ENUM columns to VARCHAR(50) for better flexibility
     * and to support PHP-backed enums.
     */
    public function up(): void
    {
        // Application Documents
        Schema::table('application_documents', function (Blueprint $table) {
            $table->string('ocr_status', 50)->default('pending')->change();
            $table->string('verification_status', 50)->default('pending')->change();
        });

        // Applications
        Schema::table('applications', function (Blueprint $table) {
            $table->string('assigned_agency', 50)->nullable()->change();
            $table->string('entry_type', 50)->default('single')->change();
            $table->string('owner_agency', 50)->nullable()->change();
            $table->string('risk_level', 50)->default('low')->change();
            $table->string('sumsub_review_result', 50)->default('init')->change();
            $table->string('sumsub_verification_status', 50)->default('not_required')->change();
            $table->string('tier', 50)->default('tier_1')->change();
        });

        // Boarding Authorizations
        Schema::table('boarding_authorizations', function (Blueprint $table) {
            $table->string('authorization_type', 50)->change();
        });

        // Border Crossings
        Schema::table('border_crossings', function (Blueprint $table) {
            $table->string('crossing_type', 50)->change();
            $table->string('verification_status', 50)->change();
        });

        // ETA Applications
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->string('entry_type', 50)->default('single')->change();
            $table->string('status', 50)->default('pending')->change();
            $table->string('sumsub_review_result', 50)->default('init')->change();
            $table->string('sumsub_verification_status', 50)->default('not_required')->change();
        });

        // Fee Waivers
        Schema::table('fee_waivers', function (Blueprint $table) {
            $table->string('waiver_type', 50)->change();
        });

        // Interpol Checks
        Schema::table('interpol_checks', function (Blueprint $table) {
            $table->string('status', 50)->default('pending')->change();
        });

        // MFA Missions
        Schema::table('mfa_missions', function (Blueprint $table) {
            $table->string('mission_type', 50)->change();
        });

        // Notification Logs
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('channel', 50)->change();
            $table->string('status', 50)->default('pending')->change();
        });

        // Payments
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 50)->default('initiated')->change();
            $table->string('gateway', 50)->change();
        });

        // Payment Audit Logs
        Schema::table('payment_audit_logs', function (Blueprint $table) {
            $table->string('actor_type', 50)->change();
        });

        // Payment Reconciliation Issues
        Schema::table('payment_reconciliation_issues', function (Blueprint $table) {
            $table->string('issue_type', 50)->change();
        });

        // Refund Requests
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->string('gateway', 50)->change();
        });

        // Risk Assessments
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->string('risk_level', 50)->default('low')->change();
            $table->string('status', 50)->default('pending')->change();
        });

        // Routing Rules
        Schema::table('routing_rules', function (Blueprint $table) {
            $table->string('route_to', 50)->change();
            $table->string('rule_type', 50)->change();
        });

        // Sumsub Verifications
        Schema::table('sumsub_verifications', function (Blueprint $table) {
            $table->string('review_result', 50)->default('init')->change();
            $table->string('verification_status', 50)->default('pending')->change();
        });

        // Support Tickets
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('priority', 50)->default('medium')->change();
            $table->string('status', 50)->default('open')->change();
        });

        // Tier Rules
        Schema::table('tier_rules', function (Blueprint $table) {
            $table->string('route_to', 50)->change();
            $table->string('tier', 50)->change();
        });

        // Travel Authorizations
        Schema::table('travel_authorizations', function (Blueprint $table) {
            $table->string('authorization_type', 50)->change();
            $table->string('status', 50)->default('active')->change();
        });

        // Visa Types
        Schema::table('visa_types', function (Blueprint $table) {
            $table->string('entry_type', 50)->default('single')->change();
        });

        // Watchlists
        Schema::table('watchlists', function (Blueprint $table) {
            $table->string('list_type', 50)->change();
            $table->string('severity', 50)->default('medium')->change();
        });

        // Webhook Events
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('gateway', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This rollback converts VARCHAR back to ENUM.
     * This should only be used in development environments.
     */
    public function down(): void
    {
        // Application Documents
        DB::statement("ALTER TABLE application_documents MODIFY COLUMN ocr_status ENUM('pending','processing','passed','failed','skipped') DEFAULT 'pending'");
        DB::statement("ALTER TABLE application_documents MODIFY COLUMN verification_status ENUM('pending','accepted','rejected','reupload_requested') DEFAULT 'pending'");

        // Applications
        DB::statement("ALTER TABLE applications MODIFY COLUMN assigned_agency ENUM('gis','mfa') NULL");
        DB::statement("ALTER TABLE applications MODIFY COLUMN entry_type ENUM('single','multiple') DEFAULT 'single'");
        DB::statement("ALTER TABLE applications MODIFY COLUMN owner_agency ENUM('GIS','MFA') NULL");
        DB::statement("ALTER TABLE applications MODIFY COLUMN risk_level ENUM('low','medium','high','critical') DEFAULT 'low'");
        DB::statement("ALTER TABLE applications MODIFY COLUMN sumsub_review_result ENUM('approved','rejected','pending','init') DEFAULT 'init'");
        DB::statement("ALTER TABLE applications MODIFY COLUMN sumsub_verification_status ENUM('not_required','pending','completed','failed') DEFAULT 'not_required'");
        DB::statement("ALTER TABLE applications MODIFY COLUMN tier ENUM('tier_1','tier_2') DEFAULT 'tier_1'");

        // Boarding Authorizations
        DB::statement("ALTER TABLE boarding_authorizations MODIFY COLUMN authorization_type ENUM('ETA','VISA')");

        // Border Crossings
        DB::statement("ALTER TABLE border_crossings MODIFY COLUMN crossing_type ENUM('entry','exit')");
        DB::statement("ALTER TABLE border_crossings MODIFY COLUMN verification_status ENUM('valid','invalid','expired','not_found','secondary_inspection')");

        // ETA Applications
        DB::statement("ALTER TABLE eta_applications MODIFY COLUMN entry_type ENUM('single','multiple') DEFAULT 'single'");
        DB::statement("ALTER TABLE eta_applications MODIFY COLUMN status ENUM('pending','approved','denied','expired') DEFAULT 'pending'");
        DB::statement("ALTER TABLE eta_applications MODIFY COLUMN sumsub_review_result ENUM('approved','rejected','pending','init') DEFAULT 'init'");
        DB::statement("ALTER TABLE eta_applications MODIFY COLUMN sumsub_verification_status ENUM('not_required','pending','completed','failed') DEFAULT 'not_required'");

        // Fee Waivers
        DB::statement("ALTER TABLE fee_waivers MODIFY COLUMN waiver_type ENUM('full','percentage','fixed_reduction')");

        // Interpol Checks
        DB::statement("ALTER TABLE interpol_checks MODIFY COLUMN status ENUM('pending','matched','no_match','failed') DEFAULT 'pending'");

        // MFA Missions
        DB::statement("ALTER TABLE mfa_missions MODIFY COLUMN mission_type ENUM('embassy','consulate','high_commission','permanent_mission')");

        // Notification Logs
        DB::statement("ALTER TABLE notification_logs MODIFY COLUMN channel ENUM('email','sms','push','in_app')");
        DB::statement("ALTER TABLE notification_logs MODIFY COLUMN status ENUM('pending','sent','delivered','failed','bounced') DEFAULT 'pending'");

        // Payments
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('initiated','processing','paid','failed','refunded','cancelled','suspicious','expired','pending_verification') DEFAULT 'initiated'");
        DB::statement("ALTER TABLE payments MODIFY COLUMN gateway ENUM('gcb','paystack')");

        // Payment Audit Logs
        DB::statement("ALTER TABLE payment_audit_logs MODIFY COLUMN actor_type ENUM('user','system','gateway')");

        // Payment Reconciliation Issues
        DB::statement("ALTER TABLE payment_reconciliation_issues MODIFY COLUMN issue_type ENUM('LOCAL_PAID_GATEWAY_FAILED','LOCAL_FAILED_GATEWAY_PAID','MISSING_LOCAL','AMOUNT_MISMATCH','REFERENCE_NOT_FOUND')");

        // Refund Requests
        DB::statement("ALTER TABLE refund_requests MODIFY COLUMN gateway ENUM('gcb','paystack')");

        // Risk Assessments
        DB::statement("ALTER TABLE risk_assessments MODIFY COLUMN risk_level ENUM('low','medium','high','critical') DEFAULT 'low'");
        DB::statement("ALTER TABLE risk_assessments MODIFY COLUMN status ENUM('pending','in_progress','completed','manual_review') DEFAULT 'pending'");

        // Routing Rules
        DB::statement("ALTER TABLE routing_rules MODIFY COLUMN route_to ENUM('gis_hq','mfa_hq','mfa_mission')");
        DB::statement("ALTER TABLE routing_rules MODIFY COLUMN rule_type ENUM('nationality','visa_type','risk_level','purpose','diplomatic','custom')");

        // Sumsub Verifications
        DB::statement("ALTER TABLE sumsub_verifications MODIFY COLUMN review_result ENUM('approved','rejected','pending','init') DEFAULT 'init'");
        DB::statement("ALTER TABLE sumsub_verifications MODIFY COLUMN verification_status ENUM('pending','queued','completed','failed') DEFAULT 'pending'");

        // Support Tickets
        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN priority ENUM('low','medium','high','urgent') DEFAULT 'medium'");
        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN status ENUM('open','in_progress','resolved','closed') DEFAULT 'open'");

        // Tier Rules
        DB::statement("ALTER TABLE tier_rules MODIFY COLUMN route_to ENUM('gis','mfa')");
        DB::statement("ALTER TABLE tier_rules MODIFY COLUMN tier ENUM('tier_1','tier_2')");

        // Travel Authorizations
        DB::statement("ALTER TABLE travel_authorizations MODIFY COLUMN authorization_type ENUM('ETA','VISA')");
        DB::statement("ALTER TABLE travel_authorizations MODIFY COLUMN status ENUM('active','expired','used','cancelled') DEFAULT 'active'");

        // Visa Types
        DB::statement("ALTER TABLE visa_types MODIFY COLUMN entry_type ENUM('single','multiple') DEFAULT 'single'");

        // Watchlists
        DB::statement("ALTER TABLE watchlists MODIFY COLUMN list_type ENUM('interpol','national','travel_ban','fraud','overstay','custom')");
        DB::statement("ALTER TABLE watchlists MODIFY COLUMN severity ENUM('low','medium','high','critical') DEFAULT 'medium'");

        // Webhook Events
        DB::statement("ALTER TABLE webhook_events MODIFY COLUMN gateway ENUM('gcb','paystack')");
    }
};