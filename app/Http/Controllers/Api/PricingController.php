<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Calculate pricing preview for given parameters.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function calculatePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visa_channel' => 'required|string|in:e-visa,regular,on-arrival',
            'entry_type' => 'required|string|in:single,multiple',
            'service_tier_code' => 'required|string|in:standard,priority,express',
        ]);

        $pricing = $this->pricingService->getPricingPreview($validated);

        return response()->json([
            'success' => true,
            'pricing' => $pricing,
        ]);
    }

    /**
     * Get available service tiers with pricing information.
     * 
     * @return JsonResponse
     */
    public function getServiceTiers(): JsonResponse
    {
        $tiers = $this->pricingService->getServiceTiers();

        return response()->json([
            'success' => true,
            'service_tiers' => $tiers,
        ]);
    }

    /**
     * Get example prices for documentation.
     * 
     * @return JsonResponse
     */
    public function getExamples(): JsonResponse
    {
        $examples = $this->pricingService->getExamplePrices();

        return response()->json([
            'success' => true,
            'examples' => $examples,
        ]);
    }
}
