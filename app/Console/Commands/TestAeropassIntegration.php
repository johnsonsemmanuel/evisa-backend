<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\AeropassService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAeropassIntegration extends Command
{
    protected $signature = 'test:aeropass {--application-id= : Test with specific application ID}';
    protected $description = 'Test Aeropass API integration';

    public function handle(): int
    {
        $this->info('🔍 Testing Aeropass Integration');
        $this->newLine();

        // Test configuration
        $this->testConfiguration();

        // Test Interpol check submission
        if ($applicationId = $this->option('application-id')) {
            $this->testInterpolCheck($applicationId);
        } else {
            $this->info('💡 Use --application-id=X to test Interpol check with a specific application');
        }

        // Test E-Visa check endpoint
        $this->testEVisaCheckEndpoint();

        // Test callback endpoint
        $this->testCallbackEndpoint();

        $this->newLine();
        $this->info('✅ Aeropass integration test completed');

        return 0;
    }

    private function testConfiguration(): void
    {
        $this->info('📋 Configuration Check:');
        
        $config = config('services.aeropass');
        
        $this->line("  • Enabled: " . ($config['enabled'] ? '✅ Yes' : '❌ No'));
        $this->line("  • Base URL: " . ($config['base_url'] ?? '❌ Not set'));
        $this->line("  • Username: " . ($config['username'] ? '✅ Set' : '❌ Not set'));
        $this->line("  • Password: " . ($config['password'] ? '✅ Set' : '❌ Not set'));
        $this->line("  • API Username: " . ($config['api_username'] ? '✅ Set' : '❌ Not set'));
        $this->line("  • API Password: " . ($config['api_password'] ? '✅ Set' : '❌ Not set'));
        $this->line("  • Timeout: {$config['timeout']}s");
        $this->line("  • Max Retries: {$config['max_retries']}");
        
        $this->newLine();
    }

    private function testInterpolCheck(int $applicationId): void
    {
        $this->info('🔍 Testing Interpol Check Submission:');
        
        $application = Application::find($applicationId);
        
        if (!$application) {
            $this->error("  ❌ Application {$applicationId} not found");
            return;
        }

        $this->line("  • Application: {$application->reference_number}");
        $this->line("  • Applicant: {$application->first_name} {$application->last_name}");
        $this->line("  • Status: {$application->status}");

        if (!config('services.aeropass.enabled')) {
            $this->warn("  ⚠️ Aeropass is disabled - enable in config to test");
            return;
        }

        try {
            $aeropassService = app(AeropassService::class);
            $interpolCheck = $aeropassService->submitInterpolCheck($application);
            
            $this->line("  • Interpol Check ID: {$interpolCheck->id}");
            $this->line("  • Reference ID: {$interpolCheck->unique_reference_id}");
            $this->line("  • Status: {$interpolCheck->status}");
            
            if ($interpolCheck->last_error) {
                $this->warn("  ⚠️ Error: {$interpolCheck->last_error}");
            } else {
                $this->info("  ✅ Interpol check submitted successfully");
            }
            
        } catch (\Exception $e) {
            $this->error("  ❌ Error: " . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function testEVisaCheckEndpoint(): void
    {
        $this->info('🔍 Testing E-Visa Check Endpoint:');
        
        // Find a test application
        $application = Application::where('status', 'issued')
            ->whereNotNull('passport_number')
            ->first();

        if (!$application) {
            $this->warn("  ⚠️ No issued applications found for testing");
            return;
        }

        $testData = [
            'uniqueReferenceId' => 'TEST-' . time(),
            'firstName' => $application->first_name,
            'surname' => $application->last_name,
            'dateOfBirth' => $application->date_of_birth ? $application->date_of_birth->format('Y-m-d') : '1990-01-01',
            'nationality' => $application->nationality ?? 'USA',
            'travelDocNumber' => $application->passport_number ?? 'TEST123456',
        ];

        $this->line("  • Testing with application: {$application->reference_number}");
        $this->line("  • Request data: " . json_encode($testData, JSON_PRETTY_PRINT));

        try {
            $credentials = base64_encode(config('services.aeropass.api_username') . ':' . config('services.aeropass.api_password'));
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json',
            ])->post('http://127.0.0.1:8001/api/aeropass/visa-check', $testData);

            $this->line("  • Response Status: {$response->status()}");
            $this->line("  • Response Body: " . $response->body());

            if ($response->successful()) {
                $this->info("  ✅ E-Visa check endpoint working");
            } else {
                $this->warn("  ⚠️ E-Visa check returned error");
            }

        } catch (\Exception $e) {
            $this->error("  ❌ Error: " . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function testCallbackEndpoint(): void
    {
        $this->info('🔍 Testing Interpol Callback Endpoint:');
        
        $testData = [
            'uniqueReferenceId' => 'TEST-CALLBACK-' . time(),
            'firstName' => 'JOHN',
            'surname' => 'DOE',
            'dateOfBirth' => '20/09/1990',
            'interpolNominalMatched' => 'No',
        ];

        $this->line("  • Test callback data: " . json_encode($testData, JSON_PRETTY_PRINT));

        try {
            $credentials = base64_encode(config('services.aeropass.api_username') . ':' . config('services.aeropass.api_password'));
            
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json',
            ])->post('http://127.0.0.1:8001/api/aeropass/interpol-nominal-verification/callback', $testData);

            $this->line("  • Response Status: {$response->status()}");
            $this->line("  • Response Body: " . $response->body());

            if ($response->status() === 400) {
                $this->info("  ✅ Callback endpoint working (400 expected for non-existent reference)");
            } else {
                $this->warn("  ⚠️ Unexpected response");
            }

        } catch (\Exception $e) {
            $this->error("  ❌ Error: " . $e->getMessage());
        }
        
        $this->newLine();
    }
}