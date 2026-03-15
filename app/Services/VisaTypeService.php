<?php

namespace App\Services;

use App\Models\VisaType;
use Illuminate\Support\Collection;

/**
 * Visa Type Service with caching.
 * 
 * Visa types are reference data that rarely change.
 * Safe to cache for 1 hour.
 */
class VisaTypeService
{
    /**
     * Get all active visa types (cached).
     */
    public function getActiveVisaTypes(): Collection
    {
        $cacheKey = CacheService::visaTypesKey(true);
        
        return CacheService::remember(
            $cacheKey,
            CacheService::REFERENCE_DATA_TTL,
            fn() => VisaType::where('is_active', true)
                ->orderBy('name')
                ->get(),
            [CacheService::TAG_REFERENCE_DATA]
        );
    }

    /**
     * Get all visa types including inactive (cached).
     */
    public function getAllVisaTypes(): Collection
    {
        $cacheKey = CacheService::visaTypesKey(false);
        
        return CacheService::remember(
            $cacheKey,
            CacheService::REFERENCE_DATA_TTL,
            fn() => VisaType::orderBy('name')->get(),
            [CacheService::TAG_REFERENCE_DATA]
        );
    }

    /**
     * Get single visa type by ID (cached).
     */
    public function getVisaType(int $id): ?VisaType
    {
        $cacheKey = CacheService::visaTypeKey($id);
        
        return CacheService::remember(
            $cacheKey,
            CacheService::REFERENCE_DATA_TTL,
            fn() => VisaType::find($id),
            [CacheService::TAG_REFERENCE_DATA]
        );
    }

    /**
     * Update visa type and bust cache.
     */
    public function updateVisaType(VisaType $visaType, array $data): VisaType
    {
        $visaType->update($data);
        
        // Bust all visa type caches
        CacheService::forget(CacheService::visaTypesKey(true));
        CacheService::forget(CacheService::visaTypesKey(false));
        CacheService::forget(CacheService::visaTypeKey($visaType->id));
        
        return $visaType->fresh();
    }

    /**
     * Create visa type and bust cache.
     */
    public function createVisaType(array $data): VisaType
    {
        $visaType = VisaType::create($data);
        
        // Bust list caches
        CacheService::forget(CacheService::visaTypesKey(true));
        CacheService::forget(CacheService::visaTypesKey(false));
        
        return $visaType;
    }

    /**
     * Delete visa type and bust cache.
     */
    public function deleteVisaType(VisaType $visaType): bool
    {
        $id = $visaType->id;
        $deleted = $visaType->delete();
        
        if ($deleted) {
            CacheService::forget(CacheService::visaTypesKey(true));
            CacheService::forget(CacheService::visaTypesKey(false));
            CacheService::forget(CacheService::visaTypeKey($id));
        }
        
        return $deleted;
    }
}
