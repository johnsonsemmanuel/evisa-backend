<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match($this) {
            self::Low => 'Low Risk',
            self::Medium => 'Medium Risk',
            self::High => 'High Risk',
            self::Critical => 'Critical Risk',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Low => 'green',
            self::Medium => 'yellow',
            self::High => 'orange',
            self::Critical => 'red',
        };
    }

    public function score(): int
    {
        return match($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    public function requiresManualReview(): bool
    {
        return in_array($this, [self::High, self::Critical]);
    }

    public function requiresSecondaryScreening(): bool
    {
        return $this === self::Critical;
    }

    public static function fromScore(int $score): self
    {
        return match(true) {
            $score <= 25 => self::Low,
            $score <= 50 => self::Medium,
            $score <= 75 => self::High,
            default => self::Critical,
        };
    }
}