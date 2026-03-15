<?php

namespace App\Enums;

enum BorderVerificationStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case NotFound = 'not_found';
    case SecondaryInspection = 'secondary_inspection';

    public function label(): string
    {
        return match($this) {
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
            self::Expired => 'Expired',
            self::NotFound => 'Not Found',
            self::SecondaryInspection => 'Secondary Inspection Required',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Valid => 'green',
            self::Invalid => 'red',
            self::Expired => 'orange',
            self::NotFound => 'gray',
            self::SecondaryInspection => 'yellow',
        };
    }

    public function allowsEntry(): bool
    {
        return $this === self::Valid;
    }

    public function requiresAction(): bool
    {
        return in_array($this, [
            self::Invalid,
            self::Expired,
            self::NotFound,
            self::SecondaryInspection
        ]);
    }
}