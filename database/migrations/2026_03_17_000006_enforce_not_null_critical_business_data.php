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
        // Analytics Audit Logs - user tracking is critical
        if (Schema::hasTable('analytics_audit_logs')) {
            Schema::table('analytics_audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('analytics_audit_logs', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable(false)->change();
                }
            });
        }

        // Application Status Histories - status tracking is critical
        if (Schema::hasTable('application_status_histories')) {
            Schema::table('application_status_histories', function (Blueprint $table) {
                if (Schema::hasColumn('application_status_histories', 'application_id')) {
                    $table->unsignedBigInteger('application_id')->nullable(false)->change();
                }
                if (Schema::hasColumn('application_status_histories', 'to_status')) {
                    $table->string('to_status')->nullable(false)->change();
                }
            });
        }

        // Payment Audit Logs - payment tracking is critical
        if (Schema::hasTable('payment_audit_logs')) {
            Schema::table('payment_audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('payment_audit_logs', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable(false)->change();
                }
                if (Schema::hasColumn('payment_audit_logs', 'event_type')) {
                    $table->string('event_type')->nullable(false)->change();
                }
            });
        }

        // Refund Requests - critical financial data
        if (Schema::hasTable('refund_requests')) {
            Schema::table('refund_requests', function (Blueprint $table) {
                if (Schema::hasColumn('refund_requests', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable(false)->change();
                }
                if (Schema::hasColumn('refund_requests', 'amount')) {
                    $table->unsignedBigInteger('amount')->nullable(false)->change();
                }
                if (Schema::hasColumn('refund_requests', 'status')) {
                    $table->string('status')->nullable(false)->change();
                }
                if (Schema::hasColumn('refund_requests', 'requested_by')) {
                    $table->unsignedBigInteger('requested_by')->nullable(false)->change();
                }
            });
        }

        // Visa Fees - fee configuration is critical
        if (Schema::hasTable('visa_fees')) {
            Schema::table('visa_fees', function (Blueprint $table) {
                if (Schema::hasColumn('visa_fees', 'visa_type_id')) {
                    $table->unsignedBigInteger('visa_type_id')->nullable(false)->change();
                }
                if (Schema::hasColumn('visa_fees', 'amount')) {
                    $table->unsignedBigInteger('amount')->nullable(false)->change();
                }
                if (Schema::hasColumn('visa_fees', 'nationality_category')) {
                    $table->string('nationality_category')->nullable(false)->change();
                }
                if (Schema::hasColumn('visa_fees', 'processing_tier')) {
                    $table->string('processing_tier')->nullable(false)->change();
                }
            });
        }

        // Visa Types - visa configuration is critical
        if (Schema::hasTable('visa_types')) {
            Schema::table('visa_types', function (Blueprint $table) {
                if (Schema::hasColumn('visa_types', 'name')) {
                    $table->string('name')->nullable(false)->change();
                }
                if (Schema::hasColumn('visa_types', 'slug')) {
                    $table->string('slug')->nullable(false)->change();
                }
                if (Schema::hasColumn('visa_types', 'is_active')) {
                    $table->boolean('is_active')->nullable(false)->default(true)->change();
                }
            });
        }

        // Reason Codes - decision tracking is critical
        if (Schema::hasTable('reason_codes')) {
            Schema::table('reason_codes', function (Blueprint $table) {
                if (Schema::hasColumn('reason_codes', 'code')) {
                    $table->string('code')->nullable(false)->change();
                }
                if (Schema::hasColumn('reason_codes', 'name')) {
                    $table->string('name')->nullable(false)->change();
                }
                if (Schema::hasColumn('reason_codes', 'action_type')) {
                    $table->string('action_type')->nullable(false)->change();
                }
                if (Schema::hasColumn('reason_codes', 'is_active')) {
                    $table->boolean('is_active')->nullable(false)->default(true)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Analytics Audit Logs
        if (Schema::hasTable('analytics_audit_logs')) {
            Schema::table('analytics_audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('analytics_audit_logs', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->change();
                }
            });
        }

        // Application Status Histories
        if (Schema::hasTable('application_status_histories')) {
            Schema::table('application_status_histories', function (Blueprint $table) {
                if (Schema::hasColumn('application_status_histories', 'application_id')) {
                    $table->unsignedBigInteger('application_id')->nullable()->change();
                }
                if (Schema::hasColumn('application_status_histories', 'to_status')) {
                    $table->string('to_status')->nullable()->change();
                }
            });
        }

        // Payment Audit Logs
        if (Schema::hasTable('payment_audit_logs')) {
            Schema::table('payment_audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('payment_audit_logs', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->change();
                }
                if (Schema::hasColumn('payment_audit_logs', 'event_type')) {
                    $table->string('event_type')->nullable()->change();
                }
            });
        }

        // Refund Requests
        if (Schema::hasTable('refund_requests')) {
            Schema::table('refund_requests', function (Blueprint $table) {
                if (Schema::hasColumn('refund_requests', 'payment_id')) {
                    $table->unsignedBigInteger('payment_id')->nullable()->change();
                }
                if (Schema::hasColumn('refund_requests', 'amount')) {
                    $table->unsignedBigInteger('amount')->nullable()->change();
                }
                if (Schema::hasColumn('refund_requests', 'status')) {
                    $table->string('status')->nullable()->change();
                }
                if (Schema::hasColumn('refund_requests', 'requested_by')) {
                    $table->unsignedBigInteger('requested_by')->nullable()->change();
                }
            });
        }

        // Visa Fees
        if (Schema::hasTable('visa_fees')) {
            Schema::table('visa_fees', function (Blueprint $table) {
                if (Schema::hasColumn('visa_fees', 'visa_type_id')) {
                    $table->unsignedBigInteger('visa_type_id')->nullable()->change();
                }
                if (Schema::hasColumn('visa_fees', 'amount')) {
                    $table->unsignedBigInteger('amount')->nullable()->change();
                }
                if (Schema::hasColumn('visa_fees', 'nationality_category')) {
                    $table->string('nationality_category')->nullable()->change();
                }
                if (Schema::hasColumn('visa_fees', 'processing_tier')) {
                    $table->string('processing_tier')->nullable()->change();
                }
            });
        }

        // Visa Types
        if (Schema::hasTable('visa_types')) {
            Schema::table('visa_types', function (Blueprint $table) {
                if (Schema::hasColumn('visa_types', 'name')) {
                    $table->string('name')->nullable()->change();
                }
                if (Schema::hasColumn('visa_types', 'slug')) {
                    $table->string('slug')->nullable()->change();
                }
                if (Schema::hasColumn('visa_types', 'is_active')) {
                    $table->boolean('is_active')->nullable()->change();
                }
            });
        }

        // Reason Codes
        if (Schema::hasTable('reason_codes')) {
            Schema::table('reason_codes', function (Blueprint $table) {
                if (Schema::hasColumn('reason_codes', 'code')) {
                    $table->string('code')->nullable()->change();
                }
                if (Schema::hasColumn('reason_codes', 'name')) {
                    $table->string('name')->nullable()->change();
                }
                if (Schema::hasColumn('reason_codes', 'action_type')) {
                    $table->string('action_type')->nullable()->change();
                }
                if (Schema::hasColumn('reason_codes', 'is_active')) {
                    $table->boolean('is_active')->nullable()->change();
                }
            });
        }
    }
};