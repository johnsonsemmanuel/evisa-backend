<?php

namespace App\Enums;

enum BorderCrossingType: string
{
    case Entry = 'entry';
    case Exit = 'exit';

    public function label(): string
    {
        return match($this) {
            self::Entry => 'Entry',
            self::Exit => 'Exit',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::Entry => 'Entering Ghana',
            self::Exit => 'Exiting Ghana',
        };
    }
}