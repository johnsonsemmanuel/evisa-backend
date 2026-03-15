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
        // Remove unwanted tiers (priority, fast_track, ultra_express)
        $unwantedTierIds = DB::table('service_tiers')
            ->whereIn('code', ['priority', 'fast_track', 'ultra_express'])
            ->pluck('id');
        
        // Update applications using removed tiers to use standard
        $standardTierId = DB::table('service_tiers')->where('code', 'standard')->value('id');
        if ($standardTierId && $unwantedTierIds->isNotEmpty()) {
            DB::table('applications')
                ->whereIn('service_tier_id', $unwantedTierIds)
                ->update(['service_tier_id' => $standardTierId]);
        }
        
        // Delete unwanted tiers
        DB::table('service_tiers')->whereIn('code', ['priority', 'fast_track', 'ultra_express'])->delete();

        // Ensure we have exactly 3 tiers with correct data
        $standardId = DB::table('service_tiers')->where('code', 'standard')->value('id');
        $expressId = DB::table('service_tiers')->where('code', 'express')->value('id');
        $premiumId = DB::table('service_tiers')->where('code', 'premium')->value('id');

        // Update standard tier
        if ($standardId) {
            DB::table('service_tiers')->where('id', $standardId)->update([
                'name' => 'Standard Processing',
                'description' => 'Standard visa processing with regular SLA',
                'processing_hours' => 120,
                'processing_time_display' => '3-5 business days',
                'fee_multiplier' => 1.00,
                'additional_fee' => 0.00,
                'sort_order' => 1,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        // Update express tier
        if ($expressId) {
            DB::table('service_tiers')->where('id', $expressId)->update([
                'name' => 'Express Processing',
                'description' => 'Priority visa processing — 30% surcharge',
                'processing_hours' => 72,
                'processing_time_display' => '2-3 business days',
                'fee_multiplier' => 1.30,
                'additional_fee' => 0.00,
                'sort_order' => 2,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        // Update premium tier
        if ($premiumId) {
            DB::table('service_tiers')->where('id', $premiumId)->update([
                'name' => 'Premium Processing',
                'description' => 'Expedited visa processing — 50% surcharge',
                'processing_hours' => 48,
                'processing_time_display' => '24-48 hours',
                'fee_multiplier' => 1.50,
                'additional_fee' => 0.00,
                'sort_order' => 3,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it cleans up data
    }
};