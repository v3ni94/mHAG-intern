<?php

namespace App\Enums;

enum ReminderPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Niedrig',
            self::Normal => 'Normal',
            self::High => 'Hoch',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'info',
            self::High => 'danger',
        };
    }
}
