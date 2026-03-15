<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-pending
                            {--hours=24 : Number of hours after which pending payments expire}
                            {--dry-run : Show what would be expired without actually expiring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending payments that are older than specified hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        
        $this->info("Checking for pending payments older than {$hours} hours...");
        
        $cutoffTime = now()->subHours($hours);
        
        $query = Payment::where('status', 'pending')
            ->where('created_at', '<', $cutoffTime);
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No pending payments to expire.');
            return self::SUCCESS;
        }
        
        if ($dryRun) {
            $this->warn("DRY RUN: Would expire {$count} pending payment(s)");
            
            $payments = $query->with('application')->get();
            
            $this->table(
                ['ID', 'Reference', 'Application', 'Amount', 'Created'],
                $payments->map(fn($p) => [
                    $p->id,
                    $p->transaction_reference,
                    $p->application->reference_number ?? 'N/A',
                    $p->currency . ' ' . $p->amount,
                    $p->created_at->diffForHumans(),
                ])
            );
            
            return self::SUCCESS;
        }
        
        // Expire the payments - Fixed SQL injection by using parameter bindings instead of string concatenation
        $expired = $query->update([
            'status' => 'expired',
            'provider_response' => \DB::raw("JSON_SET(COALESCE(provider_response, '{}'), '$.expired_at', ?, '$.expired_reason', ?)", [
                now()->toIso8601String(),
                "Automatic expiration after {$hours} hours"
            ]),
        ]);
        
        $this->info("Successfully expired {$expired} pending payment(s).");
        
        Log::info("Expired {$expired} pending payments older than {$hours} hours");
        
        return self::SUCCESS;
    }
}
