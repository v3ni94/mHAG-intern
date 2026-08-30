<?php

namespace App\Enums;

enum BookingType: string
{
    case Disbursement = 'disbursement';
    case Repayment = 'repayment';
    case InterestCharge = 'interest_charge';
    case InterestPayment = 'interest_payment';
    case FeeCharge = 'fee_charge';
    case FeePayment = 'fee_payment';
    case DefaultInterest = 'default_interest';
    case Cancellation = 'cancellation';
    case Correction = 'correction';
    case WriteOff = 'write_off';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Disbursement => 'Auszahlung',
            self::Repayment => 'Tilgung',
            self::InterestCharge => 'Vertragszins',
            self::InterestPayment => 'Zinszahlung',
            self::FeeCharge => 'Gebühr',
            self::FeePayment => 'Gebührenzahlung',
            self::DefaultInterest => 'Verzugszins',
            self::Cancellation => 'Storno',
            self::Correction => 'Korrektur',
            self::WriteOff => 'Abschreibung',
            self::Other => 'Sonstige Buchung',
        };
    }
}
