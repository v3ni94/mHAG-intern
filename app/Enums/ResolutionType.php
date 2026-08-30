<?php

namespace App\Enums;

enum ResolutionType: string
{
    case Board = 'board';
    case SupervisoryBoard = 'supervisory_board';
    case GeneralMeeting = 'general_meeting';
    case Circular = 'circular';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Board => 'Vorstandsbeschluss',
            self::SupervisoryBoard => 'Aufsichtsratsbeschluss',
            self::GeneralMeeting => 'Hauptversammlungsbeschluss',
            self::Circular => 'Umlaufbeschluss',
            self::Other => 'Sonstiger Beschluss',
        };
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Board => 'VOR',
            self::SupervisoryBoard => 'AR',
            self::GeneralMeeting => 'HV',
            self::Circular => 'UB',
            self::Other => 'SB',
        };
    }
}
