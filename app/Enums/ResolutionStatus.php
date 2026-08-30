<?php

namespace App\Enums;

enum ResolutionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Review = 'review';
    case Voting = 'voting';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Postponed = 'postponed';
    case Withdrawn = 'withdrawn';
    case ForSignature = 'for_signature';
    case Signed = 'signed';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Submitted => 'Antrag gestellt',
            self::Review => 'In Prüfung',
            self::Voting => 'Zur Abstimmung',
            self::Accepted => 'Angenommen',
            self::Rejected => 'Abgelehnt',
            self::Postponed => 'Vertagt',
            self::Withdrawn => 'Zurückgezogen',
            self::ForSignature => 'Zur Unterschrift',
            self::Signed => 'Unterschrieben',
            self::Completed => 'Abgeschlossen',
            self::Archived => 'Archiviert',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'info',
            self::Review => 'info',
            self::Voting => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Postponed => 'warning',
            self::Withdrawn => 'neutral',
            self::ForSignature => 'warning',
            self::Signed => 'success',
            self::Completed => 'success',
            self::Archived => 'neutral',
        };
    }
}
