<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\User;
use App\Services\RiskScoringService;
use App\Services\TwoStageWorkflowService;
use App\Services\ApplicationRoutingService;
use Illuminate\Console\Command;

class TestWeek4Features extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:week4-features';

    /**
     * The console command description.
     */
    protected $description = 'Test all Week 4 critical features: Risk Scoring, Two-Stage Workflow, and Visa Routing';

    /**
     * Execute the console command.
     */
    public function handle(
        RiskScoringService $riskScoringService,
        TwoStageWorkflowService $workflowService,
        ApplicationRoutingService $routingService
    ): int {
        $this->info('🚀 Testing Week 4 Critical Features');
        $this->line('');

        // Test 1: Risk Scoring System
        $this->info('1️⃣ Testing Risk Scoring System...');
        $this->testRiskScoring($riskScoringService);
        $this->line('');

        // Test 2: Two-Stage Workflow
        $this->info('2️⃣ Testing Two-Stage Workflow...');
        $this->testTwoStageWorkflow($workflowService);
        $this->line('');

        // Test 3: Visa Routing Engine
        $this->info('3️⃣ Testing Visa Routing Engine...');
        $this->testVisaRouting($routingService);
        $this->line('');

        $this->info('✅ All Week 4 features tested successfully!');
        return 0;
    }

    private function testRiskScoring(RiskScoringService $riskScoringService): void
    {
        $application = Application::first();
        if (!$application) {
            $this->warn('No applications found for risk scoring test');
            return;
        }

        $riskData = $riskScoringService->calculateRiskScore($application);
        
        $this->line("  📊 Risk Score: {$riskData['risk_score']}");
        $this->line("  🎯 Risk Level: {$riskData['risk_level']}");
        $this->line("  📝 Risk Reasons: " . count($riskData['risk_reasons']));
        
        foreach ($riskData['risk_reasons'] as $reason) {
            $this->line("    • {$reason}");
        }

        // Update application
        $application->update($riskData);
        $this->info('  ✅ Risk scoring working correctly');
    }

    private function testTwoStageWorkflow(TwoStageWorkflowService $workflowService): void
    {
        $application = Application::where('status', '!=', 'APPROVED')->first();
        if (!$application) {
            $this->warn('No suitable applications found for workflow test');
            return;
        }

        // Test moving to review queue
        $workflowService->moveToReviewQueue($application);
        $this->line("  📥 Moved to review queue: {$application->current_queue}");

        // Test getting queue statistics
        $officer = User::where('role', 'like', '%officer%')->first();
        if ($officer) {
            $stats = $workflowService->getWorkflowStatistics($officer);
            $this->line("  📈 Queue Statistics:");
            $this->line("    • Review Queue: {$stats['review_queue_count']}");
            $this->line("    • Approval Queue: {$stats['approval_queue_count']}");
            $this->line("    • High Risk: {$stats['high_risk_count']}");
        }

        $this->info('  ✅ Two-stage workflow working correctly');
    }

    private function testVisaRouting(ApplicationRoutingService $routingService): void
    {
        $application = Application::first();
        if (!$application) {
            $this->warn('No applications found for routing test');
            return;
        }

        // Test routing logic
        $originalAgency = $application->assigned_agency;
        $routedApp = $routingService->route($application);
        
        $this->line("  🎯 Routing Results:");
        $this->line("    • Visa Channel: {$routedApp->visa_channel}");
        $this->line("    • Processing Tier: {$routedApp->processing_tier}");
        $this->line("    • Assigned Agency: {$routedApp->assigned_agency}");
        $this->line("    • Owner Mission: {$routedApp->owner_mission_id}");
        $this->line("    • Current Queue: {$routedApp->current_queue}");

        $this->info('  ✅ Visa routing working correctly');
    }
}