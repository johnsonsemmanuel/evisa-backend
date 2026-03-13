<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Mission assignment for MFA applications
            if (!Schema::hasColumn('applications', 'owner_mission_id')) {
                $table->unsignedBigInteger('owner_mission_id')->nullable()->after('assigned_agency');
                $table->foreign('owner_mission_id')->references('id')->on('mfa_missions')->nullOnDelete();
            }
            
            // Queue tracking - skip if exists (may have different name like current_queue)
            if (!Schema::hasColumn('applications', 'current_queue')) {
                $table->string('current_queue')->default('review')->after('owner_mission_id');
            }
            
            // Two-stage officer assignment
            if (!Schema::hasColumn('applications', 'reviewing_officer_id')) {
                $table->unsignedBigInteger('reviewing_officer_id')->nullable()->after('assigned_officer_id');
                $table->foreign('reviewing_officer_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('applications', 'approval_officer_id')) {
                $table->unsignedBigInteger('approval_officer_id')->nullable()->after('reviewing_officer_id');
                $table->foreign('approval_officer_id')->references('id')->on('users')->nullOnDelete();
            }
            
            // Workflow timestamps
            if (!Schema::hasColumn('applications', 'review_started_at')) {
                $table->timestamp('review_started_at')->nullable()->after('sla_deadline');
            }
            if (!Schema::hasColumn('applications', 'review_completed_at')) {
                $table->timestamp('review_completed_at')->nullable()->after('review_started_at');
            }
            if (!Schema::hasColumn('applications', 'approval_started_at')) {
                $table->timestamp('approval_started_at')->nullable()->after('review_completed_at');
            }
            if (!Schema::hasColumn('applications', 'approval_completed_at')) {
                $table->timestamp('approval_completed_at')->nullable()->after('approval_started_at');
            }
        });

        // Add indexes separately
        try {
            Schema::table('applications', function (Blueprint $table) {
                $table->index(['assigned_agency', 'current_queue'], 'apps_agency_queue_index');
                $table->index(['owner_mission_id', 'current_queue'], 'apps_mission_queue_index');
            });
        } catch (\Exception $e) {
            // Indexes may already exist
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['owner_mission_id']);
            $table->dropForeign(['reviewing_officer_id']);
            $table->dropForeign(['approval_officer_id']);
            
            $table->dropIndex(['assigned_agency', 'current_queue']);
            $table->dropIndex(['owner_mission_id', 'current_queue']);
            
            $table->dropColumn([
                'owner_mission_id',
                'current_queue',
                'reviewing_officer_id',
                'approval_officer_id',
                'review_started_at',
                'review_completed_at',
                'approval_started_at',
                'approval_completed_at',
            ]);
        });
    }
};
