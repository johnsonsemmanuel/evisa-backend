<?php

namespace App\Services;

use App\Models\Application;
use App\Models\VisaType;
use App\Models\ServiceTier;

/**
 * Unified Pricing Service
 *
 * All monetary results are in pesewas (integer) for data integrity.
 * Base Price: $260 = 26000 pesewas.
 *
 * Formulas:
 * - E-Visa: Base Price × Entry Multiplier × Processing Tier Multiplier + additional_fee (pesewas)
 * - Regular Visa: Base Price × Entry Multiplier (always Standard tier)
 */
class PricingService
{
    /** Base price in pesewas (26000 = GHS/USD 260.00) */
    const BASE_PRICE_PESEWAS = 26000;

    /**
     * Entry type multipliers
     */
    const ENTRY_MULTIPLIER_SINGLE = 1.0;
    const ENTRY_MULTIPLIER_MULTIPLE = 1.8;

    /**
     * Processing tier multipliers (E-Visa only)
     * Per specification section 5.1
     */
    const TIER_MULTIPLIERS = [
        'standard'      => 1.0,
        'priority'      => 1.3,
        'express'       => 1.7,
    ];

    /**
     * Calculate total price for an application.
     * 
     * @param Application|array $data Application model or array with pricing data
     * @return array Breakdown of pricing components
     */
    public function calculatePrice($data): array
    {
        // Extract data from Application model or array
        if ($data instanceof Application) {
            $visaChannel = $data->visa_channel;
            $entryType = $data->entry_type;
            $serviceTierCode = $data->serviceTier?->code ?? 'standard';
        } else {
            $visaChannel = $data['visa_channel'] ?? 'e-visa';
            $entryType = $data['entry_type'] ?? 'single';
            $serviceTierCode = $data['service_tier_code'] ?? 'standard';
        }

        // Normalize values
        $visaChannel = strtolower($visaChannel);
        $entryType = strtolower($entryType);
        $serviceTierCode = strtolower($serviceTierCode);

        // Step 1: Base in pesewas
        $basePesewas = self::BASE_PRICE_PESEWAS;

        // Step 2: Entry Type Multiplier
        $entryMultiplier = $this->getEntryMultiplier($entryType);

        // Step 3: Get Service Tier (additional_fee is stored in pesewas)
        $serviceTier = ServiceTier::where('code', $serviceTierCode)->first();
        $tierMultiplier = 1.0;
        $additionalFeePesewas = 0;

        if ($visaChannel === 'e-visa' && $serviceTier) {
            $tierMultiplier = $serviceTier->fee_multiplier;
            $additionalFeePesewas = (int) $serviceTier->additional_fee;
        }

        // All in pesewas (integer)
        $entryFeePesewas = (int) round($basePesewas * $entryMultiplier);
        $totalPesewas = (int) round($entryFeePesewas * $tierMultiplier) + $additionalFeePesewas;
        $processingFeePesewas = $totalPesewas - $entryFeePesewas;

        return [
            'base_price' => $basePesewas / 100,
            'entry_type' => $entryType,
            'entry_multiplier' => $entryMultiplier,
            'entry_fee' => $entryFeePesewas / 100,
            'visa_channel' => $visaChannel,
            'service_tier' => $serviceTierCode,
            'tier_multiplier' => $tierMultiplier,
            'additional_fee' => $additionalFeePesewas / 100,
            'processing_fee' => $processingFeePesewas,
            'total' => $totalPesewas,
            'total_pesewas' => $totalPesewas,
            'formula' => $this->getFormulaDescription($visaChannel, $entryType, $serviceTierCode),
        ];
    }

    /**
     * Get entry type multiplier.
     */
    protected function getEntryMultiplier(string $entryType): float
    {
        return match ($entryType) {
            'multiple' => self::ENTRY_MULTIPLIER_MULTIPLE,
            'single' => self::ENTRY_MULTIPLIER_SINGLE,
            default => self::ENTRY_MULTIPLIER_SINGLE,
        };
    }

    /**
     * Get processing tier multiplier.
     * Uses database values for E-Visa channel.
     */
    protected function getTierMultiplier(string $visaChannel, string $serviceTierCode): float
    {
        // Regular visa: no tier multiplier
        if ($visaChannel === 'regular' || $visaChannel === 'on-arrival') {
            return 1.0;
        }

        // E-Visa: use database fee multiplier
        $serviceTier = ServiceTier::where('code', $serviceTierCode)->first();
        return $serviceTier ? $serviceTier->fee_multiplier : 1.0;
    }

    /**
     * Get formula description for transparency.
     */
    protected function getFormulaDescription(string $visaChannel, string $entryType, string $serviceTierCode): string
    {
        $base = self::BASE_PRICE_PESEWAS / 100;
        $entryMult = $this->getEntryMultiplier($entryType);
        $tierMult = $this->getTierMultiplier($visaChannel, $serviceTierCode);

        if ($visaChannel === 'e-visa') {
            return "{$base} × {$entryMult} × {$tierMult}";
        }

        return "{$base} × {$entryMult}";
    }

    /**
     * Calculate and update application total fee.
     */
    public function calculateAndUpdateApplicationFee(Application $application): Application
    {
        $pricing = $this->calculatePrice($application);
        $totalPesewas = (int) $pricing['total_pesewas'];
        $processingPesewas = (int) $pricing['processing_fee'];

        $application->update([
            'total_fee' => $totalPesewas,
            'processing_fee' => $processingPesewas,
            'government_fee' => 0,
            'platform_fee' => 0,
        ]);

        return $application->fresh();
    }

    /**
     * Validate that submitted pesewas matches the calculated fee.
     */
    public function validatePrice(Application $application, int $submittedPesewas, int $tolerancePesewas = 1): bool
    {
        $pricing = $this->calculatePrice($application);
        $calculatedPesewas = (int) $pricing['total_pesewas'];

        return abs($calculatedPesewas - $submittedPesewas) <= $tolerancePesewas;
    }

    /**
     * Get pricing preview for given parameters.
     * Used by API endpoint for frontend price display.
     */
    public function getPricingPreview(array $params): array
    {
        return $this->calculatePrice($params);
    }

    /**
     * Get all available service tiers with their multipliers.
     */
    public function getServiceTiers(): array
    {
        return [
            [
                'code' => 'standard',
                'name' => 'Standard Processing',
                'multiplier' => self::TIER_MULTIPLIERS['standard'],
                'description' => '3-5 business days',
            ],
            [
                'code' => 'priority',
                'name' => 'Priority Processing',
                'multiplier' => self::TIER_MULTIPLIERS['priority'],
                'description' => 'Within 48 hours',
            ],
            [
                'code' => 'express',
                'name' => 'Express Processing',
                'multiplier' => self::TIER_MULTIPLIERS['express'],
                'description' => 'Within 5 hours',
            ],
        ];
    }

    /**
     * Calculate example prices for documentation/display.
     * Per specification section 5.2
     */
    public function getExamplePrices(): array
    {
        $base = self::BASE_PRICE_PESEWAS / 100;
        return [
            ['description' => 'E-Visa, Single Entry, Standard', 'calculation' => "{$base} × 1.0 × 1.0", 'price' => 260.00],
            ['description' => 'E-Visa, Single Entry, Priority', 'calculation' => "{$base} × 1.0 × 1.3", 'price' => 338.00],
            ['description' => 'E-Visa, Single Entry, Express', 'calculation' => "{$base} × 1.0 × 1.7", 'price' => 442.00],
            ['description' => 'E-Visa, Multiple Entry, Standard', 'calculation' => "{$base} × 1.8 × 1.0", 'price' => 468.00],
            ['description' => 'E-Visa, Multiple Entry, Priority', 'calculation' => "{$base} × 1.8 × 1.3", 'price' => 608.40],
            ['description' => 'E-Visa, Multiple Entry, Express', 'calculation' => "{$base} × 1.8 × 1.7", 'price' => 795.60],
            ['description' => 'Regular Visa, Single Entry', 'calculation' => "{$base} × 1.0", 'price' => 260.00],
            ['description' => 'Regular Visa, Multiple Entry', 'calculation' => "{$base} × 1.8", 'price' => 468.00],
        ];
    }
}
