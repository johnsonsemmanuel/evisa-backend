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
            // Add queue management fields if they don't exist
            if (!Schema::hasColumn('applications', 'current_queue')) {
                $table->enum('current_queue', ['REVIEW_QUEUE', 'APPROVAL_QUEUE'])->nullable()->after('status');
            }
            
            // Add owner agency and mission fields for routing
            if (!Schema::hasColumn('applications', 'owner_agency')) {
                $table->enum('owner_agency', ['GIS', 'MFA'])->nullable()->after('current_queue');
            }
            
            if (!Schema::hasColumn('applications', 'owner_mission_id')) {
                $table->unsignedBigInteger('owner_mission_id')->nullable()->after('owner_agency');
                $table->foreign('owner_mission_id')->references('id')->on('mfa_missions');
            }
            
            // Add workflow timestamps if they don't exist
            if (!Schema::hasColumn('applications', 'review_started_at')) {
                $table->timestamp('review_started_at')->nullable()->after('owner_mission_id');
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
            
            // Add indexes for performance
            $table->index('current_queue');
            $table->index('owner_agency');
            $table->index(['owner_agency', 'owner_mission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['current_queue']);
            $table->dropIndex(['owner_agency']);
            $table->dropIndex(['owner_agency', 'owner_mission_id']);
            
            // Drop foreign key and columns
            if (Schema::hasColumn('applications', 'owner_mission_id')) {
                $table->dropForeign(['owner_mission_id']);
                $table->dropColumn('owner_mission_id');
            }
            
            // Drop columns if they exist
            $columns = ['current_queue', 'owner_agency', 'review_started_at', 'review_completed_at', 'approval_started_at', 'approval_completed_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};