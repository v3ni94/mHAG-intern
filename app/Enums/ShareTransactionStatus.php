<?php

namespace App\Enums;

enum ShareTransactionStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case ContractCreated = 'contract_created';
    case ForSignature = 'for_signature';
    case Signed = 'signed';
    case Resolved = 'resolved';
    case Effective = 'effective';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Review => 'In Prüfung',
            self::ContractCreated => 'Vertrag erstellt',
            self::ForSignature => 'Zur Unterschrift',
            self::Signed => 'Unterschrieben',
            self::Resolved => 'Beschlossen',
            self::Effective => 'Wirksam',
            self::Cancelled => 'Storniert',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Review => 'info',
            self::ContractCreated => 'info',
            self::ForSignature => 'warning',
            self::Signed => 'info',
            self::Resolved => 'info',
            self::Effective => 'success',
            self::Cancelled => 'danger',
        };
    }
}
