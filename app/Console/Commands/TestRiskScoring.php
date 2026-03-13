<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\RiskScoringService;
use Illuminate\Console\Command;

class TestRiskScoring extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:risk-scoring {application_id?}';

    /**
     * The console command description.
     */
    protected $description = 'Test the risk scoring system on applications';

    /**
     * Execute the console command.
     */
    public function handle(RiskScoringService $riskScoringService): int
    {
        $applicationId = $this->argument('application_id');
        
        if ($applicationId) {
            // Test specific application
            $application = Application::find($applicationId);
            if (!$application) {
                $this->error("Application {$applicationId} not found");
                return 1;
            }
            
            $this->testSingleApplication($application, $riskScoringService);
        } else {
            // Test all applications
            $applications = Application::limit(5)->get();
            
            if ($applications->isEmpty()) {
                $this->info('No applications found to test');
                return 0;
            }
            
            foreach ($applications as $application) {
                $this->testSingleApplication($application, $riskScoringService);
                $this->line('---');
            }
        }
        
        return 0;
    }
    
    private function testSingleApplication(Application $application, RiskScoringService $riskScoringService): void
    {
        $this->info("Testing Application: {$application->reference_number}");
        
        // Calculate risk score
        $riskData = $riskScoringService->calculateRiskScore($application);
        
        // Display results
        $this->line("Risk Score: {$riskData['risk_score']}");
        $this->line("Risk Level: {$riskData['risk_level']}");
        
        if (!empty($riskData['risk_reasons'])) {
            $this->line("Risk Reasons:");
            foreach ($riskData['risk_reasons'] as $reason) {
                $this->line("  • {$reason}");
            }
        } else {
            $this->line("No risk factors identified");
        }
        
        // Update the application
        $application->update([
            'risk_score' => $riskData['risk_score'],
            'risk_level' => $riskData['risk_level'],
            'risk_reasons' => $riskData['risk_reasons'],
            'risk_last_updated' => $riskData['risk_last_updated'],
        ]);
        
        $this->info("Application updated with risk score");
    }
}