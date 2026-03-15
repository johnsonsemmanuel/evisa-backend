<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds missing payment status values: expired, pending_verification, cancelled
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table
        // For MySQL/PostgreSQL, we can alter the enum
        
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, and ENUM is stored as TEXT anyway
            // So we don't need to do anything - the new status values can be inserted directly
            // SQLite treats ENUM as TEXT, so any string value is valid
        } else {
            // For MySQL/PostgreSQL - keep 'completed' for now, will be migrated to 'paid' in next migration
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'refunded', 'expired', 'pending_verification', 'cancelled') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite: No action needed as ENUM is stored as TEXT
            // The constraint is handled at the application level
        } else {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending'");
        }
    }
};
