<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Done => 'Erledigt',
            self::Cancelled => 'Abgebrochen',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Done => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
