<?php

namespace App\Console\Commands;

use App\Notifications\GcbAvailabilityNotification;
use App\Services\GCBPaymentService;
use App\Services\PaymentGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckGCBAvailability extends Command
{
    protected $signature = 'gcb:check-availability
                            {--no-notify : Skip notifying finance officers}';

    protected $description = 'Ping GCB gateway, update gcb:available cache, and notify finance on state change (SLA reporting).';

    private const CACHE_KEY = 'gcb:available';
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function handle(GCBPaymentService $gcb, PaymentGatewayService $gatewayService): int
    {
        $timestamp = now()->utc()->format('Y-m-d H:i:s \U\T\C');
        $wasAvailable = Cache::get(self::CACHE_KEY);
        $isAvailable = $this->pingGCB($gcb);

        $gatewayService->setGCBAvailable($isAvailable);
        Cache::put(self::CACHE_KEY, $isAvailable, self::CACHE_TTL_SECONDS);

        $this->logAvailabilityChange($wasAvailable, $isAvailable, $timestamp);

        if (!$this->option('no-notify')) {
            $this->notifyFinanceIfChanged($wasAvailable, $isAvailable, $timestamp);
        }

        $this->line($isAvailable ? 'GCB: available' : 'GCB: unavailable');
        return $isAvailable ? self::SUCCESS : self::FAILURE;
    }

    private function pingGCB(GCBPaymentService $gcb): bool
    {
        try {
            return $gcb->ping();
        } catch (\Throwable $e) {
            Log::warning('CheckGCBAvailability: GCB ping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function logAvailabilityChange(?bool $wasAvailable, bool $isAvailable, string $timestamp): void
    {
        if ($wasAvailable === null) {
            Log::info('GCB availability check (initial)', [
                'available' => $isAvailable,
                'timestamp' => $timestamp,
            ]);
            return;
        }

        if ($wasAvailable === $isAvailable) {
            return;
        }

        Log::info('GCB availability state changed', [
            'previous' => $wasAvailable ? 'available' : 'unavailable',
            'current' => $isAvailable ? 'available' : 'unavailable',
            'timestamp' => $timestamp,
        ]);
    }

    private function notifyFinanceIfChanged(?bool $wasAvailable, bool $isAvailable, string $timestamp): void
    {
        if ($wasAvailable === $isAvailable) {
            return;
        }

        $users = \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'finance_officer'))
            ->get();

        if ($users->isEmpty()) {
            $email = config('mail.finance_alert');
            if ($email) {
                Notification::route('mail', $email)
                    ->notify(new GcbAvailabilityNotification($isAvailable, $timestamp));
            }
            return;
        }

        Notification::send($users, new GcbAvailabilityNotification($isAvailable, $timestamp));
    }
}
