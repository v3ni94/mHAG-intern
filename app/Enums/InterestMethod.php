<?php

namespace App\Enums;

enum InterestMethod: string
{
    case Act365 = 'act_365';
    case Act360 = 'act_360';
    case Thirty360 = 'thirty_360';
    case ActAct = 'act_act';

    public function label(): string
    {
        return match ($this) {
            self::Act365 => 'ACT/365',
            self::Act360 => 'ACT/360',
            self::Thirty360 => '30/360',
            self::ActAct => 'ACT/ACT',
        };
    }
}
