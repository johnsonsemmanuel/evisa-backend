<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TierRule;
use App\Models\VisaType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TierConfigController extends Controller
{
    /**
     * List all tier rules grouped by visa type.
     */
    public function index(): JsonResponse
    {
        $rules = TierRule::with('visaType:id,name,slug')
            ->orderBy('visa_type_id')
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json(['tier_rules' => $rules]);
    }

    /**
     * Create a new tier rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visa_type_id' => 'required|exists:visa_types,id',
            'tier'         => 'required|in:tier_1,tier_2',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'conditions'   => 'required|array',
            'route_to'     => 'required|in:gis,mfa',
            'sla_hours'    => 'required|integer|min:1|max:720',
            'priority'     => 'nullable|integer|min:0',
            'is_active'    => 'nullable|boolean',
        ]);

        $rule = TierRule::create($validated);

        return response()->json([
            'message'   => __('admin.tier_rule_created'),
            'tier_rule' => $rule->load('visaType'),
        ], 201);
    }

    /**
     * Update an existing tier rule.
     */
    public function update(Request $request, TierRule $tierRule): JsonResponse
    {
        $validated = $request->validate([
            'tier'        => 'sometimes|in:tier_1,tier_2',
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions'  => 'sometimes|array',
            'route_to'    => 'sometimes|in:gis,mfa',
            'sla_hours'   => 'sometimes|integer|min:1|max:720',
            'priority'    => 'sometimes|integer|min:0',
            'is_active'   => 'sometimes|boolean',
        ]);

        $tierRule->update($validated);

        return response()->json([
            'message'   => __('admin.tier_rule_updated'),
            'tier_rule' => $tierRule->fresh()->load('visaType'),
        ]);
    }

    /**
     * Delete a tier rule.
     */
    public function destroy(TierRule $tierRule): JsonResponse
    {
        $tierRule->delete();

        return response()->json([
            'message' => __('admin.tier_rule_deleted'),
        ]);
    }
}
