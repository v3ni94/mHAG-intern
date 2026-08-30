<?php

namespace App\Enums;

/**
 * Sichtbarkeitsmodus für externe Benutzer (Anforderung vom 30.08.2026).
 *
 * Interne Rollen sehen immer den Gesamtbestand; dieser Modus wirkt
 * ausschließlich für externe Rollen.
 */
enum EntityScopeMode: string
{
    /** Bisheriges Verhalten: sichtbar sind nur die zugeordneten Gesellschaften. */
    case Include = 'include';

    /** Sichtbar ist alles außer den zugeordneten Gesellschaften. */
    case Exclude = 'exclude';

    public function label(): string
    {
        return match ($this) {
            self::Include => 'Nur die zugeordneten Gesellschaften',
            self::Exclude => 'Alles außer den zugeordneten Gesellschaften',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Include => 'Sichtbar sind ausschließlich die unten zugeordneten Personen und Unternehmen '
                .'sowie die zugehörigen Darlehen, Zahlungen und Dokumente. Standard für Darlehensgeber, '
                .'Darlehensnehmer und Aktionäre.',
            self::Exclude => 'Sichtbar ist der gesamte Bestand mit Ausnahme der unten zugeordneten Personen '
                .'und Unternehmen. Ein Vorgang bleibt verborgen, sobald eine ausgeschlossene Gesellschaft '
                .'daran beteiligt ist. Später angelegte Gesellschaften sind automatisch sichtbar. '
                .'Gedacht für Partner, die den Bestand bis auf einzelne Gesellschaften bearbeiten sollen.',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Include => 'info',
            self::Exclude => 'warning',
        };
    }
}
