<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Jobs\SendApplicationExpiredEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingApplications extends Command
{
    protected $signature = 'applications:expire-pending
                            {--hours=72 : Consider applications older than this many hours as expired}
                            {--chunk=100 : Process in chunks of this size}';

    protected $description = 'Expire applications with status pending_payment older than threshold with no active payment';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $chunkSize = (int) $this->option('chunk');
        $cutoff = now()->subHours($hours);

        $query = \App\Models\Application::withoutGlobalScopes()
            ->where('status', ApplicationStatus::PendingPayment)
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('payment', fn ($q) => $q->where('status', 'paid'))
            ->orderBy('id');

        $total = 0;

        $query->chunkById($chunkSize, function ($applications) use (&$total) {
            foreach ($applications as $application) {
                $application->update(['status' => ApplicationStatus::Expired]);
                SendApplicationExpiredEmail::dispatch($application)->onQueue('default');
                $total++;
            }
        });

        if ($total > 0) {
            Log::info("Expired {$total} applications (pending_payment older than {$hours}h, no paid payment)");
            $this->info("Expired {$total} applications.");
        } else {
            $this->info('No applications to expire.');
        }

        return self::SUCCESS;
    }
}
