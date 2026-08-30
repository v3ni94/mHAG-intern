<?php

namespace App\Enums;

enum DisbursementStatus: string
{
    case Planned = 'planned';
    case Assumed = 'assumed';
    case Confirmed = 'confirmed';
    case Partial = 'partial';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Geplant',
            self::Assumed => 'Systemseitig angenommen',
            self::Confirmed => 'Bestätigt ausgezahlt',
            self::Partial => 'Teilweise ausgezahlt',
            self::Failed => 'Nicht ausgezahlt',
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
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
        };
    }
}
