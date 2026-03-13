<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: Role column already exists as string/enum in users table
        // New roles (gis_reviewer, gis_approver, gis_admin, mfa_approver, mfa_admin) 
        // are handled by the application logic - no schema change needed for SQLite

        Schema::table('users', function (Blueprint $table) {
            // Check if mfa_mission_id doesn't already exist
            if (!Schema::hasColumn('users', 'mfa_mission_id')) {
                // Mission assignment for MFA officers
                $table->unsignedBigInteger('mfa_mission_id')->nullable()->after('agency');
                $table->foreign('mfa_mission_id')->references('id')->on('mfa_missions')->nullOnDelete();
            }
            
            // Officer capabilities
            if (!Schema::hasColumn('users', 'can_review')) {
                $table->boolean('can_review')->default(false)->after('mfa_mission_id');
            }
            if (!Schema::hasColumn('users', 'can_approve')) {
                $table->boolean('can_approve')->default(false)->after('can_review');
            }
        });

        // Add index separately to avoid issues
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'mfa_mission_id', 'is_active'], 'users_role_mission_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_mission_active_index');
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'mfa_mission_id')) {
                $table->dropForeign(['mfa_mission_id']);
                $table->dropColumn('mfa_mission_id');
            }
            if (Schema::hasColumn('users', 'can_review')) {
                $table->dropColumn('can_review');
            }
            if (Schema::hasColumn('users', 'can_approve')) {
                $table->dropColumn('can_approve');
            }
        });
    }
};
