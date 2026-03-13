<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReasonCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReasonCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ReasonCode::query();

        if ($request->has('action_type')) {
            $query->forAction($request->action_type);
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $codes = $query->orderBy('action_type')->orderBy('sort_order')->get();
        return response()->json(['reason_codes' => $codes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:reason_codes,code',
            'action_type' => 'required|in:approve,reject,request_info,escalate,border_admit,border_deny,border_secondary',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $reasonCode = ReasonCode::create($validated);
        return response()->json(['reason_code' => $reasonCode], 201);
    }

    public function show(ReasonCode $reasonCode): JsonResponse
    {
        return response()->json(['reason_code' => $reasonCode]);
    }

    public function update(Request $request, ReasonCode $reasonCode): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'string|unique:reason_codes,code,' . $reasonCode->id,
            'action_type' => 'in:approve,reject,request_info,escalate,border_admit,border_deny,border_secondary',
            'reason' => 'string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $reasonCode->update($validated);
        return response()->json(['reason_code' => $reasonCode]);
    }

    public function destroy(ReasonCode $reasonCode): JsonResponse
    {
        $reasonCode->delete();
        return response()->json(['message' => 'Reason code deleted']);
    }
}
