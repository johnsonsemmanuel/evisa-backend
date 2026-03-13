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
        // Update applications using fast_track to use express instead
        DB::table('applications')
            ->where('processing_tier', 'fast_track')
            ->update(['processing_tier' => 'express']);

        // Update any routing rules that reference fast_track
        DB::table('routing_rules')
            ->where('conditions', 'like', '%fast_track%')
            ->update([
                'conditions' => DB::raw("REPLACE(conditions, 'fast_track', 'express')")
            ]);

        // Update tier rules if they exist
        if (Schema::hasTable('tier_rules')) {
            DB::table('tier_rules')
                ->where('processing_tier', 'fast_track')
                ->update(['processing_tier' => 'express']);
        }

        // Remove fast_track service tier
        DB::table('service_tiers')->where('code', 'fast_track')->delete();

        // Add premium tier if it doesn't exist
        $premiumExists = DB::table('service_tiers')->where('code', 'premium')->exists();
        if (!$premiumExists) {
            DB::table('service_tiers')->insert([
                'code' => 'premium',
                'name' => 'Premium Processing',
                'description' => 'Expedited visa processing — 50% surcharge',
                'processing_hours' => 48,
                'processing_time_display' => '24-48 hours',
                'fee_multiplier' => 1.50,
                'additional_fee' => 0,
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update express tier to have 30% surcharge (was 50%)
        DB::table('service_tiers')
            ->where('code', 'express')
            ->update([
                'description' => 'Priority visa processing — 30% surcharge',
                'processing_hours' => 72,
                'processing_time_display' => '2-3 business days',
                'fee_multiplier' => 1.30,
                'sort_order' => 2,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add fast_track tier
        DB::table('service_tiers')->insert([
            'code' => 'fast_track',
            'name' => 'Fast-Track Processing',
            'description' => 'Accelerated visa processing — 30% surcharge',
            'processing_hours' => 72,
            'processing_time_display' => '1-3 business days',
            'fee_multiplier' => 1.30,
            'additional_fee' => 0,
            'is_active' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Revert express tier changes
        DB::table('service_tiers')
            ->where('code', 'express')
            ->update([
                'description' => 'Priority visa processing — 50% surcharge',
                'processing_hours' => 48,
                'processing_time_display' => '24-48 hours',
                'fee_multiplier' => 1.50,
                'sort_order' => 3,
                'updated_at' => now(),
            ]);

        // Remove premium tier
        DB::table('service_tiers')->where('code', 'premium')->delete();
    }
};