<?php

namespace Database\Seeders;

use App\Models\VisaType;
use Illuminate\Database\Seeder;

class VisaTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding visa types...');

        $visaTypes = [
            [
                'name' => 'Tourism Visa',
                'slug' => 'tourism',
                'description' => 'For tourists visiting Ghana for leisure, sightseeing, or visiting friends and family',
                'base_fee' => 50.00,
                'government_fee' => 40.00,
                'platform_fee' => 10.00,
                'max_duration_days' => 90,
                'entry_type' => 'single',
                'validity_period' => '30-90 days',
                'category' => 'visa',
                'is_active' => true,
                'required_documents' => ['passport_bio', 'photo', 'hotel_booking', 'return_ticket'],
                'required_fields' => [
                    'passport_number', 'passport_expiry', 'nationality', 'date_of_birth',
                    'intended_arrival_date', 'intended_departure_date', 'port_of_entry',
                    'address_in_ghana', 'email', 'phone'
                ],
                'optional_fields' => ['hotel_booking', 'return_ticket'],
                'eligible_nationalities' => null, // All nationalities
                'blacklisted_nationalities' => null,
                'default_processing_days' => 5,
                'default_route_to' => 'gis',
                'sort_order' => 1,
            ],
            [
                'name' => 'Business Visa',
                'slug' => 'business',
                'description' => 'For business travelers attending meetings, conferences, or exploring business opportunities',
                'base_fee' => 100.00,
                'government_fee' => 80.00,
                'platform_fee' => 20.00,
                'max_duration_days' => 365,
                'entry_type' => 'multiple',
                'validity_period' => '90 days - 1 year',
                'category' => 'visa',
                'is_active' => true,
                'required_documents' => ['passport_bio', 'photo', 'invitation_letter', 'company_registration'],
                'required_fields' => [
                    'passport_number', 'passport_expiry', 'nationality', 'date_of_birth',
                    'intended_arrival_date', 'intended_departure_date', 'company_name',
                    'company_address', 'invitation_letter', 'email', 'phone'
                ],
                'optional_fields' => ['business_registration', 'conference_registration'],
                'eligible_nationalities' => null,
                'blacklisted_nationalities' => null,
                'default_processing_days' => 5,
                'default_route_to' => 'gis',
                'sort_order' => 2,
            ],
            [
                'name' => 'Student Visa',
                'slug' => 'student',
                'description' => 'For international students enrolled in Ghanaian educational institutions',
                'base_fee' => 150.00,
                'government_fee' => 120.00,
                'platform_fee' => 30.00,
                'max_duration_days' => 365,
                'entry_type' => 'multiple',
                'validity_period' => '1 year (renewable)',
                'category' => 'visa',
                'is_active' => false, // Inactive for now
                'required_documents' => ['passport_bio', 'photo', 'admission_letter', 'proof_of_funds'],
                'required_fields' => [
                    'passport_number', 'passport_expiry', 'nationality', 'date_of_birth',
                    'institution_name', 'course_of_study', 'admission_letter', 'email', 'phone'
                ],
                'optional_fields' => ['scholarship_letter', 'accommodation_proof'],
                'eligible_nationalities' => null,
                'blacklisted_nationalities' => null,
                'default_processing_days' => 10,
                'default_route_to' => 'mfa',
                'sort_order' => 3,
            ],
            [
                'name' => 'Work Permit',
                'slug' => 'work',
                'description' => 'For foreign nationals employed by Ghanaian companies or organizations',
                'base_fee' => 200.00,
                'government_fee' => 160.00,
                'platform_fee' => 40.00,
                'max_duration_days' => 730,
                'entry_type' => 'multiple',
                'validity_period' => '1-2 years (renewable)',
                'category' => 'visa',
                'is_active' => false, // Inactive for now
                'required_documents' => ['passport_bio', 'photo', 'employment_contract', 'work_permit_approval'],
                'required_fields' => [
                    'passport_number', 'passport_expiry', 'nationality', 'date_of_birth',
                    'employer_name', 'employer_address', 'job_title', 'employment_contract', 'email', 'phone'
                ],
                'optional_fields' => ['qualification_certificates', 'cv'],
                'eligible_nationalities' => null,
                'blacklisted_nationalities' => null,
                'default_processing_days' => 15,
                'default_route_to' => 'mfa',
                'sort_order' => 4,
            ],
            [
                'name' => 'Transit Visa',
                'slug' => 'transit',
                'description' => 'For travelers passing through Ghana to another destination',
                'base_fee' => 30.00,
                'government_fee' => 25.00,
                'platform_fee' => 5.00,
                'max_duration_days' => 7,
                'entry_type' => 'single',
                'validity_period' => '7 days',
                'category' => 'visa',
                'is_active' => false, // Inactive for now
                'required_documents' => ['passport_bio', 'photo', 'onward_ticket'],
                'required_fields' => [
                    'passport_number', 'passport_expiry', 'nationality', 'date_of_birth',
                    'transit_date', 'final_destination', 'onward_ticket', 'email', 'phone'
                ],
                'optional_fields' => [],
                'eligible_nationalities' => null,
                'blacklisted_nationalities' => null,
                'default_processing_days' => 3,
                'default_route_to' => 'gis',
                'sort_order' => 5,
            ],
        ];

        foreach ($visaTypes as $visaType) {
            $created = VisaType::updateOrCreate(
                ['slug' => $visaType['slug']],
                $visaType
            );

            $status = $created->is_active ? '✓ Active' : '○ Inactive';
            $fee = number_format($created->base_fee, 2);
            $this->command->line("  {$status} {$created->name} (\${$fee})");
        }

        $activeCount = VisaType::where('is_active', true)->count();
        $this->command->newLine();
        $this->command->info("✅ Visa types seeded successfully!");
        $this->command->line("   Active: {$activeCount} visa types");
        $this->command->line("   Total: " . count($visaTypes) . " visa types");
    }
}

