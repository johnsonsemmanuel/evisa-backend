<?php

namespace App\Console\Commands;

use App\Services\RealTimeDashboardService;
use Illuminate\Console\Command;

class RefreshDashboardMetrics extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dashboard:refresh-metrics';

    /**
     * The console command description.
     */
    protected $description = 'Refresh and broadcast dashboard metrics for all agencies';

    /**
     * Execute the console command.
     */
    public function handle(RealTimeDashboardService $dashboardService): int
    {
        $this->info('Refreshing dashboard metrics...');

        try {
            $dashboardService->refreshAllMetrics();
            $this->info('Dashboard metrics refreshed and broadcasted successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to refresh dashboard metrics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}