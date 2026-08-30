<?php

namespace App\Enums;

enum VoteChoice: string
{
    case Yes = 'yes';
    case No = 'no';
    case Abstain = 'abstain';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Ja',
            self::No => 'Nein',
            self::Abstain => 'Enthaltung',
            self::Absent => 'Nicht teilgenommen',
        };
    }
}
