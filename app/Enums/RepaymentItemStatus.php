<?php

namespace App\Enums;

enum RepaymentItemStatus: string
{
    case Planned = 'planned';
    case Assumed = 'assumed';
    case Confirmed = 'confirmed';
    case Partial = 'partial';
    case Missed = 'missed';
    case Late = 'late';
    case Waived = 'waived';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Geplant',
            self::Assumed => 'Systemseitig angenommen',
            self::Confirmed => 'Bestätigt bezahlt',
            self::Partial => 'Teilweise bezahlt',
            self::Missed => 'Nicht bezahlt',
            self::Late => 'Verspätet bezahlt',
            self::Waived => 'Erlassen',
            self::Cancelled => 'Storniert',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Assumed => 'info',
            self::Confirmed => 'success',
            self::Partial => 'warning',
            self::Missed => 'danger',
            self::Late => 'warning',
            self::Waived => 'neutral',
            self::Cancelled => 'neutral',
        };
    }
}
