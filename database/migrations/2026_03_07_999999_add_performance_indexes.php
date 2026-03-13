<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Applications table indexes
        Schema::table('applications', function (Blueprint $table) {
            // User-based queries
            $table->index(['user_id'], 'idx_applications_user');
            
            // Status-based queries (most common)
            $table->index(['status'], 'idx_applications_status');
            $table->index(['status', 'assigned_agency'], 'idx_applications_status_agency');
            
            // Agency and queue queries
            $table->index(['assigned_agency'], 'idx_applications_agency');
            $table->index(['current_queue'], 'idx_applications_queue');
            $table->index(['assigned_agency', 'current_queue'], 'idx_applications_agency_queue');
            
            // SLA and deadline queries
            $table->index(['sla_deadline'], 'idx_applications_sla_deadline');
            $table->index(['sla_deadline', 'status'], 'idx_applications_sla_status');
            
            // Reference number lookup
            $table->index(['reference_number'], 'idx_applications_reference');
            
            // Date-based queries
            $table->index(['submitted_at'], 'idx_applications_submitted');
            $table->index(['decided_at'], 'idx_applications_decided');
            $table->index(['created_at'], 'idx_applications_created');
            
            // Officer assignment
            $table->index(['assigned_officer_id'], 'idx_applications_officer');
            
            // Composite indexes for common dashboard queries
            $table->index(['assigned_agency', 'status', 'sla_deadline'], 'idx_applications_dashboard');
            $table->index(['assigned_agency', 'current_queue', 'status'], 'idx_applications_queue_status');
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            // Authentication queries
            $table->index(['email'], 'idx_users_email');
            
            // Role-based queries
            $table->index(['role'], 'idx_users_role');
            $table->index(['role', 'agency'], 'idx_users_role_agency');
            
            // Agency-based queries
            $table->index(['agency'], 'idx_users_agency');
            
            // Email verification
            $table->index(['email_verified_at'], 'idx_users_verified');
        });

        // Payments table indexes
        Schema::table('payments', function (Blueprint $table) {
            // Application relationship
            $table->index(['application_id'], 'idx_payments_application');
            
            // Status queries
            $table->index(['status'], 'idx_payments_status');
            
            // Date-based queries
            $table->index(['created_at'], 'idx_payments_created');
            $table->index(['updated_at'], 'idx_payments_updated');
            
            // Transaction reference
            $table->index(['transaction_reference'], 'idx_payments_transaction');
        });

        // Application documents indexes
        Schema::table('application_documents', function (Blueprint $table) {
            // Application relationship
            $table->index(['application_id'], 'idx_documents_application');
            
            // Document type queries
            $table->index(['document_type'], 'idx_documents_type');
            
            // Status queries
            $table->index(['status'], 'idx_documents_status');
            
            // Upload date queries
            $table->index(['created_at'], 'idx_documents_created');
        });

        // Status history indexes
        if (Schema::hasTable('application_status_histories')) {
            Schema::table('application_status_histories', function (Blueprint $table) {
            // Application relationship
            $table->index(['application_id'], 'idx_status_history_application');
            
            // Timeline queries
            $table->index(['created_at'], 'idx_status_history_created');
            
            // Status tracking
            $table->index(['to_status'], 'idx_status_history_to_status');
            
            // User tracking
            $table->index(['changed_by_user_id'], 'idx_status_history_user');
            
            // Composite for application timeline
            $table->index(['application_id', 'created_at'], 'idx_status_history_timeline');
            });
        }

        // Internal notes indexes
        Schema::table('internal_notes', function (Blueprint $table) {
            // Application relationship
            $table->index(['application_id'], 'idx_notes_application');
            
            // Author queries
            $table->index(['author_id'], 'idx_notes_author');
            
            // Date queries
            $table->index(['created_at'], 'idx_notes_created');
            
            // Visibility
            $table->index(['is_visible_to_applicant'], 'idx_notes_visibility');
        });

        // Reason codes indexes
        Schema::table('reason_codes', function (Blueprint $table) {
            // Action type queries
            $table->index(['action_type'], 'idx_reason_codes_action');
            
            // Agency-specific codes
            $table->index(['agency'], 'idx_reason_codes_agency');
            
            // Active codes
            $table->index(['is_active'], 'idx_reason_codes_active');
        });

        // Personal access tokens indexes
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Token lookup
            $table->index(['token'], 'idx_tokens_token');
            
            // User tokens
            $table->index(['tokenable_id', 'tokenable_type'], 'idx_tokens_user');
            
            // Expiration cleanup
            $table->index(['expires_at'], 'idx_tokens_expires');
            
            // Last used tracking
            $table->index(['last_used_at'], 'idx_tokens_last_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_user');
            $table->dropIndex('idx_applications_status');
            $table->dropIndex('idx_applications_status_agency');
            $table->dropIndex('idx_applications_agency');
            $table->dropIndex('idx_applications_queue');
            $table->dropIndex('idx_applications_agency_queue');
            $table->dropIndex('idx_applications_sla_deadline');
            $table->dropIndex('idx_applications_sla_status');
            $table->dropIndex('idx_applications_reference');
            $table->dropIndex('idx_applications_submitted');
            $table->dropIndex('idx_applications_decided');
            $table->dropIndex('idx_applications_created');
            $table->dropIndex('idx_applications_officer');
            $table->dropIndex('idx_applications_dashboard');
            $table->dropIndex('idx_applications_queue_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_email');
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_role_agency');
            $table->dropIndex('idx_users_agency');
            $table->dropIndex('idx_users_verified');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_application');
            $table->dropIndex('idx_payments_status');
            $table->dropIndex('idx_payments_created');
            $table->dropIndex('idx_payments_updated');
            $table->dropIndex('idx_payments_transaction');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_application');
            $table->dropIndex('idx_documents_type');
            $table->dropIndex('idx_documents_status');
            $table->dropIndex('idx_documents_created');
        });

        if (Schema::hasTable('application_status_histories')) {
            Schema::table('application_status_histories', function (Blueprint $table) {
                $table->dropIndex('idx_status_history_application');
                $table->dropIndex('idx_status_history_created');
                $table->dropIndex('idx_status_history_to_status');
                $table->dropIndex('idx_status_history_user');
                $table->dropIndex('idx_status_history_timeline');
            });
        }

        Schema::table('internal_notes', function (Blueprint $table) {
            $table->dropIndex('idx_notes_application');
            $table->dropIndex('idx_notes_author');
            $table->dropIndex('idx_notes_created');
            $table->dropIndex('idx_notes_visibility');
        });

        Schema::table('reason_codes', function (Blueprint $table) {
            $table->dropIndex('idx_reason_codes_action');
            $table->dropIndex('idx_reason_codes_agency');
            $table->dropIndex('idx_reason_codes_active');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_tokens_token');
            $table->dropIndex('idx_tokens_user');
            $table->dropIndex('idx_tokens_expires');
            $table->dropIndex('idx_tokens_last_used');
        });
    }
};
