<?php

namespace Database\Seeders;

use App\Models\ReasonCode;
use App\Models\ServiceTier;
use App\Models\TierRule;
use App\Models\VisaType;
use Illuminate\Database\Seeder;

class Phase1Seeder extends Seeder
{
    public function run(): void
    {
        $this->seedServiceTiers();
        $this->seedReasonCodes();
        $this->seedVisaTypes();
    }

    private function seedServiceTiers(): void
    {
        $tiers = [
            [
                'code' => 'standard',
                'name' => 'Standard Processing',
                'description' => 'Regular processing time for non-urgent applications',
                'processing_hours' => 120, // 3-5 business days
                'processing_time_display' => '3-5 business days',
                'fee_multiplier' => 1.00,
                'additional_fee' => 0,
                'sort_order' => 1,
            ],
            [
                'code' => 'priority',
                'name' => 'Priority Processing',
                'description' => 'Expedited processing within 48 hours',
                'processing_hours' => 48,
                'processing_time_display' => 'Within 48 hours',
                'fee_multiplier' => 1.30,
                'additional_fee' => 0,
                'sort_order' => 2,
            ],
            [
                'code' => 'express',
                'name' => 'Express Processing',
                'description' => 'Ultra-fast processing within 5 hours',
                'processing_hours' => 5,
                'processing_time_display' => 'Within 5 hours',
                'fee_multiplier' => 1.70,
                'additional_fee' => 0,
                'sort_order' => 3,
            ],
        ];

        foreach ($tiers as $tier) {
            ServiceTier::updateOrCreate(['code' => $tier['code']], $tier);
        }

        // Deactivate any other tiers
        ServiceTier::whereNotIn('code', ['standard', 'priority', 'express'])->delete();
    }

    private function seedReasonCodes(): void
    {
        $codes = [
            // Approval Codes
            ['code' => 'AP01', 'action_type' => 'approve', 'reason' => 'All documents verified', 'description' => 'All submitted documents have been verified and meet requirements', 'sort_order' => 1],
            ['code' => 'AP02', 'action_type' => 'approve', 'reason' => 'Background check passed', 'description' => 'Security and background verification completed successfully', 'sort_order' => 2],
            ['code' => 'AP03', 'action_type' => 'approve', 'reason' => 'Meets eligibility criteria', 'description' => 'Applicant meets all eligibility requirements for this visa type', 'sort_order' => 3],
            ['code' => 'AP04', 'action_type' => 'approve', 'reason' => 'Valid invitation/sponsorship', 'description' => 'Invitation letter or sponsorship documents verified', 'sort_order' => 4],
            ['code' => 'AP05', 'action_type' => 'approve', 'reason' => 'Diplomatic clearance received', 'description' => 'Required diplomatic clearances obtained', 'sort_order' => 5],

            // Rejection Codes
            ['code' => 'RJ01', 'action_type' => 'reject', 'reason' => 'Incomplete documentation', 'description' => 'Required documents missing or incomplete after multiple requests', 'sort_order' => 1],
            ['code' => 'RJ02', 'action_type' => 'reject', 'reason' => 'Fraudulent documents', 'description' => 'Submitted documents identified as fraudulent or forged', 'sort_order' => 2],
            ['code' => 'RJ03', 'action_type' => 'reject', 'reason' => 'Security concern', 'description' => 'Application flagged due to security concerns', 'sort_order' => 3],
            ['code' => 'RJ04', 'action_type' => 'reject', 'reason' => 'Previous visa violation', 'description' => 'Applicant has history of visa violations or overstay', 'sort_order' => 4],
            ['code' => 'RJ05', 'action_type' => 'reject', 'reason' => 'Ineligible nationality', 'description' => 'Applicant nationality not eligible for this visa type', 'sort_order' => 5],
            ['code' => 'RJ06', 'action_type' => 'reject', 'reason' => 'Criminal record', 'description' => 'Applicant has disqualifying criminal history', 'sort_order' => 6],
            ['code' => 'RJ07', 'action_type' => 'reject', 'reason' => 'Insufficient funds', 'description' => 'Unable to demonstrate sufficient financial means', 'sort_order' => 7],
            ['code' => 'RJ08', 'action_type' => 'reject', 'reason' => 'Invalid passport', 'description' => 'Passport expired, damaged, or insufficient validity', 'sort_order' => 8],

            // Request More Info Codes
            ['code' => 'RMI01', 'action_type' => 'request_info', 'reason' => 'Passport bio page unclear', 'description' => 'Please upload a clearer scan of passport bio page', 'sort_order' => 1],
            ['code' => 'RMI02', 'action_type' => 'request_info', 'reason' => 'Photo does not meet requirements', 'description' => 'Please upload a passport-style photo meeting specifications', 'sort_order' => 2],
            ['code' => 'RMI03', 'action_type' => 'request_info', 'reason' => 'Invitation letter required', 'description' => 'Please provide invitation letter from host organization', 'sort_order' => 3],
            ['code' => 'RMI04', 'action_type' => 'request_info', 'reason' => 'Proof of accommodation needed', 'description' => 'Please provide hotel booking or accommodation details', 'sort_order' => 4],
            ['code' => 'RMI05', 'action_type' => 'request_info', 'reason' => 'Flight itinerary required', 'description' => 'Please provide confirmed flight booking details', 'sort_order' => 5],
            ['code' => 'RMI06', 'action_type' => 'request_info', 'reason' => 'Bank statement required', 'description' => 'Please provide recent bank statements (last 3 months)', 'sort_order' => 6],
            ['code' => 'RMI07', 'action_type' => 'request_info', 'reason' => 'Employment verification needed', 'description' => 'Please provide employment letter or business registration', 'sort_order' => 7],
            ['code' => 'RMI08', 'action_type' => 'request_info', 'reason' => 'Medical certificate required', 'description' => 'Please provide required medical certificates', 'sort_order' => 8],

            // Escalation Codes
            ['code' => 'ESC01', 'action_type' => 'escalate', 'reason' => 'Security flag - requires MFA review', 'description' => 'Application flagged for security review by MFA', 'sort_order' => 1],
            ['code' => 'ESC02', 'action_type' => 'escalate', 'reason' => 'Complex case - senior review', 'description' => 'Case requires senior officer assessment', 'sort_order' => 2],
            ['code' => 'ESC03', 'action_type' => 'escalate', 'reason' => 'Diplomatic case', 'description' => 'Requires diplomatic clearance or foreign ministry coordination', 'sort_order' => 3],
            ['code' => 'ESC04', 'action_type' => 'escalate', 'reason' => 'Policy exception required', 'description' => 'Case requires policy exception approval', 'sort_order' => 4],

            // Border Control - Admit (BP-A codes)
            ['code' => 'BP-A01', 'action_type' => 'border_admit', 'reason' => 'Visa/ETA valid and matched', 'description' => 'Travel authorization verified and passport matched', 'sort_order' => 1],
            ['code' => 'BP-A02', 'action_type' => 'border_admit', 'reason' => 'Secondary cleared', 'description' => 'Cleared after secondary inspection', 'sort_order' => 2],
            ['code' => 'BP-A03', 'action_type' => 'border_admit', 'reason' => 'Supervisor override approved', 'description' => 'Entry approved by supervisor override', 'sort_order' => 3],
            ['code' => 'BP-A04', 'action_type' => 'border_admit', 'reason' => 'ECOWAS citizen - visa exempt', 'description' => 'ECOWAS national with valid travel document', 'sort_order' => 4],
            ['code' => 'BP-A05', 'action_type' => 'border_admit', 'reason' => 'Diplomatic passport holder', 'description' => 'Diplomatic immunity verified', 'sort_order' => 5],

            // Border Control - Secondary (BP-S codes)
            ['code' => 'BP-S01', 'action_type' => 'border_secondary', 'reason' => 'Document clarity issue', 'description' => 'Documents require detailed inspection for clarity', 'sort_order' => 1],
            ['code' => 'BP-S02', 'action_type' => 'border_secondary', 'reason' => 'Host/accommodation verification', 'description' => 'Need to verify host or accommodation details', 'sort_order' => 2],
            ['code' => 'BP-S03', 'action_type' => 'border_secondary', 'reason' => 'Travel purpose clarification', 'description' => 'Purpose of visit requires clarification', 'sort_order' => 3],
            ['code' => 'BP-S04', 'action_type' => 'border_secondary', 'reason' => 'Payment confirmation pending', 'description' => 'Visa payment status needs verification', 'sort_order' => 4],
            ['code' => 'BP-S05', 'action_type' => 'border_secondary', 'reason' => 'PNR/flight mismatch', 'description' => 'Flight manifest does not match travel documents', 'sort_order' => 5],
            ['code' => 'BP-S06', 'action_type' => 'border_secondary', 'reason' => 'Interview required', 'description' => 'Traveler requires interview with immigration officer', 'sort_order' => 6],

            // Border Control - Deny/Hold (BP-D codes)
            ['code' => 'BP-D01', 'action_type' => 'border_deny', 'reason' => 'No valid authorization', 'description' => 'Traveler lacks valid visa or ETA', 'sort_order' => 1],
            ['code' => 'BP-D02', 'action_type' => 'border_deny', 'reason' => 'Passport mismatch', 'description' => 'Passport does not match visa/ETA record', 'sort_order' => 2],
            ['code' => 'BP-D03', 'action_type' => 'border_deny', 'reason' => 'Watchlist/Interpol hit', 'description' => 'Traveler flagged on security watchlist or Interpol', 'sort_order' => 3],
            ['code' => 'BP-D04', 'action_type' => 'border_deny', 'reason' => 'Suspected fraud', 'description' => 'Fraudulent documents or identity suspected', 'sort_order' => 4],
            ['code' => 'BP-D05', 'action_type' => 'border_deny', 'reason' => 'Expired/invalid entry window', 'description' => 'Visa expired or outside valid entry dates', 'sort_order' => 5],
            ['code' => 'BP-D06', 'action_type' => 'border_deny', 'reason' => 'Entry limit exceeded', 'description' => 'Maximum entries for visa type exceeded', 'sort_order' => 6],
        ];

        foreach ($codes as $code) {
            ReasonCode::updateOrCreate(['code' => $code['code']], $code);
        }
    }

    private function seedVisaTypes(): void
    {
        // Deactivate all visa types except tourism and business
        VisaType::whereNotIn('slug', ['tourism', 'business'])->update(['is_active' => false]);

        // Update existing visa types with new fields
        VisaType::where('slug', 'tourism')->update([
            'government_fee' => 40.00,
            'platform_fee' => 10.00,
            'entry_type' => 'single',
            'validity_period' => '30-90 days',
            'category' => 'visa',
            'sort_order' => 1,
            'required_fields' => [
                'passport_number', 'passport_expiry', 'nationality', 'date_of_birth',
                'intended_arrival_date', 'intended_departure_date', 'port_of_entry',
                'address_in_ghana', 'email', 'phone'
            ],
            'optional_fields' => ['hotel_booking', 'return_ticket'],
            'default_processing_days' => 5,
            'default_route_to' => 'gis',
            'is_active' => true,
        ]);

        VisaType::where('slug', 'business')->update([
            'government_fee' => 80.00,
            'platform_fee' => 20.00,
            'entry_type' => 'multiple',
            'validity_period' => '90 days - 1 year',
            'category' => 'visa',
            'sort_order' => 2,
            'required_fields' => [
                'passport_number', 'passport_expiry', 'nationality', 'date_of_birth',
                'intended_arrival_date', 'intended_departure_date', 'company_name',
                'company_address', 'invitation_letter', 'email', 'phone'
            ],
            'optional_fields' => ['business_registration', 'conference_registration'],
            'default_processing_days' => 5,
            'default_route_to' => 'gis',
            'is_active' => true,
        ]);

        // Create tier rules for tourism and business only
        $this->createTierRulesForActiveVisaTypes();
    }

    private function createTierRulesForActiveVisaTypes(): void
    {
        $visaTypes = VisaType::whereIn('slug', ['tourism', 'business'])->get();

        foreach ($visaTypes as $visaType) {
            // Standard tier - routes to MFA
            TierRule::updateOrCreate(
                ['visa_type_id' => $visaType->id, 'processing_tier' => 'standard'],
                [
                    'tier' => 'tier_1',
                    'name' => "Standard {$visaType->name}",
                    'description' => "Standard processing for {$visaType->name} (3-5 business days)",
                    'conditions' => [],
                    'route_to' => 'mfa', // Per spec: E-Visa Standard routes to MFA
                    'sla_hours' => 120, // 5 days
                    'priority' => 5,
                    'price_multiplier' => 1.00,
                    'is_active' => true,
                ]
            );

            // Priority tier - routes to GIS
            TierRule::updateOrCreate(
                ['visa_type_id' => $visaType->id, 'processing_tier' => 'priority'],
                [
                    'tier' => 'tier_1',
                    'name' => "Priority {$visaType->name}",
                    'description' => "Priority processing for {$visaType->name} (within 48 hours)",
                    'conditions' => [],
                    'route_to' => 'gis', // Per spec: E-Visa Priority routes to GIS
                    'sla_hours' => 48,
                    'priority' => 10,
                    'price_multiplier' => 1.30,
                    'is_active' => true,
                ]
            );

            // Express tier - routes to GIS
            TierRule::updateOrCreate(
                ['visa_type_id' => $visaType->id, 'processing_tier' => 'express'],
                [
                    'tier' => 'tier_1',
                    'name' => "Express {$visaType->name}",
                    'description' => "Express processing for {$visaType->name} (within 5 hours)",
                    'conditions' => [],
                    'route_to' => 'gis', // Per spec: E-Visa Express routes to GIS
                    'sla_hours' => 5,
                    'priority' => 15,
                    'price_multiplier' => 1.70,
                    'is_active' => true,
                ]
            );
        }
    }
}
