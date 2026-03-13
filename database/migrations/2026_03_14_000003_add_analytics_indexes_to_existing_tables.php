<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Note: Many indexes already exist from 2026_03_07_999999_add_performance_indexes.php
     * This migration only adds analytics-specific indexes that are missing.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Add analytics-specific indexes that don't already exist
            
            // visa_type_id index for revenue by visa type analytics
            try {
                $table->index('visa_type_id', 'idx_applications_visa_type_id');
            } catch (\Exception $e) {
                // Index already exists, skip
            }
            
            // tier index for revenue by tier analytics
            try {
                $table->index('tier', 'idx_applications_tier');
            } catch (\Exception $e) {
                // Index already exists, skip
            }
            
            // Composite indexes for common analytics queries
            // status + created_at for time-based status analytics
            try {
                $table->index(['status', 'created_at'], 'idx_applications_status_created');
            } catch (\Exception $e) {
                // Index already exists, skip
            }
            
            // visa_type_id + created_at for time-based visa type analytics
            try {
                $table->index(['visa_type_id', 'created_at'], 'idx_applications_visa_created');
            } catch (\Exception $e) {
                // Index already exists, skip
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            // Add analytics-specific indexes that don't already exist
            
            // paid_at index for time-based revenue analytics
            try {
                $table->index('paid_at', 'idx_payments_paid_at');
            } catch (\Exception $e) {
                // Index already exists, skip
            }
            
            // Composite index for common analytics queries
            // status + paid_at for completed payment analytics over time
            try {
                $table->index(['status', 'paid_at'], 'idx_payments_status_paid');
            } catch (\Exception $e) {
                // Index already exists, skip
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_visa_type_id');
            $table->dropIndex('idx_applications_tier');
            $table->dropIndex('idx_applications_status_created');
            $table->dropIndex('idx_applications_visa_created');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_paid_at');
            $table->dropIndex('idx_payments_status_paid');
        });
    }
};
