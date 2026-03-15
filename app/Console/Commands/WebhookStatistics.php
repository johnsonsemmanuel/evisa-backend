<?php

namespace App\Console\Commands;

use App\Services\WebhookProcessingService;
use Illuminate\Console\Command;

class WebhookStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:stats 
                            {--gateway= : Filter by gateway (gcb or paystack)}
                            {--hours=24 : Number of hours to look back}
                            {--cleanup : Clean up old processed events}
                            {--days=90 : Days to keep when cleaning up}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display webhook processing statistics and optionally cleanup old events';

    /**
     * Execute the console command.
     */
    public function handle(WebhookProcessingService $service): int
    {
        if ($this->option('cleanup')) {
            return $this->handleCleanup($service);
        }

        return $this->displayStatistics($service);
    }

    /**
     * Display webhook statistics.
     */
    protected function displayStatistics(WebhookProcessingService $service): int
    {
        $gateway = $this->option('gateway');
        $hours = (int) $this->option('hours');

        $this->info("Webhook Processing Statistics");
        $this->line(str_repeat('=', 50));
        $this->newLine();

        // Get statistics for all gateways or specific gateway
        $gateways = $gateway ? [$gateway] : ['gcb', 'paystack', null];

        foreach ($gateways as $gw) {
            $stats = $service->getStatistics($gw, $hours);

            $label = $gw ? strtoupper($gw) : 'ALL GATEWAYS';
            $this->line("<fg=cyan>{$label}</>");
            $this->line(str_repeat('-', 50));

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Events', $stats['total']],
                    ['Processed', $stats['processed']],
                    ['Unprocessed', $stats['unprocessed']],
                    ['Processing Rate', $stats['processing_rate'] . '%'],
                    ['Period', $stats['period_hours'] . ' hours'],
                ]
            );

            // Warning if processing rate is low
            if ($stats['processing_rate'] < 95 && $stats['total'] > 0) {
                $this->warn("⚠️  Low processing rate detected!");
            }

            // Warning if many unprocessed
            if ($stats['unprocessed'] > 10) {
                $this->warn("⚠️  High number of unprocessed webhooks!");
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }

    /**
     * Handle cleanup of old events.
     */
    protected function handleCleanup(WebhookProcessingService $service): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up webhook events older than {$days} days...");

        if (!$this->confirm('This will permanently delete old processed webhook events. Continue?', true)) {
            $this->info('Cleanup cancelled.');
            return Command::SUCCESS;
        }

        $deleted = $service->cleanupOldEvents($days);

        $this->info("✓ Deleted {$deleted} old webhook events.");

        return Command::SUCCESS;
    }
}
