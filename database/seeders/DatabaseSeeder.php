<?php

namespace Database\Seeders;

use App\Models\TierRule;
use App\Models\User;
use App\Models\VisaType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ServiceTierSeeder::class,
            MfaMissionSeeder::class,
            RoutingRuleSeeder::class,
        ]);

        // ── Visa Types ────────────────────────────────────
        $tourism = VisaType::create([
            'name'                     => 'Tourism',
            'slug'                     => 'tourism',
            'description'              => 'For sightseeing, visiting friends and family, or cultural exploration.',
            'base_fee'                 => 260.00,
            'max_duration_days'        => 90,
            'is_active'                => true,
            'required_documents'       => ['passport_bio', 'photo'],
            'eligible_nationalities'   => null,
            'blacklisted_nationalities'=> null,
            'default_route_to'         => 'mfa',
        ]);

        $business = VisaType::create([
            'name'                     => 'Business',
            'slug'                     => 'business',
            'description'              => 'For attending conferences, meetings, or exploring business opportunities.',
            'base_fee'                 => 260.00,
            'max_duration_days'        => 90,
            'is_active'                => true,
            'required_documents'       => ['passport_bio', 'photo', 'invitation_letter'],
            'eligible_nationalities'   => null,
            'blacklisted_nationalities'=> null,
            'default_route_to'         => 'mfa',
        ]);

        $student = VisaType::create([
            'name'                     => 'Student',
            'slug'                     => 'student',
            'description'              => 'For educational purposes, study programs, or academic exchanges.',
            'base_fee'                 => 75.00,
            'max_duration_days'        => 365,
            'is_active'                => true,
            'required_documents'       => ['passport_bio', 'photo', 'admission_letter'],
            'eligible_nationalities'   => null,
            'blacklisted_nationalities'=> null,
            'default_route_to'         => 'mfa',
        ]);

        $work = VisaType::create([
            'name'                     => 'Work',
            'slug'                     => 'work',
            'description'              => 'For employment purposes, work contracts, or professional assignments.',
            'base_fee'                 => 200.00,
            'max_duration_days'        => 365,
            'is_active'                => true,
            'required_documents'       => ['passport_bio', 'photo', 'work_permit', 'employment_contract'],
            'eligible_nationalities'   => null,
            'blacklisted_nationalities'=> null,
            'default_route_to'         => 'mfa',
        ]);

        $medical = VisaType::create([
            'name'                     => 'Medical',
            'slug'                     => 'medical',
            'description'              => 'For medical treatment, healthcare services, or medical consultations.',
            'base_fee'                 => 50.00,
            'max_duration_days'        => 90,
            'is_active'                => true,
            'required_documents'       => ['passport_bio', 'photo', 'medical_letter'],
            'eligible_nationalities'   => null,
            'blacklisted_nationalities'=> null,
            'default_route_to'         => 'gis',
        ]);

        $transit = VisaType::create([
            'name'                     => 'Transit',
            'slug'                     => 'transit',
            'description'              => 'For transit through Ghana to another destination.',
            'base_fee'                 => 30.00,
            'max_duration_days'        => 7,
            'is_active'                => true,
            'required_documents'       => ['passport_bio', 'photo', 'onward_ticket'],
            'eligible_nationalities'   => null,
            'blacklisted_nationalities'=> null,
            'default_route_to'         => 'gis',
        ]);

        $diplomatic = VisaType::create([
            'name'                     => 'Diplomatic',
            'slug'                     => 'diplomatic',
            'description'              => 'For diplomatic officials, government representatives, and embassy staff.',
            'base_fee'                 => 0.00,
            'max_duration_days'        => 365,
            'is_active'                => true,
            'required_documents'       => ['passport_bio', 'diplomatic_note', 'government_credentials'],
            'eligible_nationalities'   => null,
            'blacklisted_nationalities'=> null,
            'default_route_to'         => 'mfa',
        ]);

        // ── Tier Rules ────────────────────────────────────

        // ── Tourism Tier Rules ────────────────────────────────
        
        // Tourism — Express (24hr): <= 7 days, route to GIS
        TierRule::create([
            'visa_type_id'     => $tourism->id,
            'tier'             => 'tier_1',
            'processing_tier'  => 'express',
            'name'             => 'Express Tourism',
            'description'      => 'Tourism applications with stay <= 7 days. Expedited processing.',
            'conditions'       => ['duration_lt' => 8],
            'route_to'         => 'gis',
            'sla_hours'        => 24,
            'priority'         => 20,
            'is_active'        => true,
        ]);

        // Tourism — Fast-Track (72hr): 8-30 days, route to GIS
        TierRule::create([
            'visa_type_id'     => $tourism->id,
            'tier'             => 'tier_1',
            'processing_tier'  => 'fast_track',
            'name'             => 'Fast-Track Tourism',
            'description'      => 'Tourism applications with stay 8-30 days.',
            'conditions'       => ['duration_lt' => 31],
            'route_to'         => 'gis',
            'sla_hours'        => 72,
            'priority'         => 10,
            'is_active'        => true,
        ]);

        // Tourism — Regular (120hr): > 30 days, route to MFA
        TierRule::create([
            'visa_type_id'     => $tourism->id,
            'tier'             => 'tier_2',
            'processing_tier'  => 'regular',
            'name'             => 'Regular Tourism (Extended Stay)',
            'description'      => 'Tourism applications with stay > 30 days. MFA review required.',
            'conditions'       => ['duration_gt' => 30],
            'route_to'         => 'mfa',
            'sla_hours'        => 120,
            'priority'         => 5,
            'is_active'        => true,
        ]);

        // ── Business Tier Rules ────────────────────────────────

        // Business — Express (24hr): <= 7 days, route to GIS
        TierRule::create([
            'visa_type_id'     => $business->id,
            'tier'             => 'tier_1',
            'processing_tier'  => 'express',
            'name'             => 'Express Business',
            'description'      => 'Business applications with stay <= 7 days. Expedited processing.',
            'conditions'       => ['duration_lt' => 8],
            'route_to'         => 'gis',
            'sla_hours'        => 24,
            'priority'         => 20,
            'is_active'        => true,
        ]);

        // Business — Fast-Track (72hr): 8-30 days, route to GIS
        TierRule::create([
            'visa_type_id'     => $business->id,
            'tier'             => 'tier_1',
            'processing_tier'  => 'fast_track',
            'name'             => 'Fast-Track Business',
            'description'      => 'Business applications with stay 8-30 days.',
            'conditions'       => ['duration_lt' => 31],
            'route_to'         => 'gis',
            'sla_hours'        => 72,
            'priority'         => 10,
            'is_active'        => true,
        ]);

        // Business — Regular (120hr): > 30 days, route to MFA
        TierRule::create([
            'visa_type_id'     => $business->id,
            'tier'             => 'tier_2',
            'processing_tier'  => 'regular',
            'name'             => 'Regular Business (Extended Stay)',
            'description'      => 'Business applications with stay > 30 days. MFA review required.',
            'conditions'       => ['duration_gt' => 30],
            'route_to'         => 'mfa',
            'sla_hours'        => 120,
            'priority'         => 5,
            'is_active'        => true,
        ]);

        // ── Users ─────────────────────────────────────────

        User::create([
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'email'      => 'admin@ghevisa.gov.gh',
            'password'   => Hash::make('password'),
            'role'       => 'admin',
            'agency'     => 'ADMIN',
            'is_active'  => true,
            'locale'     => 'en',
        ]);

        User::create([
            'first_name' => 'Kwame',
            'last_name'  => 'Mensah',
            'email'      => 'kmensah@gis.gov.gh',
            'password'   => Hash::make('password'),
            'role'       => 'gis_officer',
            'agency'     => 'GIS',
            'is_active'  => true,
            'locale'     => 'en',
        ]);

        User::create([
            'first_name' => 'Ama',
            'last_name'  => 'Adjei',
            'email'      => 'aadjei@mfa.gov.gh',
            'password'   => Hash::make('password'),
            'role'       => 'mfa_reviewer',
            'agency'     => 'MFA',
            'is_active'  => true,
            'locale'     => 'en',
        ]);

        User::create([
            'first_name' => 'Fatima',
            'last_name'  => 'Al-Hassan',
            'email'      => 'fatima@example.com',
            'password'   => Hash::make('password'),
            'role'       => 'applicant',
            'agency'     => null,
            'is_active'  => true,
            'locale'     => 'en',
        ]);

        User::create([
            'first_name' => 'Kofi',
            'last_name'  => 'Asante',
            'email'      => 'gis.approver@gis.gov.gh',
            'password'   => Hash::make('password'),
            'role'       => 'gis_approver',
            'agency'     => 'GIS',
            'is_active'  => true,
            'locale'     => 'en',
        ]);

        User::create([
            'first_name' => 'Akua',
            'last_name'  => 'Boateng',
            'email'      => 'gis.admin@gis.gov.gh',
            'password'   => Hash::make('password'),
            'role'       => 'gis_admin',
            'agency'     => 'GIS',
            'is_active'  => true,
            'locale'     => 'en',
        ]);
    }
}
