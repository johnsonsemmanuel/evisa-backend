<?php

namespace App\Jobs;

use App\Models\EtaApplication;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireEtas extends BaseJob
{
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $expiredCount = EtaApplication::where('status', 'issued')
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhere('valid_until', '<', now());
            })
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            Log::info("Expired {$expiredCount} ETA applications");
        }

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
