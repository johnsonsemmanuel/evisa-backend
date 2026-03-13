<?php

namespace Database\Seeders;

use App\Models\MfaMission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MissionCountryMappingSeeder extends Seeder
{
    public function run(): void
    {
        // Get all missions
        $missions = MfaMission::all()->keyBy('code');

        // Country to Mission mappings
        // Maps applicant nationality to the mission that handles their applications
        $mappings = [
            // Washington DC handles US and Central America
            'WASH' => ['US', 'MX', 'GT', 'HN', 'SV', 'NI', 'CR', 'PA', 'BZ'],
            
            // New York handles Caribbean
            'NY' => ['JM', 'TT', 'BB', 'BS', 'HT', 'DO', 'CU', 'PR'],
            
            // London handles UK and Ireland
            'LON' => ['GB', 'IE'],
            
            // Berlin handles Germany, Austria, Switzerland, Poland, Netherlands
            'BER' => ['DE', 'AT', 'CH', 'PL', 'NL', 'BE', 'CZ', 'HU'],
            
            // Paris handles France, Spain, Portugal, Italy
            'PAR' => ['FR', 'ES', 'PT', 'IT', 'GR', 'MC', 'LU'],
            
            // New Delhi handles India, Sri Lanka, Nepal, Bangladesh
            'DEL' => ['IN', 'LK', 'NP', 'BD', 'PK', 'AF'],
            
            // Beijing handles China, Japan, South Korea, Southeast Asia
            'BEI' => ['CN', 'JP', 'KR', 'TW', 'HK', 'SG', 'MY', 'TH', 'VN', 'ID', 'PH'],
            
            // Abidjan handles Côte d'Ivoire, Mali, Burkina Faso
            'ABJ' => ['CI', 'ML', 'BF', 'NE', 'SN', 'GN', 'GM', 'SL', 'LR'],
            
            // Abuja handles Nigeria and nearby
            'ABU' => ['NG'],
            
            // Lagos is secondary for Nigeria
            'LAG' => [],
            
            // Dubai handles UAE, Qatar, Kuwait, Bahrain, Oman
            'DUB' => ['AE', 'QA', 'KW', 'BH', 'OM'],
            
            // Riyadh handles Saudi Arabia, Jordan, Lebanon, Egypt
            'RIY' => ['SA', 'JO', 'LB', 'EG', 'IQ', 'YE'],
            
            // Ottawa handles Canada
            'OTT' => ['CA'],
            
            // Canberra handles Australia, New Zealand, Pacific Islands
            'CAN' => ['AU', 'NZ', 'FJ', 'PG'],
            
            // Pretoria handles South Africa and Southern Africa
            'PRE' => ['ZA', 'BW', 'NA', 'ZW', 'ZM', 'MW', 'MZ', 'AO', 'TZ', 'KE', 'UG', 'RW', 'ET'],
        ];

        foreach ($mappings as $missionCode => $countries) {
            $mission = $missions->get($missionCode);
            if (!$mission) {
                continue;
            }

            foreach ($countries as $countryCode) {
                DB::table('mission_country_mappings')->insert([
                    'mfa_mission_id' => $mission->id,
                    'country_code' => $countryCode,
                    'country_name' => $this->getCountryName($countryCode),
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function getCountryName(string $code): string
    {
        $countries = [
            'US' => 'United States',
            'MX' => 'Mexico',
            'GT' => 'Guatemala',
            'HN' => 'Honduras',
            'SV' => 'El Salvador',
            'NI' => 'Nicaragua',
            'CR' => 'Costa Rica',
            'PA' => 'Panama',
            'BZ' => 'Belize',
            'JM' => 'Jamaica',
            'TT' => 'Trinidad and Tobago',
            'BB' => 'Barbados',
            'BS' => 'Bahamas',
            'HT' => 'Haiti',
            'DO' => 'Dominican Republic',
            'CU' => 'Cuba',
            'PR' => 'Puerto Rico',
            'GB' => 'United Kingdom',
            'IE' => 'Ireland',
            'DE' => 'Germany',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'PL' => 'Poland',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'FR' => 'France',
            'ES' => 'Spain',
            'PT' => 'Portugal',
            'IT' => 'Italy',
            'GR' => 'Greece',
            'MC' => 'Monaco',
            'LU' => 'Luxembourg',
            'IN' => 'India',
            'LK' => 'Sri Lanka',
            'NP' => 'Nepal',
            'BD' => 'Bangladesh',
            'PK' => 'Pakistan',
            'AF' => 'Afghanistan',
            'CN' => 'China',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'TW' => 'Taiwan',
            'HK' => 'Hong Kong',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'CI' => 'Côte d\'Ivoire',
            'ML' => 'Mali',
            'BF' => 'Burkina Faso',
            'NE' => 'Niger',
            'SN' => 'Senegal',
            'GN' => 'Guinea',
            'GM' => 'Gambia',
            'SL' => 'Sierra Leone',
            'LR' => 'Liberia',
            'NG' => 'Nigeria',
            'AE' => 'United Arab Emirates',
            'QA' => 'Qatar',
            'KW' => 'Kuwait',
            'BH' => 'Bahrain',
            'OM' => 'Oman',
            'SA' => 'Saudi Arabia',
            'JO' => 'Jordan',
            'LB' => 'Lebanon',
            'EG' => 'Egypt',
            'IQ' => 'Iraq',
            'YE' => 'Yemen',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'FJ' => 'Fiji',
            'PG' => 'Papua New Guinea',
            'ZA' => 'South Africa',
            'BW' => 'Botswana',
            'NA' => 'Namibia',
            'ZW' => 'Zimbabwe',
            'ZM' => 'Zambia',
            'MW' => 'Malawi',
            'MZ' => 'Mozambique',
            'AO' => 'Angola',
            'TZ' => 'Tanzania',
            'KE' => 'Kenya',
            'UG' => 'Uganda',
            'RW' => 'Rwanda',
            'ET' => 'Ethiopia',
        ];

        return $countries[$code] ?? $code;
    }
}
