<?php

namespace App\Enums;

enum EntryType: string
{
    case Single = 'single';
    case Multiple = 'multiple';

    public function label(): string
    {
        return match($this) {
            self::Single => 'Single Entry',
            self::Multiple => 'Multiple Entry',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::Single => 'Valid for one entry into Ghana',
            self::Multiple => 'Valid for multiple entries into Ghana',
        };
    }

    public function hasAdditionalFee(): bool
    {
        return $this === self::Multiple;
    }
}