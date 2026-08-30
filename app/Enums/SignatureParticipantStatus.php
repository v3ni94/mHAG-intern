<?php

namespace App\Enums;

enum SignatureParticipantStatus: string
{
    case NotSent = 'not_sent';
    case Sent = 'sent';
    case Opened = 'opened';
    case Signed = 'signed';
    case Declined = 'declined';
    case Expired = 'expired';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotSent => 'Nicht versendet',
            self::Sent => 'Versendet',
            self::Opened => 'Geöffnet',
            self::Signed => 'Unterschrieben',
            self::Declined => 'Abgelehnt',
            self::Expired => 'Abgelaufen',
            self::Error => 'Fehler',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::NotSent => 'neutral',
            self::Sent => 'info',
            self::Opened => 'info',
            self::Signed => 'success',
            self::Declined => 'danger',
            self::Expired => 'danger',
            self::Error => 'danger',
        };
    }
}
