<?php

namespace App\Enums;

enum RepaymentModel: string
{
    case Bullet = 'bullet';
    case Installment = 'installment';
    case Annuity = 'annuity';
    case Custom = 'custom';
    case OpenEnded = 'open_ended';
    case Frame = 'frame';
    case CurrentAccount = 'current_account';

    public function label(): string
    {
        return match ($this) {
            self::Bullet => 'Endfällig',
            self::Installment => 'Ratendarlehen',
            self::Annuity => 'Annuitätendarlehen',
            self::Custom => 'Individuell',
            self::OpenEnded => 'Unbefristet',
            self::Frame => 'Rahmendarlehen',
            self::CurrentAccount => 'Kontokorrent',
        };
    }
}
