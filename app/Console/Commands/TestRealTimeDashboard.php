<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\ApplicationService;
use App\Services\RealTimeDashboardService;
use Illuminate\Console\Command;

class TestRealTimeDashboard extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dashboard:test-realtime {--simulate-status-change}';

    /**
     * The console command description.
     */
    protected $description = 'Test real-time dashboard functionality';

    /**
     * Execute the console command.
     */
    public function handle(
        RealTimeDashboardService $dashboardService,
        ApplicationService $applicationService
    ): int {
        $this->info('Testing real-time dashboard functionality...');

        if ($this->option('simulate-status-change')) {
            return $this->simulateStatusChange($applicationService);
        }

        // Test metric calculation and broadcasting
        $this->info('Testing metric calculation...');
        
        try {
            // Test GIS metrics
            $gisMetrics = $dashboardService->calculateGisMetrics();
            $this->info('GIS Metrics: ' . json_encode($gisMetrics, JSON_PRETTY_PRINT));

            // Test MFA metrics
            $mfaMetrics = $dashboardService->calculateMfaMetrics();
            $this->info('MFA Metrics: ' . json_encode($mfaMetrics, JSON_PRETTY_PRINT));

            // Test Admin metrics
            $adminMetrics = $dashboardService->calculateAdminMetrics();
            $this->info('Admin Metrics: ' . json_encode($adminMetrics, JSON_PRETTY_PRINT));

            // Test broadcasting
            $this->info('Testing metric broadcasting...');
            $dashboardService->refreshAllMetrics();
            $this->info('Metrics broadcasted successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Simulate an application status change to test real-time updates.
     */
    protected function simulateStatusChange(ApplicationService $applicationService): int
    {
        $this->info('Simulating application status change...');

        // Find a test application
        $application = Application::where('status', 'submitted')
            ->where('assigned_agency', 'gis')
            ->first();

        if (!$application) {
            $this->error('No suitable test application found. Need a submitted GIS application.');
            return Command::FAILURE;
        }

        $this->info("Using application: {$application->reference_number}");
        $this->info("Current status: {$application->status}");

        try {
            // Change status to trigger real-time update
            $applicationService->changeStatus(
                $application,
                'under_review',
                'Status changed by test command for real-time dashboard testing'
            );

            $this->info("Status changed to: under_review");
            $this->info('Real-time dashboard update should have been broadcasted.');

            // Change back to original status
            sleep(2);
            $applicationService->changeStatus(
                $application,
                'submitted',
                'Status reverted by test command'
            );

            $this->info("Status reverted to: submitted");
            $this->info('Test completed successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Status change test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}