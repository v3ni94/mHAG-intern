<?php

namespace App\Enums;

enum SignatureRequestStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Declined = 'declined';
    case Expired = 'expired';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Versendet',
            self::InProgress => 'In Bearbeitung',
            self::Completed => 'Abgeschlossen',
            self::Declined => 'Abgelehnt',
            self::Expired => 'Abgelaufen',
            self::Error => 'Fehler',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Declined => 'danger',
            self::Expired => 'danger',
            self::Error => 'danger',
        };
    }
}
