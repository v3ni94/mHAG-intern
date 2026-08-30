<?php

namespace App\Enums;

enum SecurityType: string
{
    case Guarantee = 'guarantee';
    case LandCharge = 'land_charge';
    case Mortgage = 'mortgage';
    case ChattelTransfer = 'chattel_transfer';
    case Pledge = 'pledge';
    case Assignment = 'assignment';
    case CompanyShares = 'company_shares';
    case Shares = 'shares';
    case RealEstate = 'real_estate';
    case Vehicle = 'vehicle';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Guarantee => 'Bürgschaft',
            self::LandCharge => 'Grundschuld',
            self::Mortgage => 'Hypothek',
            self::ChattelTransfer => 'Sicherungsübereignung',
            self::Pledge => 'Verpfändung',
            self::Assignment => 'Forderungsabtretung',
            self::CompanyShares => 'Geschäftsanteile',
            self::Shares => 'Aktien',
            self::RealEstate => 'Immobilie',
            self::Vehicle => 'Fahrzeug',
            self::Other => 'Sonstige Sicherheit',
        };
    }
}
