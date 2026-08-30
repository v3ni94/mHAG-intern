<?php

namespace App\Enums;

enum PaymentOrigin: string
{
    case Assumed = 'assumed';
    case ManualConfirmed = 'manual_confirmed';
    case ManualEntered = 'manual_entered';
    case BankImport = 'bank_import';
    case Corrected = 'corrected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Assumed => 'Systemseitig angenommen',
            self::ManualConfirmed => 'Manuell bestätigt',
            self::ManualEntered => 'Manuell erfasst',
            self::BankImport => 'Bankseitig bestätigt',
            self::Corrected => 'Korrigiert',
            self::Cancelled => 'Storniert',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Assumed => 'info',
            self::ManualConfirmed => 'success',
            self::ManualEntered => 'success',
            self::BankImport => 'success',
            self::Corrected => 'warning',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Assumed => 'bi-cpu',
            self::ManualConfirmed, self::ManualEntered => 'bi-person-check',
            self::BankImport => 'bi-bank',
            self::Corrected => 'bi-pencil-square',
            self::Cancelled => 'bi-x-octagon',
        };
    }
}
