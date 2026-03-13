<?php

namespace App\Console\Commands;

use App\Models\EtaApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireEtasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eta:expire
                          {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired ETAs as expired (runs daily per specification)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting ETA expiration check...');
        
        $dryRun = $this->option('dry-run');
        
        // Find all issued ETAs that have passed their expiration date
        $expiredEtas = EtaApplication::where('status', 'issued')
            ->where(function($query) {
                $query->where('expires_at', '<', now())
                      ->orWhere('valid_until', '<', now());
            })
            ->get();
        
        $count = $expiredEtas->count();
        
        if ($count === 0) {
            $this->info('No expired ETAs found.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$count} expired ETA(s)");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->table(
                ['ETA Number', 'Passport', 'Expired Date', 'Current Status'],
                $expiredEtas->map(function($eta) {
                    return [
                        $eta->eta_number,
                        '***' . substr($eta->passport_number_encrypted, -4),
                        $eta->expires_at?->format('Y-m-d') ?? $eta->valid_until?->format('Y-m-d'),
                        $eta->status,
                    ];
                })->toArray()
            );
            return Command::SUCCESS;
        }
        
        // Update expired ETAs
        $updated = 0;
        foreach ($expiredEtas as $eta) {
            try {
                $eta->update(['status' => 'expired']);
                $updated++;
                
                Log::info('ETA expired by scheduled job', [
                    'eta_number' => $eta->eta_number,
                    'reference_number' => $eta->reference_number,
                    'expired_at' => $eta->expires_at ?? $eta->valid_until,
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to expire ETA', [
                    'eta_number' => $eta->eta_number,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to expire ETA {$eta->eta_number}: {$e->getMessage()}");
            }
        }
        
        $this->info("Successfully marked {$updated} ETA(s) as expired");
        
        return Command::SUCCESS;
    }
}
