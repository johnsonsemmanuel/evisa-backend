<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate legacy tier names to spec-compliant names.
     * 
     * Legacy → Spec:
     * - fast_track → priority
     * - regular → standard
     */
    public function up(): void
    {
        // Update applications table
        DB::table('applications')
            ->where('processing_tier', 'fast_track')
            ->update(['processing_tier' => 'priority']);
        
        DB::table('applications')
            ->where('processing_tier', 'regular')
            ->update(['processing_tier' => 'standard']);
        
        // Update tier_rules table
        DB::table('tier_rules')
            ->where('processing_tier', 'fast_track')
            ->update(['processing_tier' => 'priority']);
        
        DB::table('tier_rules')
            ->where('processing_tier', 'regular')
            ->update(['processing_tier' => 'standard']);
        
        // Update ETA status from 'approved' to 'issued' for consistency
        DB::table('eta_applications')
            ->where('status', 'approved')
            ->update(['status' => 'issued']);
    }

    public function down(): void
    {
        // Revert changes
        DB::table('applications')
            ->where('processing_tier', 'priority')
            ->update(['processing_tier' => 'fast_track']);
        
        DB::table('applications')
            ->where('processing_tier', 'standard')
            ->whereNotNull('service_tier_id')
            ->update(['processing_tier' => 'regular']);
        
        DB::table('tier_rules')
            ->where('processing_tier', 'priority')
            ->update(['processing_tier' => 'fast_track']);
        
        DB::table('tier_rules')
            ->where('processing_tier', 'standard')
            ->update(['processing_tier' => 'regular']);
        
        DB::table('eta_applications')
            ->where('status', 'issued')
            ->update(['status' => 'approved']);
    }
};
