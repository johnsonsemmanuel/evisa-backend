<?php

namespace App\Enums;

enum OcrStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending OCR',
            self::Processing => 'Processing',
            self::Passed => 'OCR Passed',
            self::Failed => 'OCR Failed',
            self::Skipped => 'OCR Skipped',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'gray',
            self::Processing => 'blue',
            self::Passed => 'green',
            self::Failed => 'red',
            self::Skipped => 'yellow',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Passed, self::Failed, self::Skipped]);
    }
}