<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MfaMissionSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            // North America
            ['code' => 'WASH', 'name' => 'Ghana Embassy Washington DC', 'city' => 'Washington DC', 'country_code' => 'US', 'country_name' => 'United States', 'mission_type' => 'embassy', 'can_issue_visa' => true],
            ['code' => 'NY', 'name' => 'Ghana Consulate New York', 'city' => 'New York', 'country_code' => 'US', 'country_name' => 'United States', 'mission_type' => 'consulate', 'can_issue_visa' => true],
            
            // Europe
            ['code' => 'LON', 'name' => 'Ghana High Commission London', 'city' => 'London', 'country_code' => 'GB', 'country_name' => 'United Kingdom', 'mission_type' => 'high_commission', 'can_issue_visa' => true],
            ['code' => 'BER', 'name' => 'Ghana Embassy Berlin', 'city' => 'Berlin', 'country_code' => 'DE', 'country_name' => 'Germany', 'mission_type' => 'embassy', 'can_issue_visa' => true],
            ['code' => 'PAR', 'name' => 'Ghana Embassy Paris', 'city' => 'Paris', 'country_code' => 'FR', 'country_name' => 'France', 'mission_type' => 'embassy', 'can_issue_visa' => true],
            
            // Asia
            ['code' => 'DEL', 'name' => 'Ghana High Commission New Delhi', 'city' => 'New Delhi', 'country_code' => 'IN', 'country_name' => 'India', 'mission_type' => 'high_commission', 'can_issue_visa' => true],
            ['code' => 'BEI', 'name' => 'Ghana Embassy Beijing', 'city' => 'Beijing', 'country_code' => 'CN', 'country_name' => 'China', 'mission_type' => 'embassy', 'can_issue_visa' => true],
            
            // West Africa
            ['code' => 'ABJ', 'name' => 'Ghana Embassy Abidjan', 'city' => 'Abidjan', 'country_code' => 'CI', 'country_name' => 'Côte d\'Ivoire', 'mission_type' => 'embassy', 'can_issue_visa' => true],
            ['code' => 'ABU', 'name' => 'Ghana High Commission Abuja', 'city' => 'Abuja', 'country_code' => 'NG', 'country_name' => 'Nigeria', 'mission_type' => 'high_commission', 'can_issue_visa' => true],
            ['code' => 'LAG', 'name' => 'Ghana Consulate Lagos', 'city' => 'Lagos', 'country_code' => 'NG', 'country_name' => 'Nigeria', 'mission_type' => 'consulate', 'can_issue_visa' => true],
            
            // Middle East
            ['code' => 'DUB', 'name' => 'Ghana Consulate Dubai', 'city' => 'Dubai', 'country_code' => 'AE', 'country_name' => 'United Arab Emirates', 'mission_type' => 'consulate', 'can_issue_visa' => true],
            ['code' => 'RIY', 'name' => 'Ghana Embassy Riyadh', 'city' => 'Riyadh', 'country_code' => 'SA', 'country_name' => 'Saudi Arabia', 'mission_type' => 'embassy', 'can_issue_visa' => true],
            
            // Other key locations
            ['code' => 'OTT', 'name' => 'Ghana High Commission Ottawa', 'city' => 'Ottawa', 'country_code' => 'CA', 'country_name' => 'Canada', 'mission_type' => 'high_commission', 'can_issue_visa' => true],
            ['code' => 'CAN', 'name' => 'Ghana High Commission Canberra', 'city' => 'Canberra', 'country_code' => 'AU', 'country_name' => 'Australia', 'mission_type' => 'high_commission', 'can_issue_visa' => true],
            ['code' => 'PRE', 'name' => 'Ghana High Commission Pretoria', 'city' => 'Pretoria', 'country_code' => 'ZA', 'country_name' => 'South Africa', 'mission_type' => 'high_commission', 'can_issue_visa' => true],
        ];

        foreach ($missions as $mission) {
            DB::table('mfa_missions')->insert(array_merge($mission, [
                'address' => $mission['city'] . ', ' . $mission['country_name'],
                'phone' => '+1-555-' . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $mission['name'])) . '@mfa.gov.gh',
                'timezone' => $this->getTimezone($mission['country_code']),
                'default_sla_hours' => $this->getDefaultSla($mission['country_code']),
                'requires_interview' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function getTimezone(string $countryCode): string
    {
        $timezones = [
            'US' => 'America/New_York',
            'GB' => 'Europe/London',
            'DE' => 'Europe/Berlin',
            'FR' => 'Europe/Paris',
            'IN' => 'Asia/Kolkata',
            'CN' => 'Asia/Shanghai',
            'CI' => 'Africa/Abidjan',
            'NG' => 'Africa/Lagos',
            'AE' => 'Asia/Dubai',
            'SA' => 'Asia/Riyadh',
            'CA' => 'America/Toronto',
            'AU' => 'Australia/Canberra',
            'ZA' => 'Africa/Johannesburg',
        ];

        return $timezones[$countryCode] ?? 'UTC';
    }

    private function getDefaultSla(string $countryCode): int
    {
        // Different SLAs based on region
        $slaByRegion = [
            'US' => 72, // 3-5 days
            'GB' => 72,
            'DE' => 72,
            'FR' => 72,
            'IN' => 168, // 5-7 days
            'NG' => 168, // 5-7 days
            'default' => 120, // 5 days
        ];

        return $slaByRegion[$countryCode] ?? $slaByRegion['default'];
    }
}
