<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceTierController extends Controller
{
    public function index(): JsonResponse
    {
        $tiers = ServiceTier::ordered()->get();
        return response()->json(['service_tiers' => $tiers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:service_tiers,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'processing_hours' => 'required|integer|min:1',
            'processing_time_display' => 'required|string',
            'fee_multiplier' => 'required|numeric|min:1',
            'additional_fee' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $tier = ServiceTier::create($validated);
        return response()->json(['service_tier' => $tier], 201);
    }

    public function show(ServiceTier $serviceTier): JsonResponse
    {
        return response()->json(['service_tier' => $serviceTier]);
    }

    public function update(Request $request, ServiceTier $serviceTier): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'string|unique:service_tiers,code,' . $serviceTier->id,
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'processing_hours' => 'integer|min:1',
            'processing_time_display' => 'string',
            'fee_multiplier' => 'numeric|min:1',
            'additional_fee' => 'numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $serviceTier->update($validated);
        return response()->json(['service_tier' => $serviceTier]);
    }

    public function destroy(ServiceTier $serviceTier): JsonResponse
    {
        $serviceTier->delete();
        return response()->json(['message' => 'Service tier deleted']);
    }
}
