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
    case Capitalized = 'capitalized';
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
            self::Capitalized => 'Dem Kapital zugeschrieben',
            self::Waived => 'Erlassen',
            self::Cancelled => 'Storniert',
        };
    }

    /**
     * Gilt die Position nur aufgrund einer Annahme als erfüllt?
     *
     * Abschnitt 24: Solange keine Abweichung erfasst ist, wird planmäßige
     * Erfüllung angenommen. "Geplant" gehört ausdrücklich dazu, unabhängig
     * davon, ob die Fortschreibung auf "Systemseitig angenommen" schon
     * gelaufen ist. Ohne diese Gleichbehandlung wies dieselbe Position im
     * Darlehensreiter den vollen Betrag als offen aus, während Kennzahl und
     * Forderungsaufstellung 0,00 meldeten.
     *
     * Diese Aufzählung ist die einzige Stelle, an der die Zuordnung steht.
     * Modell und LoanBalanceService greifen beide darauf zu, damit sie nicht
     * wieder auseinanderlaufen können.
     */
    public function giltAlsErfuelltDurchAnnahme(): bool
    {
        return in_array($this, [self::Planned, self::Assumed], true);
    }

    /** Liegt ein bestätigter IST-Betrag vor? */
    public function hatBestaetigtenIst(): bool
    {
        return in_array($this, [self::Confirmed, self::Partial, self::Late], true);
    }

    /**
     * Ist die Position erledigt, ohne dass eine Zahlung zu erwarten ist?
     * Erlassen, storniert und dem Kapital zugeschrieben schulden nichts mehr.
     */
    public function istAbgeschlossenOhneZahlung(): bool
    {
        return in_array($this, [self::Capitalized, self::Waived, self::Cancelled], true);
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
            self::Capitalized => 'info',
            self::Waived => 'neutral',
            self::Cancelled => 'neutral',
        };
    }
}
