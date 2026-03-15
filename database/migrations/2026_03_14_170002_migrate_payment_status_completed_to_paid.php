<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrates 'completed' status to 'paid' and updates enum definition
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        
        // First, update the enum to include both 'completed' and 'paid'
        if ($driver === 'sqlite') {
            // SQLite stores as string, no enum constraint
        } elseif ($driver === 'mysql') {
            // MySQL: Add 'paid' to enum first
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('initiated', 'pending', 'processing', 'completed', 'paid', 'failed', 'refunded', 'expired', 'pending_verification', 'cancelled', 'suspicious') DEFAULT 'initiated'");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Add the new value
            DB::statement("ALTER TYPE payment_status ADD VALUE IF NOT EXISTS 'paid'");
            DB::statement("ALTER TYPE payment_status ADD VALUE IF NOT EXISTS 'initiated'");
            DB::statement("ALTER TYPE payment_status ADD VALUE IF NOT EXISTS 'suspicious'");
        }
        
        // Now update all existing 'completed' records to 'paid'
        DB::table('payments')
            ->where('status', 'completed')
            ->update(['status' => 'paid']);
        
        // Finally, remove 'completed' from the enum
        if ($driver === 'mysql') {
            // MySQL: Update enum definition to remove 'completed'
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('initiated', 'processing', 'paid', 'failed', 'refunded', 'expired', 'pending_verification', 'cancelled', 'suspicious') DEFAULT 'initiated'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        
        // Migrate 'paid' back to 'completed'
        DB::table('payments')
            ->where('status', 'paid')
            ->update(['status' => 'completed']);
        
        if ($driver === 'mysql') {
            // Restore original enum with 'completed'
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'refunded', 'expired', 'pending_verification', 'cancelled') DEFAULT 'pending'");
        }
        // SQLite and PostgreSQL don't need enum rollback
    }
};
