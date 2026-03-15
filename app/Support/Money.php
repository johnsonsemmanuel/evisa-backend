<?php

namespace App\Support;

/**
 * Money helper for government eVisa platform.
 *
 * CRITICAL: All monetary values are stored as UNSIGNED BIGINT in minor currency units
 * (pesewas for GHS, cents for USD, etc.) to avoid floating-point precision errors.
 *
 * GHS 75.00 = 7500 pesewas stored as integer 7500.
 */
final class Money
{
    /**
     * Format pesewas/cents as display string.
     */
    public static function format(int $pesewas, string $currency = 'GHS'): string
    {
        $symbol = match (strtoupper($currency)) {
            'GHS' => 'GHS ',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency . ' ',
        };

        return $symbol . number_format($pesewas / 100, 2);
    }

    /**
     * Convert a currency amount (float) to pesewas/cents (int).
     * Use for user input or external values before storing.
     */
    public static function toPesewas(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert pesewas/cents to currency units (float).
     * Use only for display or external APIs that expect decimal.
     */
    public static function toCurrency(int $pesewas): float
    {
        return round($pesewas / 100, 2);
    }

    /**
     * Validate that a value is a non-negative integer (pesewas).
     */
    public static function isValidPesewas(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }
}
