<?php

namespace App\Console\Commands;

use App\Models\ReasonCode;
use App\Models\ServiceTier;
use App\Models\VisaFee;
use App\Models\VisaType;
use App\Services\CacheService;
use App\Services\VisaTypeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmCaches extends Command
{
    protected $signature = 'cache:warm
                          {--force : Force cache warming even if caches exist}';

    protected $description = 'Pre-load reference data into Redis cache';

    public function handle(): int
    {
        $this->info('Warming caches...');
        $startTime = microtime(true);

        try {
            // Warm visa types cache
            $this->warmVisaTypes();

            // Warm visa fees cache
            $this->warmVisaFees();

            // Warm reason codes cache
            $this->warmReasonCodes();

            // Warm service tiers cache
            $this->warmServiceTiers();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("✅ Cache warming completed in {$duration}ms");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Cache warming failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    protected function warmVisaTypes(): void
    {
        $this->info('Warming visa types cache...');

        $visaTypeService = app(VisaTypeService::class);

        // Warm active visa types
        $activeTypes = $visaTypeService->getActiveVisaTypes();
        $this->line("  - Cached {$activeTypes->count()} active visa types");

        // Warm all visa types
        $allTypes = $visaTypeService->getAllVisaTypes();
        $this->line("  - Cached {$allTypes->count()} total visa types");

        // Warm individual visa types
        foreach ($allTypes as $type) {
            $visaTypeService->getVisaType($type->id);
        }
        $this->line("  - Cached {$allTypes->count()} individual visa type records");
    }

    protected function warmVisaFees(): void
    {
        $this->info('Warming visa fees cache...');

        $fees = VisaFee::current()->get();
        $this->line("  - Found {$fees->count()} active visa fees");

        // Group fees by visa type for efficient caching
        $feesByType = $fees->groupBy('visa_type_id');
        $this->line("  - Fees cover {$feesByType->count()} visa types");

        // Note: Individual fee lookups will be cached on first access
        // We don't pre-cache all combinations as that would be too many keys
    }

    protected function warmReasonCodes(): void
    {
        $this->info('Warming reason codes cache...');

        // Cache all reason codes
        $cacheKey = CacheService::reasonCodesKey();
        $allCodes = CacheService::remember(
            $cacheKey,
            CacheService::REFERENCE_DATA_TTL,
            fn() => ReasonCode::active()->orderBy('action_type')->orderBy('sort_order')->get(),
            [CacheService::TAG_REFERENCE_DATA]
        );
        $this->line("  - Cached {$allCodes->count()} reason codes");

        // Cache by action type
        $actionTypes = ['denial', 'additional_info', 'escalation'];
        foreach ($actionTypes as $actionType) {
            $cacheKey = CacheService::reasonCodesKey($actionType);
            $codes = CacheService::remember(
                $cacheKey,
                CacheService::REFERENCE_DATA_TTL,
                fn() => ReasonCode::active()->forAction($actionType)->orderBy('sort_order')->get(),
                [CacheService::TAG_REFERENCE_DATA]
            );
            $this->line("  - Cached {$codes->count()} {$actionType} reason codes");
        }
    }

    protected function warmServiceTiers(): void
    {
        $this->info('Warming service tiers cache...');

        $cacheKey = CacheService::serviceTiersKey();
        $tiers = CacheService::remember(
            $cacheKey,
            CacheService::REFERENCE_DATA_TTL,
            fn() => ServiceTier::ordered()->get(),
            [CacheService::TAG_REFERENCE_DATA]
        );
        $this->line("  - Cached {$tiers->count()} service tiers");
    }
}
