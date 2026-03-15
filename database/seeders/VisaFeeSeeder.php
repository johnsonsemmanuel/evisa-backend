<?php

namespace Database\Seeders;

use App\Models\FeeWaiver;
use App\Models\VisaFee;
use App\Models\VisaType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VisaFeeSeeder extends Seeder
{
    /**
     * Seed realistic Ghana eVisa fees.
     * 
     * Standard fees based on Ghana Immigration Service rates:
     * - Standard processing: GHS 500 (50000 pesewas)
     * - Express processing: GHS 750 (75000 pesewas)
     * - Emergency processing: GHS 1000 (100000 pesewas)
     * 
     * ECOWAS nationals receive full waiver per regional agreement.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedVisaFees();
            $this->seedFeeWaivers();
        });
    }

    /**
     * Seed visa fees for all visa types and processing tiers.
     */
    protected function seedVisaFees(): void
    {
        $visaTypes = VisaType::all();
        
        if ($visaTypes->isEmpty()) {
            $this->command->warn('No visa types found. Please run VisaTypesSeeder first.');
            return;
        }

        $processingTiers = [
            'standard' => 50000,  // GHS 500.00
            'express' => 75000,   // GHS 750.00
            'emergency' => 100000, // GHS 1000.00
        ];

        $nationalityCategories = ['all']; // Default: same fee for all nationalities

        foreach ($visaTypes as $visaType) {
            foreach ($processingTiers as $tier => $amount) {
                foreach ($nationalityCategories as $category) {
                    VisaFee::create([
                        'visa_type_id' => $visaType->id,
                        'nationality_category' => $category,
                        'processing_tier' => $tier,
                        'amount' => $amount,
                        'currency' => 'GHS',
                        'is_active' => true,
                        'effective_from' => now()->subYear(), // Effective from 1 year ago
                        'effective_until' => null, // No expiry
                        'created_by' => null, // System-created
                    ]);
                }
            }
        }

        $this->command->info('✓ Visa fees seeded: ' . ($visaTypes->count() * count($processingTiers)) . ' fee records created');
    }

    /**
     * Seed fee waivers for ECOWAS and other special categories.
     */
    protected function seedFeeWaivers(): void
    {
        // ECOWAS Full Waiver
        // Per ECOWAS Protocol on Free Movement of Persons
        FeeWaiver::create([
            'name' => 'ECOWAS_FULL_WAIVER',
            'description' => 'Full visa fee waiver for ECOWAS member state nationals per regional protocol',
            'nationality_codes' => [
                'BJ', // Benin
                'BF', // Burkina Faso
                'CV', // Cape Verde
                'CI', // Côte d\'Ivoire
                'GM', // Gambia
                'GH', // Ghana
                'GN', // Guinea
                'GW', // Guinea-Bissau
                'LR', // Liberia
                'ML', // Mali
                'NE', // Niger
                'NG', // Nigeria
                'SN', // Senegal
                'SL', // Sierra Leone
                'TG', // Togo
            ],
            'visa_type_id' => null, // Applies to all visa types
            'waiver_type' => 'full',
            'waiver_value' => 10000, // 100% (ignored for 'full' type)
            'is_active' => true,
            'effective_from' => now()->subYear(),
            'effective_until' => null,
            'created_by' => null,
        ]);

        // Diplomatic Waiver (Example - can be activated when needed)
        FeeWaiver::create([
            'name' => 'DIPLOMATIC_WAIVER',
            'description' => 'Full waiver for diplomatic passport holders',
            'nationality_codes' => ['*'], // Special marker for "all countries"
            'visa_type_id' => null,
            'waiver_type' => 'full',
            'waiver_value' => 10000,
            'is_active' => false, // Inactive by default - activate when diplomatic visa type is added
            'effective_from' => now(),
            'effective_until' => null,
            'created_by' => null,
        ]);

        // African Union Discount (Example - 50% discount)
        FeeWaiver::create([
            'name' => 'AFRICAN_UNION_DISCOUNT',
            'description' => '50% discount for African Union member states (non-ECOWAS)',
            'nationality_codes' => [
                'DZ', 'AO', 'BW', 'BI', 'CM', 'CF', 'TD', 'KM', 'CG', 'CD', 'DJ', 'EG', 'GQ', 'ER', 'ET', 'GA',
                'KE', 'LS', 'LY', 'MG', 'MW', 'MU', 'MA', 'MZ', 'NA', 'RW', 'ST', 'SC', 'SO', 'ZA', 'SS', 'SD',
                'SZ', 'TZ', 'TN', 'UG', 'ZM', 'ZW'
            ],
            'visa_type_id' => null,
            'waiver_type' => 'percentage',
            'waiver_value' => 5000, // 50% discount
            'is_active' => false, // Inactive by default - activate if policy changes
            'effective_from' => now(),
            'effective_until' => null,
            'created_by' => null,
        ]);

        $this->command->info('✓ Fee waivers seeded: 3 waiver policies created');
        $this->command->info('  - ECOWAS Full Waiver: ACTIVE');
        $this->command->info('  - Diplomatic Waiver: INACTIVE (activate when needed)');
        $this->command->info('  - African Union Discount: INACTIVE (activate if policy changes)');
    }
}
