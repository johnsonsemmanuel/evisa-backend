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
        // Add 'suspicious' status to payments table enum - with database driver compatibility
        if (DB::getDriverName() === 'mysql') {
            // MySQL: Use ALTER TABLE to modify ENUM
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('initiated', 'pending', 'completed', 'failed', 'expired', 'cancelled', 'refunded', 'pending_verification', 'suspicious') NOT NULL DEFAULT 'initiated'");
        } else {
            // SQLite: ENUM is stored as string, so this is already compatible
            // No action needed for SQLite as it treats ENUM as TEXT
            // The new status can be inserted directly
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, check if any payments have the suspicious status that we're about to remove
        $suspiciousPayments = DB::table('payments')->where('status', 'suspicious')->count();
        
        if ($suspiciousPayments > 0) {
            // Convert suspicious payments to failed status
            DB::table('payments')->where('status', 'suspicious')->update(['status' => 'failed']);
        }

        // Remove 'suspicious' status - with database driver compatibility
        if (DB::getDriverName() === 'mysql') {
            // MySQL: Use ALTER TABLE to modify ENUM back to original values
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('initiated', 'pending', 'completed', 'failed', 'expired', 'cancelled', 'refunded', 'pending_verification') NOT NULL DEFAULT 'initiated'");
        } else {
            // SQLite: No action needed as ENUM is stored as TEXT
            // The constraint is handled at the application level
        }
    }
};
