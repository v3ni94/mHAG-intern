<?php

namespace App\Enums;

enum EntityType: string
{
    case Person = 'person';
    case Company = 'company';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Privatperson',
            self::Company => 'Unternehmen',
            self::Organization => 'Sonstige Organisation',
        };
    }
}
