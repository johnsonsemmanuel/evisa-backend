<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add submitted_awaiting_payment to status enum
        // SQLite doesn't enforce enums, so we just need to handle MySQL/Postgres
        // For MySQL we alter the enum; for SQLite it's already a string
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM(
                'draft',
                'submitted_awaiting_payment',
                'pending_payment',
                'submitted',
                'under_review',
                'additional_info_requested',
                'escalated',
                'pending_approval',
                'approved',
                'denied',
                'cancelled'
            ) DEFAULT 'draft'");
        }

        // 2. Rename service tiers to match spec (use temp names to avoid unique collisions):
        //    standard  -> standard (keep)
        //    express   -> fast_track
        //    premium   -> express
        //    emergency -> ultra_express

        // Step A: rename to temp names first
        DB::table('service_tiers')->where('code', 'express')->update(['code' => '_tmp_fast_track']);
        DB::table('service_tiers')->where('code', 'premium')->update(['code' => '_tmp_express']);
        DB::table('service_tiers')->where('code', 'emergency')->update(['code' => '_tmp_ultra_express']);

        // Step B: rename from temp to final names with updated metadata
        DB::table('service_tiers')->where('code', '_tmp_fast_track')->update([
            'code' => 'fast_track',
            'name' => 'Fast-Track Processing',
            'description' => 'Faster processing for time-sensitive travel',
            'processing_time_display' => '24-48 hours',
        ]);

        DB::table('service_tiers')->where('code', '_tmp_express')->update([
            'code' => 'express',
            'name' => 'Express Processing',
            'description' => 'Priority processing with dedicated support',
            'processing_time_display' => 'Same day (24 hours)',
        ]);

        DB::table('service_tiers')->where('code', '_tmp_ultra_express')->update([
            'code' => 'ultra_express',
            'name' => 'Ultra-Express Processing',
            'description' => 'Highest priority processing for urgent travel needs',
            'processing_time_display' => '4-6 hours',
        ]);

        // 3. Update step max from 4 to 6 in applications
        // (no schema change needed, just backend validation)
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM(
                'draft',
                'pending_payment',
                'submitted',
                'under_review',
                'additional_info_requested',
                'escalated',
                'approved',
                'denied',
                'cancelled'
            ) DEFAULT 'draft'");
        }

        // Revert tier renames
        DB::table('service_tiers')->where('code', 'fast_track')->update([
            'code' => 'express',
            'name' => 'Express Processing',
            'description' => 'Faster processing for time-sensitive travel',
            'processing_time_display' => '24-48 hours',
        ]);

        DB::table('service_tiers')->where('code', 'express')->update([
            'code' => 'premium',
            'name' => 'Premium Processing',
            'description' => 'Priority processing with dedicated support',
            'processing_time_display' => 'Same day (24 hours)',
        ]);

        DB::table('service_tiers')->where('code', 'ultra_express')->update([
            'code' => 'emergency',
            'name' => 'Emergency Processing',
            'description' => 'Urgent cases requiring immediate attention',
            'processing_time_display' => '4-6 hours',
        ]);
    }
};
