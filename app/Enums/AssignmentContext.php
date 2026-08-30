<?php

namespace App\Enums;

/**
 * Art einer Benutzerzuordnung (Abschnitt 13: Kontextwechsel).
 *
 * Die Zuordnung beantwortet zwei Fragen: in welcher Eigenschaft handelt der
 * Benutzer für diese Gesellschaft, und unter welcher Bezeichnung erscheint
 * die Ansicht im Umschalter.
 */
enum AssignmentContext: string
{
    /** Eigene Person oder eigenes Unternehmen. */
    case Self = 'self';

    /** Für die Gesellschaft handelnd: Geschäftsführung, Vorstand, Prokura. */
    case Company = 'company';

    /** Aufsichtsrat der Gesellschaft. */
    case SupervisoryBoard = 'supervisory_board';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Selbst',
            self::Company => 'Geschäftsführung oder Vorstand',
            self::SupervisoryBoard => 'Aufsichtsrat',
        };
    }

    /** Kurzform für den Ansichtsumschalter. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Self => 'Selbst',
            self::Company => 'Vorstand',
            self::SupervisoryBoard => 'Aufsichtsrat',
        };
    }

    /** Passender Kontext zu einem Organtyp (corporate_bodies.type). */
    public static function fromBodyType(?string $type): self
    {
        return match ($type) {
            'supervisory_board' => self::SupervisoryBoard,
            default => self::Company,
        };
    }
}
