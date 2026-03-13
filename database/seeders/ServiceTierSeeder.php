<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'code' => 'standard',
                'name' => 'Standard Processing',
                'description' => 'Standard visa processing with regular SLA',
                'processing_hours' => 120,
                'processing_time_display' => '3-5 business days',
                'fee_multiplier' => 1.00,
                'additional_fee' => 0,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'express',
                'name' => 'Express Processing',
                'description' => 'Priority visa processing — 30% surcharge',
                'processing_hours' => 72,
                'processing_time_display' => '2-3 business days',
                'fee_multiplier' => 1.30,
                'additional_fee' => 0,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'premium',
                'name' => 'Premium Processing',
                'description' => 'Expedited visa processing — 50% surcharge',
                'processing_hours' => 48,
                'processing_time_display' => '24-48 hours',
                'fee_multiplier' => 1.50,
                'additional_fee' => 0,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($tiers as $tier) {
            DB::table('service_tiers')->insert(array_merge($tier, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
