<?php

namespace App\Services;

use App\Models\VisaType;

class EtaEligibilityService
{
    /**
     * ECOWAS member states - Visa-free, ETA required for digital registration.
     */
    protected array $ecowasCountries = [
        'NG', // Nigeria
        'SN', // Senegal
        'CI', // Côte d'Ivoire
        'ML', // Mali
        'BF', // Burkina Faso
        'NE', // Niger
        'BJ', // Benin
        'TG', // Togo
        'GN', // Guinea
        'SL', // Sierra Leone
        'LR', // Liberia
        'GM', // Gambia
        'GW', // Guinea-Bissau
        'CV', // Cape Verde
    ];

    /**
     * African Union countries eligible for ETA (replaces Visa on Arrival).
     */
    protected array $auCountries = [
        // East Africa
        'KE', 'TZ', 'RW', 'UG', 'ET', 'DJ', 'SO', 'SS', 'SD', 'ER',
        // Southern Africa
        'ZA', 'NA', 'BW', 'ZW', 'ZM', 'MW', 'MZ', 'AO', 'SZ', 'LS',
        // North Africa
        'MA', 'TN', 'DZ', 'EG', 'LY',
        // Central Africa
        'GA', 'CM', 'CG', 'CD', 'CF', 'TD', 'GQ', 'ST',
    ];

    /**
     * Caribbean/friendly countries with visa waiver - ETA required.
     */
    protected array $caribbeanCountries = [
        'BB', // Barbados
        'BS', // Bahamas
        'GD', // Grenada
        'JM', // Jamaica
        'TT', // Trinidad & Tobago
        'AG', // Antigua and Barbuda
        'DM', // Dominica
        'KN', // Saint Kitts and Nevis
        'LC', // Saint Lucia
        'VC', // Saint Vincent and the Grenadines
    ];

    /**
     * Countries requiring full eVisa/Embassy visa.
     */
    protected array $visaRequiredRegions = [
        'europe' => ['GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'PT', 'AT', 'CH', 'SE', 'NO', 'DK', 'FI', 'IE', 'PL', 'CZ', 'GR', 'HU', 'RO'],
        'north_america' => ['US', 'CA', 'MX'],
        'asia' => ['CN', 'IN', 'JP', 'KR', 'PH', 'ID', 'MY', 'TH', 'VN', 'SG', 'PK', 'BD', 'LK', 'NP'],
        'oceania' => ['AU', 'NZ'],
        'middle_east' => ['AE', 'SA', 'QA', 'KW', 'BH', 'OM', 'IL', 'TR', 'IR', 'IQ'],
    ];

    /**
     * Determine authorization type for a nationality.
     */
    public function getAuthorizationType(string $nationality): array
    {
        $nationality = strtoupper($nationality);

        // Ghana citizens - no authorization needed
        if ($nationality === 'GH') {
            return [
                'type' => 'citizen',
                'authorization' => 'none',
                'message' => 'Ghana citizen - no visa required',
            ];
        }

        // ECOWAS - ETA only (digital registration)
        if (in_array($nationality, $this->ecowasCountries)) {
            return [
                'type' => 'ecowas',
                'authorization' => 'eta',
                'eta_type' => 'ecowas-eta',
                'fee' => 10.00,
                'validity_days' => 90,
                'entry_type' => 'multiple',
                'message' => 'ECOWAS citizen - ETA required for digital registration',
            ];
        }

        // African Union - ETA (replaces VOA)
        if (in_array($nationality, $this->auCountries)) {
            return [
                'type' => 'african_union',
                'authorization' => 'eta',
                'eta_type' => 'au-eta',
                'fee' => 25.00,
                'validity_days' => 30,
                'entry_type' => 'single',
                'message' => 'African Union citizen - ETA replaces Visa on Arrival',
            ];
        }

        // Caribbean - ETA
        if (in_array($nationality, $this->caribbeanCountries)) {
            return [
                'type' => 'caribbean',
                'authorization' => 'eta',
                'eta_type' => 'caribbean-eta',
                'fee' => 15.00,
                'validity_days' => 90,
                'entry_type' => 'single',
                'message' => 'Caribbean visa-waiver country - ETA required',
            ];
        }

        // All others - eVisa required
        return [
            'type' => 'visa_required',
            'authorization' => 'evisa',
            'message' => 'eVisa or Embassy visa required',
            'apply_url' => '/apply',
        ];
    }

    /**
     * Check if nationality is ETA eligible.
     */
    public function isEtaEligible(string $nationality): bool
    {
        $result = $this->getAuthorizationType($nationality);
        return $result['authorization'] === 'eta';
    }

    /**
     * Check if nationality is visa-exempt (ECOWAS).
     */
    public function isVisaExempt(string $nationality): bool
    {
        $nationality = strtoupper($nationality);
        return $nationality === 'GH' || in_array($nationality, $this->ecowasCountries);
    }

    /**
     * Check if nationality requires full visa.
     */
    public function requiresVisa(string $nationality): bool
    {
        $result = $this->getAuthorizationType($nationality);
        return $result['authorization'] === 'evisa';
    }

    /**
     * Get all ETA eligible nationalities.
     */
    public function getAllEtaEligibleNationalities(): array
    {
        return [
            'ecowas' => $this->ecowasCountries,
            'african_union' => $this->auCountries,
            'caribbean' => $this->caribbeanCountries,
        ];
    }

    /**
     * Get ETA types available for a nationality.
     */
    public function getAvailableEtaTypes(string $nationality): array
    {
        $nationality = strtoupper($nationality);

        return VisaType::where('category', 'eta')
            ->where('is_active', true)
            ->get()
            ->filter(function ($type) use ($nationality) {
                if (empty($type->eligible_nationalities)) {
                    return false; // ETA requires specific nationalities
                }
                return in_array($nationality, $type->eligible_nationalities);
            })
            ->values()
            ->toArray();
    }

    /**
     * Get routing logic for traveler category.
     */
    public function getRoutingLogic(): array
    {
        return [
            [
                'category' => 'ECOWAS nationals',
                'authorization' => 'ETA only',
                'processing' => 'Auto-approval (no security flags)',
                'fee' => '$10',
                'validity' => '90 days',
            ],
            [
                'category' => 'AU nationals',
                'authorization' => 'ETA replacing VOA',
                'processing' => 'GIS review (1-2 days)',
                'fee' => '$25',
                'validity' => '30 days',
            ],
            [
                'category' => 'Visa waiver countries',
                'authorization' => 'ETA',
                'processing' => 'GIS review (1 day)',
                'fee' => '$15',
                'validity' => '90 days',
            ],
            [
                'category' => 'Visa required countries',
                'authorization' => 'eVisa or Embassy visa',
                'processing' => 'Full application review',
                'fee' => 'Varies by visa type',
                'validity' => 'Varies',
            ],
        ];
    }
}
