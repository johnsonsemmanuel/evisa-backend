<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run basic health checks for database and queues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $status = [
            'database' => false,
            'queue' => false,
        ];

        try {
            DB::connection()->getPdo();
            $status['database'] = true;
        } catch (\Throwable $e) {
            Log::error('Health check database failure', ['error' => $e->getMessage()]);
        }

        try {
            $pendingJobs = DB::table('jobs')->count();
            $status['queue'] = $pendingJobs < 1000;
        } catch (\Throwable $e) {
            // If jobs table is missing, treat queue as unhealthy
            Log::warning('Health check queue status unknown', ['error' => $e->getMessage()]);
        }

        $this->info(json_encode($status));

        return ($status['database'] && $status['queue']) ? self::SUCCESS : self::FAILURE;
    }
}

