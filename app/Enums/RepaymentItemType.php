<?php

namespace App\Enums;

enum RepaymentItemType: string
{
    case Interest = 'interest';
    case Principal = 'principal';
    case Fee = 'fee';

    public function label(): string
    {
        return match ($this) {
            self::Interest => 'Zinsen',
            self::Principal => 'Tilgung',
            self::Fee => 'Gebühr',
        };
    }
}
