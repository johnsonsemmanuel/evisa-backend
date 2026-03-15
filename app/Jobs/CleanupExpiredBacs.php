<?php

namespace App\Jobs;

use App\Models\BoardingAuthorization;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredBacs extends BaseJob
{
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $deletedCount = BoardingAuthorization::where('expiry_timestamp', '<', now()->subDays(7))
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} expired BAC records");
        }
    }
}
