<?php

namespace App\Enums;

/**
 * Fälligkeitstag der Zinsperioden (Anforderung vom 30.08.2026).
 *
 * Die Zinsperiode endet am Fälligkeitstag einschließlich, die nächste
 * Periode beginnt am Folgetag. Die Berechnung bleibt taggenau; es ändert
 * sich nur das Raster der Fälligkeiten.
 */
enum InterestDueDayMode: string
{
    /** Bisheriges Verhalten: Raster aus dem Wirkungsbeginn abgeleitet. */
    case EffectiveFrom = 'effective_from';

    /** Fester Tag im Monat (1 bis 28). */
    case FixedDay = 'fixed_day';

    /** Letzter Tag des Monats. */
    case MonthEnd = 'month_end';

    public function label(): string
    {
        return match ($this) {
            self::EffectiveFrom => 'Aus dem Wirkungsbeginn abgeleitet',
            self::FixedDay => 'Fester Tag im Monat',
            self::MonthEnd => 'Letzter Tag des Monats',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EffectiveFrom => 'Die Zinsperiode beginnt am Wirkungsbeginn und endet jeweils einen Tag vor dem gleichen Tag der Folgeperiode.',
            self::FixedDay => 'Die Zinsen werden jeweils zum gewählten Tag fällig. Zulässig sind die Tage 1 bis 28, weil ein fester 29., 30. oder 31. nicht in jedem Monat existiert. Für den Monatsletzten ist "Letzter Tag des Monats" zu wählen.',
            self::MonthEnd => 'Die Zinsen werden jeweils zum letzten Tag des Monats fällig, unabhängig von seiner Länge.',
        };
    }

    /** Kleinster und größter zulässiger fester Fälligkeitstag. */
    public const FIXED_DAY_MIN = 1;

    public const FIXED_DAY_MAX = 28;
}
