<?php

namespace App\Enums;

enum RiskRating: string
{
    case VeryLow = 'very_low';
    case Low = 'low';
    case Medium = 'medium';
    case Elevated = 'elevated';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::VeryLow => 'Sehr niedrig',
            self::Low => 'Niedrig',
            self::Medium => 'Mittel',
            self::Elevated => 'Erhöht',
            self::High => 'Hoch',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::VeryLow => 'success',
            self::Low => 'success',
            self::Medium => 'info',
            self::Elevated => 'warning',
            self::High => 'danger',
        };
    }
}
