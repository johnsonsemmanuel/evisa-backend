<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

trait PaginatesResults
{
    /**
     * Get the per_page value from request with validation.
     */
    protected function getPerPage(Request $request, string $context = 'default', int $defaultOverride = null): int
    {
        $limits = config('pagination.limits.' . $context, [
            'default' => config('pagination.default_per_page', 20),
            'max' => config('pagination.max_per_page', 100),
        ]);

        $default = $defaultOverride ?? $limits['default'];
        $max = $limits['max'];

        return min(
            $request->integer('per_page', $default),
            $max
        );
    }

    /**
     * Get cursor pagination per_page value.
     */
    protected function getCursorPerPage(Request $request): int
    {
        return min(
            $request->integer('per_page', config('pagination.cursor.default_per_page', 25)),
            config('pagination.cursor.max_per_page', 100)
        );
    }

    /**
     * Check if request should use cursor pagination.
     */
    protected function shouldUseCursorPagination(Request $request): bool
    {
        return $request->boolean('cursor', false) || 
               $request->has('cursor') || 
               $request->filled('after');
    }

    /**
     * Validate export request size.
     */
    protected function validateExportSize(int $recordCount): bool
    {
        return $recordCount <= config('pagination.export.max_records', 10000);
    }

    /**
     * Check if export should be queued.
     */
    protected function shouldQueueExport(int $recordCount): bool
    {
        return $recordCount > config('pagination.export.queue_threshold', 1000);
    }
}