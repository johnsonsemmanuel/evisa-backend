<?php

namespace App\Jobs;

use App\Models\BoardingAuthorization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup expired Boarding Authorization Codes
 * BACs are valid for 24 hours per specification
 */
class CleanupExpiredBacs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Delete BACs that expired more than 7 days ago (keep for audit trail)
        $deletedCount = BoardingAuthorization::where('expiry_timestamp', '<', now()->subDays(7))
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} expired BAC records");
        }
    }
}
