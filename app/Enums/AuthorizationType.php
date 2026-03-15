<?php

namespace App\Enums;

enum AuthorizationType: string
{
    case ETA = 'ETA';
    case VISA = 'VISA';

    public function label(): string
    {
        return match($this) {
            self::ETA => 'Electronic Travel Authorization',
            self::VISA => 'Visa',
        };
    }

    public function shortLabel(): string
    {
        return $this->value;
    }

    public function validityPeriod(): int
    {
        return match($this) {
            self::ETA => 90, // 90 days
            self::VISA => 365, // 1 year (varies by type)
        };
    }
}