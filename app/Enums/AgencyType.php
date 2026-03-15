<?php

namespace App\Enums;

enum AgencyType: string
{
    case GIS = 'gis';
    case MFA = 'mfa';

    public function label(): string
    {
        return match($this) {
            self::GIS => 'Ghana Immigration Service',
            self::MFA => 'Ministry of Foreign Affairs',
        };
    }

    public function shortLabel(): string
    {
        return match($this) {
            self::GIS => 'GIS',
            self::MFA => 'MFA',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::GIS => 'blue',
            self::MFA => 'green',
        };
    }

    public function canProcessVisaType(string $visaType): bool
    {
        return match($this) {
            self::GIS => in_array($visaType, [
                'tourist',
                'business',
                'transit',
                'medical',
                'student',
                'work'
            ]),
            self::MFA => in_array($visaType, [
                'diplomatic',
                'official',
                'courtesy',
                'service'
            ]),
        };
    }
}