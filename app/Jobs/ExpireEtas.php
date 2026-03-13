<?php

namespace App\Jobs;

use App\Models\EtaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily job to expire ETAs that have passed their validity period
 * Per specification section 3.11
 */
class ExpireEtas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expiredCount = EtaApplication::where('status', 'issued')
            ->where(function($query) {
                $query->where('expires_at', '<', now())
                      ->orWhere('valid_until', '<', now());
            })
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            Log::info("Expired {$expiredCount} ETA applications");
        }

        // Also update TAID status for expired ETAs
        $expiredEtas = EtaApplication::where('status', 'expired')
            ->whereNotNull('taid')
            ->get();

        foreach ($expiredEtas as $eta) {
            if ($eta->travelAuthorization && $eta->travelAuthorization->status === 'active') {
                $eta->travelAuthorization->markAsExpired();
            }
        }
    }
}
