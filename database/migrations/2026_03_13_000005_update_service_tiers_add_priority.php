<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ServiceTier;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing express tier to match spec (1.7 multiplier, 5 hours)
        ServiceTier::where('code', 'express')->update([
            'fee_multiplier' => 1.7,
            'processing_hours' => 5,
            'processing_time_display' => 'Within 5 hours',
            'additional_fee' => 0,
        ]);
        
        // Create priority tier (1.3 multiplier, 48 hours)
        ServiceTier::updateOrCreate(
            ['code' => 'priority'],
            [
                'name' => 'Priority Processing',
                'description' => 'Expedited processing within 48 hours',
                'processing_hours' => 48,
                'processing_time_display' => 'Within 48 hours',
                'fee_multiplier' => 1.3,
                'additional_fee' => 0,
                'sort_order' => 2,
            ]
        );
        
        // Update sort orders
        ServiceTier::where('code', 'standard')->update(['sort_order' => 1]);
        ServiceTier::where('code', 'priority')->update(['sort_order' => 2]);
        ServiceTier::where('code', 'express')->update(['sort_order' => 3]);
    }

    public function down(): void
    {
        // Revert express tier
        ServiceTier::where('code', 'express')->update([
            'fee_multiplier' => 1.5,
            'processing_hours' => 48,
            'processing_time_display' => '24-48 hours',
            'additional_fee' => 25,
        ]);
        
        // Remove priority tier
        ServiceTier::where('code', 'priority')->delete();
    }
};
