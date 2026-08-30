<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktiv',
            self::Archived => 'Archiviert',
            self::Deleted => 'Gelöscht',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Archived => 'neutral',
            self::Deleted => 'danger',
        };
    }
}
