<?php

namespace App\Enums;

enum AllocationBucket: string
{
    case Costs = 'costs';
    case Fees = 'fees';
    case DefaultInterest = 'default_interest';
    case Interest = 'interest';
    case Principal = 'principal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Costs => 'Kosten',
            self::Fees => 'Gebühren',
            self::DefaultInterest => 'Verzugszinsen',
            self::Interest => 'Vertragszinsen',
            self::Principal => 'Kapital',
            self::Other => 'Sonstige Forderungen',
        };
    }
}
