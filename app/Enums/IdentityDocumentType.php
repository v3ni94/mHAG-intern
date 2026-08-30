<?php

namespace App\Enums;

enum IdentityDocumentType: string
{
    case IdCard = 'id_card';
    case Passport = 'passport';
    case ResidencePermit = 'residence_permit';
    case DriversLicense = 'drivers_license';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IdCard => 'Personalausweis',
            self::Passport => 'Reisepass',
            self::ResidencePermit => 'Aufenthaltstitel',
            self::DriversLicense => 'Führerschein',
            self::Other => 'Sonstiger Identitätsnachweis',
        };
    }
}
