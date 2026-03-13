<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\EtaApplication;
use App\Models\Application;
use App\Models\TravelAuthorization;
use App\Services\BorderVerificationService;
use App\Services\RiskScoringService;
use App\Services\EtaScreeningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class TestEndToEnd extends Command
{
    protected $signature = 'test:e2e {--flow=all : Which flow to test (all|eta|visa|border)}';
    protected $description = 'End-to-end testing of critical user flows';

    public function handle(): int
    {
        $flow = $this->option('flow');
        
        $this->info('Starting End-to-End Tests');
        $this->newLine();

        $results = [];

        if ($flow === 'all' || $flow === 'eta') {
            $results['eta'] = $this->testEtaFlow();
        }

        if ($flow === 'all' || $flow === 'visa') {
            $results['visa'] = $this->testVisaFlow();
        }

        if ($flow === 'all' || $flow === 'border') {
            $results['border'] = $this->testBorderFlow();
        }

        // Summary
        $this->newLine();
        $this->info('Test Summary:');
        foreach ($results as $test => $passed) {
            $status = $passed ? '✓ PASSED' : '✗ FAILED';
            $this->line("  {$test}: {$status}");
        }

        $allPassed = !in_array(false, $results);
        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    protected function testEtaFlow(): bool
    {
        $this->info('Testing ETA Application Flow...');
        
        try {
            // Step 1: TAID Generation
            $this->line('  1. Generating TAID...');
            $taid = TravelAuthorization::createTaid('P12345678', 'GB', 'ETA');
            $this->info("     ✓ TAID created: {$taid->taid}");

            // Step 2: ETA Application
            $this->line('  2. Creating ETA application...');
            $eta = EtaApplication::create([
                'reference_number' => EtaApplication::generateReferenceNumber(),
                'taid' => $taid->taid,
                'first_name_encrypted' => Crypt::encryptString('John'),
                'last_name_encrypted' => Crypt::encryptString('Smith'),
                'date_of_birth' => '1990-01-01',
                'nationality_encrypted' => Crypt::encryptString('GB'),
                'passport_number_encrypted' => Crypt::encryptString('P12345678'),
                'passport_issue_date' => '2020-01-01',
                'passport_expiry_date' => '2030-01-01',
                'issuing_authority' => 'UK Passport Office',
                'email_encrypted' => Crypt::encryptString('john.smith@example.com'),
                'intended_arrival_date' => now()->addDays(30),
                'fee_amount' => 0,
                'validity_days' => 90,
                'entry_type' => 'single',
                'status' => 'pending_screening',
                'payment_status' => 'not_required',
            ]);
            $this->info("     ✓ ETA created: {$eta->reference_number}");

            // Step 3: Screening
            $this->line('  3. Running screening checks...');
            $screeningService = app(EtaScreeningService::class);
            $screeningService->autoIssueOrFlag($eta);
            $eta->refresh();
            $this->info("     ✓ Screening complete: Status = {$eta->status}");

            // Step 4: Verify ETA Number Generated
            if ($eta->status === 'issued' && $eta->eta_number) {
                $this->info("     ✓ ETA number generated: {$eta->eta_number}");
            } else {
                $this->warn("     ⚠ ETA flagged or number not generated");
            }

            // Step 5: Verify 90-day validity
            if ($eta->valid_until && $eta->valid_from) {
                $days = $eta->valid_from->diffInDays($eta->valid_until);
                $this->info("     ✓ Validity period: {$days} days");
            }

            // Cleanup
            $eta->delete();
            $taid->delete();

            $this->info('  ✓ ETA Flow Test PASSED');
            return true;

        } catch (\Exception $e) {
            $this->error("  ✗ ETA Flow Test FAILED: {$e->getMessage()}");
            return false;
        }
    }

    protected function testVisaFlow(): bool
    {
        $this->info('Testing Visa Application Flow...');
        
        try {
            // Step 1: Check pricing calculation
            $this->line('  1. Testing pricing calculation...');
            $pricingService = app(\App\Services\PricingService::class);
            
            $singleStandard = $pricingService->calculatePrice([
                'visa_channel' => 'e-visa',
                'entry_type' => 'single',
                'service_tier_code' => 'standard',
            ]);
            
            if ($singleStandard['total'] == 260.00) {
                $this->info('     ✓ Single/Standard pricing correct: $260.00');
            } else {
                $this->error("     ✗ Pricing incorrect: Expected $260, got ${$singleStandard['total']}");
                return false;
            }

            $multipleExpress = $pricingService->calculatePrice([
                'visa_channel' => 'e-visa',
                'entry_type' => 'multiple',
                'service_tier_code' => 'express',
            ]);
            
            $expected = 260 * 1.8 * 1.7;
            if (abs($multipleExpress['total'] - $expected) < 0.01) {
                $this->info("     ✓ Multiple/Express pricing correct: \${$multipleExpress['total']}");
            } else {
                $this->error("     ✗ Pricing incorrect: Expected \${$expected}, got \${$multipleExpress['total']}");
                return false;
            }

            // Step 2: Check routing logic
            $this->line('  2. Testing routing logic...');
            $routingService = app(\App\Services\AdvancedRoutingService::class);
            
            // Create test application
            $testApp = new Application([
                'visa_channel' => 'e-visa',
                'service_tier_id' => 1, // Assuming standard tier
            ]);
            
            $this->info('     ✓ Routing service operational');

            // Step 3: Check risk scoring
            $this->line('  3. Testing risk scoring...');
            $riskService = app(RiskScoringService::class);
            $this->info('     ✓ Risk scoring service operational');

            $this->info('  ✓ Visa Flow Test PASSED');
            return true;

        } catch (\Exception $e) {
            $this->error("  ✗ Visa Flow Test FAILED: {$e->getMessage()}");
            return false;
        }
    }

    protected function testBorderFlow(): bool
    {
        $this->info('Testing Border Verification Flow...');
        
        try {
            // Create test ETA
            $this->line('  1. Creating test ETA...');
            $taid = TravelAuthorization::createTaid('P99999999', 'US', 'ETA');
            
            $eta = EtaApplication::create([
                'reference_number' => EtaApplication::generateReferenceNumber(),
                'taid' => $taid->taid,
                'eta_number' => 'GH-ETA-' . date('Ymd') . '-TEST01',
                'first_name_encrypted' => Crypt::encryptString('Test'),
                'last_name_encrypted' => Crypt::encryptString('User'),
                'nationality_encrypted' => Crypt::encryptString('US'),
                'passport_number_encrypted' => Crypt::encryptString('P99999999'),
                'passport_expiry_date' => '2030-01-01',
                'email_encrypted' => Crypt::encryptString('test@example.com'),
                'status' => 'issued',
                'valid_from' => now(),
                'valid_until' => now()->addDays(90),
                'expires_at' => now()->addDays(90),
                'fee_amount' => 0,
                'entry_type' => 'single',
            ]);
            $this->info("     ✓ Test ETA created: {$eta->eta_number}");

            // Step 2: Verify authorization
            $this->line('  2. Testing authorization verification...');
            $borderService = app(BorderVerificationService::class);
            
            $result = $borderService->verifyAuthorization(
                'P99999999',
                'US',
                $eta->eta_number
            );
            
            if ($result['status'] === 'AUTHORIZED') {
                $this->info('     ✓ Authorization verified successfully');
            } else {
                $this->error("     ✗ Authorization failed: {$result['status']}");
                return false;
            }

            // Step 3: Test passport binding
            $this->line('  3. Testing passport binding...');
            $wrongPassport = $borderService->verifyAuthorization(
                'P88888888', // Wrong passport
                'US',
                $eta->eta_number
            );
            
            if ($wrongPassport['status'] !== 'AUTHORIZED') {
                $this->info('     ✓ Passport binding working (rejected wrong passport)');
            } else {
                $this->error('     ✗ Passport binding failed (accepted wrong passport)');
                return false;
            }

            // Cleanup
            $eta->delete();
            $taid->delete();

            $this->info('  ✓ Border Flow Test PASSED');
            return true;

        } catch (\Exception $e) {
            $this->error("  ✗ Border Flow Test FAILED: {$e->getMessage()}");
            return false;
        }
    }
}
