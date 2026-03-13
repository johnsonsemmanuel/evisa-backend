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
        Schema::table('applications', function (Blueprint $table) {
            // Only add columns that don't exist
            if (!Schema::hasColumn('applications', 'risk_reasons')) {
                $table->json('risk_reasons')->nullable()->after('risk_level');
            }
            if (!Schema::hasColumn('applications', 'risk_last_updated')) {
                $table->timestamp('risk_last_updated')->nullable()->after('risk_reasons');
            }
        });
        
        // Add indexes separately to avoid conflicts
        try {
            Schema::table('applications', function (Blueprint $table) {
                $table->index('risk_score');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        try {
            Schema::table('applications', function (Blueprint $table) {
                $table->index('risk_level');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'risk_reasons')) {
                $table->dropColumn('risk_reasons');
            }
            if (Schema::hasColumn('applications', 'risk_last_updated')) {
                $table->dropColumn('risk_last_updated');
            }
        });
        
        // Drop indexes
        try {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropIndex(['risk_score']);
            });
        } catch (\Exception $e) {
            // Index might not exist
        }
        
        try {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropIndex(['risk_level']);
            });
        } catch (\Exception $e) {
            // Index might not exist
        }
    }
};