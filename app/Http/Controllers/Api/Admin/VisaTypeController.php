<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisaType;
use App\Services\VisaTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VisaTypeController extends Controller
{
    public function __construct(
        protected VisaTypeService $visaTypeService
    ) {}

    /**
     * List all visa types (cached).
     */
    public function index(): JsonResponse
    {
        $visaTypes = $this->visaTypeService->getAllVisaTypes();
        return response()->json($visaTypes);
    }

    /**
     * Create a new visa type (busts cache).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:visa_types,name',
            'slug' => 'nullable|string|max:255|unique:visa_types,slug',
            'description' => 'nullable|string|max:1000',
            'base_fee' => 'required|numeric|min:0',
            'max_duration_days' => 'required|integer|min:1|max:365',
            'required_documents' => 'nullable|array',
            'required_documents.*' => 'string',
            'blacklisted_nationalities' => 'nullable|array',
            'blacklisted_nationalities.*' => 'string|max:3',
            'is_active' => 'boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $visaType = $this->visaTypeService->createVisaType($validated);

        return response()->json([
            'message' => 'Visa type created successfully',
            'visa_type' => $visaType,
        ], 201);
    }

    /**
     * Get a single visa type.
     */
    public function show(VisaType $visaType): JsonResponse
    {
        return response()->json($visaType);
    }

    /**
     * Update a visa type (busts cache).
     */
    public function update(Request $request, VisaType $visaType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:visa_types,name,' . $visaType->id,
            'slug' => 'nullable|string|max:255|unique:visa_types,slug,' . $visaType->id,
            'description' => 'nullable|string|max:1000',
            'base_fee' => 'sometimes|numeric|min:0',
            'max_duration_days' => 'sometimes|integer|min:1|max:365',
            'required_documents' => 'nullable|array',
            'required_documents.*' => 'string',
            'blacklisted_nationalities' => 'nullable|array',
            'blacklisted_nationalities.*' => 'string|max:3',
            'is_active' => 'boolean',
        ]);

        $visaType = $this->visaTypeService->updateVisaType($visaType, $validated);

        return response()->json([
            'message' => 'Visa type updated successfully',
            'visa_type' => $visaType,
        ]);
    }

    /**
     * Delete a visa type (busts cache).
     */
    public function destroy(VisaType $visaType): JsonResponse
    {
        // Check if visa type is in use
        if ($visaType->applications()->exists()) {
            return response()->json([
                'message' => 'Cannot delete visa type that has applications',
            ], 422);
        }

        $this->visaTypeService->deleteVisaType($visaType);

        return response()->json([
            'message' => 'Visa type deleted successfully',
        ]);
    }
}
