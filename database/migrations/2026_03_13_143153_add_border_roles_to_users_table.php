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
        // Add new roles to the enum - with database driver compatibility
        if (DB::getDriverName() === 'mysql') {
            // MySQL: Use ALTER TABLE to modify ENUM
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('applicant', 'gis_officer', 'gis_admin', 'mfa_reviewer', 'mfa_admin', 'admin', 'immigration_officer', 'airline_staff') NOT NULL DEFAULT 'applicant'");
        } else {
            // SQLite: ENUM is stored as string, so this is already compatible
            // No action needed for SQLite as it treats ENUM as TEXT
            // The new roles can be inserted directly
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, check if any users have the new roles that we're about to remove
        $usersWithNewRoles = DB::table('users')
            ->whereIn('role', ['gis_admin', 'mfa_admin', 'immigration_officer', 'airline_staff'])
            ->count();
            
        if ($usersWithNewRoles > 0) {
            // Convert users with new roles to safe fallback roles
            DB::table('users')->where('role', 'gis_admin')->update(['role' => 'gis_officer']);
            DB::table('users')->where('role', 'mfa_admin')->update(['role' => 'mfa_reviewer']);
            DB::table('users')->where('role', 'immigration_officer')->update(['role' => 'admin']);
            DB::table('users')->where('role', 'airline_staff')->update(['role' => 'admin']);
        }

        // Remove the new roles from enum - with database driver compatibility
        if (DB::getDriverName() === 'mysql') {
            // MySQL: Use ALTER TABLE to modify ENUM back to original values
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('applicant', 'gis_officer', 'mfa_reviewer', 'admin') NOT NULL DEFAULT 'applicant'");
        } else {
            // SQLite: No action needed as ENUM is stored as TEXT
            // The constraint is handled at the application level
        }
    }
};
