<?php

namespace App\Console\Commands;

use App\Models\EtaApplication;
use App\Services\EtaEligibilityService;
use App\Services\PassportVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWeek5Features extends Command
{
    protected $signature = 'test:week5-features';
    protected $description = 'Test Week 5 implementation: Eligibility Check, Passport Verification, and ETA Flow';

    public function handle()
    {
        $this->info('🧪 Testing Week 5 Features: Eligibility Check & Passport Verification');
        $this->newLine();

        // Test 1: Eligibility Check System
        $this->testEligibilityCheck();
        $this->newLine();

        // Test 2: Passport Verification Service
        $this->testPassportVerification();
        $this->newLine();

        // Test 3: Issuing Authorities
        $this->testIssuingAuthorities();
        $this->newLine();

        // Test 4: ETA Application Flow with Passport Verification
        $this->testEtaApplicationFlow();
        $this->newLine();

        // Test 5: API Endpoints
        $this->testApiEndpoints();
        $this->newLine();

        $this->info('✅ Week 5 testing completed successfully!');
    }

    protected function testEligibilityCheck()
    {
        $this->info('🔍 Testing Eligibility Check System...');

        $eligibilityService = app(EtaEligibilityService::class);

        // Test different nationality categories
        $testCases = [
            ['nationality' => 'NG', 'expected' => 'eta', 'category' => 'ECOWAS'],
            ['nationality' => 'KE', 'expected' => 'eta', 'category' => 'African Union'],
            ['nationality' => 'BB', 'expected' => 'eta', 'category' => 'Caribbean'],
            ['nationality' => 'US', 'expected' => 'evisa', 'category' => 'Visa Required'],
            ['nationality' => 'GH', 'expected' => 'none', 'category' => 'Ghana Citizen'],
        ];

        foreach ($testCases as $case) {
            $result = $eligibilityService->getAuthorizationType($case['nationality']);
            
            if ($result['authorization'] === $case['expected']) {
                $this->info("  ✅ {$case['nationality']} ({$case['category']}) → {$result['authorization']}");
            } else {
                $this->error("  ❌ {$case['nationality']} expected {$case['expected']}, got {$result['authorization']}");
            }
        }
    }

    protected function testPassportVerification()
    {
        $this->info('🛂 Testing Passport Verification Service...');

        $passportService = app(PassportVerificationService::class);

        // Test 1: Valid passport
        $validPassport = [
            'passport_number' => 'A12345678',
            'nationality' => 'NG',
            'issue_date' => '2020-01-01',
            'expiry_date' => '2030-01-01',
            'issuing_authority' => 'Nigeria Immigration Service',
        ];

        $result = $passportService->verifyPassport($validPassport);
        if ($result['valid']) {
            $this->info("  ✅ Valid passport verification: {$result['status']}");
        } else {
            $this->error("  ❌ Valid passport failed verification");
        }

        // Test 2: Expired passport
        $expiredPassport = [
            'passport_number' => 'B12345678',
            'nationality' => 'NG',
            'issue_date' => '2015-01-01',
            'expiry_date' => '2020-01-01',
            'issuing_authority' => 'Nigeria Immigration Service',
        ];

        $result = $passportService->verifyPassport($expiredPassport);
        if (!$result['valid'] && $result['status'] === 'expired') {
            $this->info("  ✅ Expired passport correctly rejected");
        } else {
            $this->error("  ❌ Expired passport should be rejected");
        }

        // Test 3: Passport expiring soon
        $soonExpiringPassport = [
            'passport_number' => 'C12345678',
            'nationality' => 'NG',
            'issue_date' => '2020-01-01',
            'expiry_date' => now()->addMonths(3)->format('Y-m-d'),
            'issuing_authority' => 'Nigeria Immigration Service',
        ];

        $result = $passportService->verifyPassport($soonExpiringPassport);
        if ($result['valid'] && !empty($result['warnings'])) {
            $this->info("  ✅ Soon-expiring passport warning: " . implode(', ', $result['warnings']));
        } else {
            $this->error("  ❌ Soon-expiring passport should have warnings");
        }
    }

    protected function testIssuingAuthorities()
    {
        $this->info('🏛️ Testing Issuing Authorities...');

        $passportService = app(PassportVerificationService::class);

        $testCountries = ['NG', 'US', 'GB', 'GH', 'FR'];

        foreach ($testCountries as $country) {
            $authorities = $passportService->getIssuingAuthorities($country);
            if (!empty($authorities)) {
                $this->info("  ✅ {$country}: " . count($authorities) . " authorities available");
                $this->line("     - " . implode(', ', array_slice($authorities, 0, 2)));
            } else {
                $this->error("  ❌ {$country}: No authorities found");
            }
        }
    }

    protected function testEtaApplicationFlow()
    {
        $this->info('📝 Testing ETA Application Flow...');

        // Test data for ETA application
        $etaData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'nationality' => 'NG',
            'passport_number' => 'A12345678',
            'passport_issue_date' => '2020-01-01',
            'passport_expiry_date' => '2030-01-01',
            'issuing_authority' => 'Nigeria Immigration Service',
            'date_of_birth' => '1990-01-01',
            'email' => 'john.doe@example.com',
            'intended_arrival_date' => now()->addDays(30)->format('Y-m-d'),
            'denied_entry_before' => false,
            'criminal_conviction' => false,
        ];

        // Check if passport verification would work
        $passportService = app(PassportVerificationService::class);
        $passportData = [
            'passport_number' => $etaData['passport_number'],
            'nationality' => $etaData['nationality'],
            'issue_date' => $etaData['passport_issue_date'],
            'expiry_date' => $etaData['passport_expiry_date'],
            'issuing_authority' => $etaData['issuing_authority'],
        ];
        $verification = $passportService->verifyPassport($passportData);

        if ($verification['valid']) {
            $this->info("  ✅ Passport verification passed for ETA application");
            if (!empty($verification['warnings'])) {
                $this->warn("  ⚠️  Warnings: " . implode(', ', $verification['warnings']));
            }
        } else {
            $this->error("  ❌ Passport verification failed: " . implode(', ', $verification['errors']));
        }

        // Test screening logic
        $screeningService = app(\App\Services\EtaScreeningService::class);
        
        // Create a mock ETA for screening test
        $mockEta = new EtaApplication();
        $mockEta->passport_expiry_date = now()->addYears(5);
        $mockEta->intended_arrival_date = now()->addDays(30);
        $mockEta->criminal_conviction = false;
        $mockEta->denied_entry_before = false;
        $mockEta->passport_verification_status = 'verified_offline';

        $flags = $screeningService->performScreening($mockEta);
        
        if (empty($flags)) {
            $this->info("  ✅ ETA screening passed - would be auto-issued");
        } else {
            $this->warn("  ⚠️  ETA screening flags: " . implode(', ', $flags));
        }
    }

    protected function testApiEndpoints()
    {
        $this->info('🌐 Testing API Endpoints...');

        // Note: These would be actual HTTP tests in a real environment
        // For now, we'll test the controller methods directly

        $controller = app(\App\Http\Controllers\Api\EtaController::class);

        // Test eligibility check
        $request = new \Illuminate\Http\Request(['nationality' => 'NG']);
        try {
            $response = $controller->checkEligibility($request);
            $data = $response->getData(true);
            
            if ($data['authorization_required'] === 'eta') {
                $this->info("  ✅ Eligibility check endpoint working");
            } else {
                $this->error("  ❌ Eligibility check returned unexpected result");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Eligibility check endpoint error: " . $e->getMessage());
        }

        // Test issuing authorities endpoint
        $request = new \Illuminate\Http\Request(['nationality' => 'NG']);
        try {
            $response = $controller->getIssuingAuthorities($request);
            $data = $response->getData(true);
            
            if (!empty($data['issuing_authorities'])) {
                $this->info("  ✅ Issuing authorities endpoint working");
            } else {
                $this->error("  ❌ Issuing authorities endpoint returned no data");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Issuing authorities endpoint error: " . $e->getMessage());
        }
    }
}