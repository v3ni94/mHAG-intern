<?php

namespace App\Enums;

enum InterestFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
    case AtMaturity = 'at_maturity';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monatlich',
            self::Quarterly => 'Quartalsweise',
            self::Semiannual => 'Halbjährlich',
            self::Annual => 'Jährlich',
            self::AtMaturity => 'Zum Vertragsende',
            self::Custom => 'Individuell',
        };
    }
}
