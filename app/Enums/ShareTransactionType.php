<?php

namespace App\Enums;

enum ShareTransactionType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Transfer = 'transfer';
    case Gift = 'gift';
    case Redemption = 'redemption';
    case CapitalIncrease = 'capital_increase';
    case CapitalDecrease = 'capital_decrease';
    case Correction = 'correction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Kauf',
            self::Sale => 'Verkauf',
            self::Transfer => 'Übertragung',
            self::Gift => 'Schenkung',
            self::Redemption => 'Einziehung',
            self::CapitalIncrease => 'Kapitalerhöhung',
            self::CapitalDecrease => 'Kapitalherabsetzung',
            self::Correction => 'Korrektur',
            self::Other => 'Sonstige Bewegung',
        };
    }
}
