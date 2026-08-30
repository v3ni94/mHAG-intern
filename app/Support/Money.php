<?php

namespace App\Support;

/**
 * Präzise Geldarithmetik auf Basis von BCMath.
 *
 * Geldbeträge werden systemweit als Dezimal-Strings mit 2 Nachkommastellen
 * geführt (DECIMAL(18,2) in der Datenbank). Zwischenrechnungen (Zinsen)
 * laufen mit erhöhter Genauigkeit (Skala 10) und werden erst am Ende
 * kaufmännisch gerundet. Niemals float für Geldwerte verwenden.
 */
final class Money
{
    public const SCALE = 2;
    public const CALC_SCALE = 10;

    public static function add(string|int|float|null $a, string|int|float|null $b, int $scale = self::SCALE): string
    {
        return bcadd(self::normalize($a), self::normalize($b), $scale);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b, int $scale = self::SCALE): string
    {
        return bcsub(self::normalize($a), self::normalize($b), $scale);
    }

    /**
     * Multiplikation. Die Operanden werden mit Rechengenauigkeit
     * (CALC_SCALE) uebernommen, nicht auf zwei Nachkommastellen gekuerzt:
     * Faktoren sind haeufig Zinssaetze, Tageszaehlfaktoren oder Kurse mit
     * mehr als zwei Nachkommastellen (z. B. Preis je Aktie DECIMAL(18,4)).
     * Das Ergebnis wird auf $scale gekuerzt; kaufmaennisch runden mit round().
     */
    public static function mul(string|int|float|null $a, string|int|float|null $b, int $scale = self::CALC_SCALE): string
    {
        return bcmul(self::normalize($a, self::CALC_SCALE), self::normalize($b, self::CALC_SCALE), $scale);
    }

    /** Division mit Rechengenauigkeit der Operanden, siehe mul(). */
    public static function div(string|int|float|null $a, string|int|float|null $b, int $scale = self::CALC_SCALE): string
    {
        $divisor = self::normalize($b, self::CALC_SCALE);
        if (bccomp($divisor, '0', self::CALC_SCALE) === 0) {
            throw new \InvalidArgumentException('Division durch 0.');
        }

        return bcdiv(self::normalize($a, self::CALC_SCALE), $divisor, $scale);
    }

    /** Vergleich: -1, 0, 1 */
    public static function cmp(string|int|float|null $a, string|int|float|null $b, int $scale = self::SCALE): int
    {
        return bccomp(self::normalize($a), self::normalize($b), $scale);
    }

    public static function isZero(string|int|float|null $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(string|int|float|null $a): bool
    {
        return self::cmp($a, '0') > 0;
    }

    public static function isNegative(string|int|float|null $a): bool
    {
        return self::cmp($a, '0') < 0;
    }

    public static function min(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::normalize($a, self::SCALE) : self::normalize($b, self::SCALE);
    }

    public static function max(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::normalize($a, self::SCALE) : self::normalize($b, self::SCALE);
    }

    public static function abs(string|int|float|null $a, int $scale = self::SCALE): string
    {
        $n = self::normalize($a, $scale);

        return self::isNegative($n) ? bcmul($n, '-1', $scale) : $n;
    }

    public static function negate(string|int|float|null $a, int $scale = self::SCALE): string
    {
        return bcmul(self::normalize($a, $scale), '-1', $scale);
    }

    /**
     * Kaufmännische Rundung (half up) auf $scale Nachkommastellen.
     */
    public static function round(string|int|float|null $value, int $scale = self::SCALE): string
    {
        $value = self::normalize($value, self::CALC_SCALE);
        $factor = bcpow('10', (string) $scale, 0);
        $shifted = bcmul($value, $factor, self::CALC_SCALE);
        $adjust = self::isNegative($shifted) ? '-0.5' : '0.5';
        $rounded = bcadd($shifted, $adjust, 0);

        return bcdiv($rounded, $factor, $scale);
    }

    /**
     * Formatierung nach Organisationsvorgabe: 1.234,56 EUR
     */
    public static function format(string|int|float|null $value, string $currency = 'EUR', bool $withCurrency = true): string
    {
        $value = self::normalize($value);
        $negative = self::isNegative($value);
        $abs = self::abs($value);
        [$int, $dec] = array_pad(explode('.', $abs, 2), 2, '00');
        $int = strrev(implode('.', str_split(strrev($int), 3)));
        $formatted = ($negative ? '-' : '').$int.','.str_pad(substr($dec, 0, 2), 2, '0');

        return $withCurrency ? $formatted.' '.$currency : $formatted;
    }

    /**
     * Deutsche Eingabe in einen Dezimalstring wandeln.
     *
     * Grundsatz: Im Zweifel NICHTS zurückgeben. Ein null führt in den
     * Formularen zu einer Fehlermeldung; eine stillschweigend andere Zahl wäre
     * ein falscher Betrag in den Büchern und damit der schwerere Fehler.
     *
     * Erkannt wird:
     *   "1.234,56"    -> 1234.56   Punkt als Tausender, Komma als Dezimalzeichen
     *   "25.000"      -> 25000.00  reine Tausendergruppierung, deutsche Schreibweise
     *   "1.234.567"   -> 1234567.00
     *   "1234,56"     -> 1234.56
     *   "1234.56"     -> 1234.56   Punkt als Dezimalzeichen, wenn keine
     *                              Tausendergruppierung vorliegt
     *   "25"          -> 25.00
     *
     * Abgelehnt wird (Rückgabe null):
     *   "12.3456" bei $scale = 2   mehr Nachkommastellen als das Feld führt.
     *                              Früher wurde hier stillschweigend auf 12,34
     *                              gekürzt.
     *   "1.23.456"                 keine deutbare Schreibweise
     *   "abc", "12,34,56"
     *
     * Das Verhalten bei "25.000" ist bewusst festgelegt: In der deutschen
     * Oberfläche bedeutet diese Eingabe fünfundzwanzigtausend. Zuvor ergab sie
     * 25,00 EUR, also einen um den Faktor 1000 falschen Betrag.
     *
     * @param  int  $scale  Nachkommastellen des Zielfeldes. Für Beträge 2, für
     *                      Kurse je Aktie 4, für Quoten und Zinssätze 6.
     */
    public static function parse(?string $input, int $scale = self::SCALE): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $s = trim(str_replace([' ', "\u{a0}", "'", 'EUR', '€'], '', $input));

        $negativ = str_starts_with($s, '-');
        if ($negativ || str_starts_with($s, '+')) {
            $s = substr($s, 1);
        }

        if (str_contains($s, ',')) {
            // Komma vorhanden: es ist das Dezimalzeichen, Punkte sind
            // Tausendertrenner. Genau ein Komma ist zulaessig.
            if (substr_count($s, ',') > 1) {
                return null;
            }
            [$ganz, $dezimal] = explode(',', $s, 2);
            if (! self::istGanzzahlMitTausendern($ganz)) {
                return null;
            }
            $ganz = str_replace('.', '', $ganz);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s) === 1) {
            // Reine Tausendergruppierung ohne Dezimalstellen.
            $ganz = str_replace('.', '', $s);
            $dezimal = '';
        } elseif (preg_match('/^(\d+)(?:\.(\d+))?$/', $s, $treffer) === 1) {
            // Punkt als Dezimalzeichen, oder gar kein Trennzeichen.
            $ganz = $treffer[1];
            $dezimal = $treffer[2] ?? '';
        } else {
            return null;
        }

        if ($dezimal !== '' && preg_match('/^\d+$/', $dezimal) !== 1) {
            return null;
        }

        // Mehr Nachkommastellen als das Zielfeld fuehrt: ablehnen statt kuerzen.
        if (strlen(rtrim($dezimal, '0')) > $scale) {
            return null;
        }

        $wert = ($negativ ? '-' : '').$ganz.($dezimal === '' ? '' : '.'.$dezimal);

        return self::normalize($wert, $scale);
    }

    /**
     * Deutsche Prozent- oder Quoteneingabe in einen Dezimalstring wandeln.
     *
     * Bewusst NICHT dieselbe Regel wie bei Beträgen: Bei einem Prozentsatz ist
     * "3.125" als 3,125 zu lesen, nicht als dreitausendeinhundertfünfundzwanzig.
     * Ein Punkt ist hier also stets das Dezimalzeichen, es sei denn, ein Komma
     * ist vorhanden; dann sind Punkte Tausendertrenner.
     *
     * Mehr Nachkommastellen als das Zielfeld führt: Rückgabe null, damit die
     * Validierung es beanstandet, statt die Datenbank stillschweigend zu kürzen.
     *
     * @param  int  $scale  Nachkommastellen des Zielfeldes, Vorgabe 6
     *                      (Zinssätze und Quoten liegen als DECIMAL(9,6)).
     */
    public static function parsePercent(?string $input, int $scale = 6): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $s = trim(str_replace([' ', "\u{a0}", '%', "'"], '', $input));

        $negativ = str_starts_with($s, '-');
        if ($negativ || str_starts_with($s, '+')) {
            $s = substr($s, 1);
        }

        if (str_contains($s, ',')) {
            if (substr_count($s, ',') > 1) {
                return null;
            }
            [$ganz, $dezimal] = explode(',', $s, 2);
            if (! self::istGanzzahlMitTausendern($ganz)) {
                return null;
            }
            $ganz = str_replace('.', '', $ganz);
        } elseif (preg_match('/^(\d+)(?:\.(\d+))?$/', $s, $treffer) === 1) {
            $ganz = $treffer[1];
            $dezimal = $treffer[2] ?? '';
        } else {
            return null;
        }

        if ($dezimal !== '' && preg_match('/^\d+$/', $dezimal) !== 1) {
            return null;
        }
        if (strlen(rtrim($dezimal, '0')) > $scale) {
            return null;
        }

        return ($negativ ? '-' : '').$ganz.($dezimal === '' ? '' : '.'.$dezimal);
    }

    /** Ganzzahliger Teil, entweder ohne Punkte oder als saubere Tausendergruppierung. */
    private static function istGanzzahlMitTausendern(string $teil): bool
    {
        if ($teil === '') {
            return false;
        }
        if (preg_match('/^\d+$/', $teil) === 1) {
            return true;
        }

        return preg_match('/^\d{1,3}(\.\d{3})+$/', $teil) === 1;
    }

    public static function normalize(string|int|float|null $value, int $scale = self::SCALE): string
    {
        if ($value === null || $value === '') {
            return bcadd('0', '0', $scale);
        }
        if (is_float($value)) {
            $value = number_format($value, $scale + 4, '.', '');
        }

        return bcadd((string) $value, '0', $scale);
    }

    /** Summe einer Liste von Beträgen. */
    public static function sum(iterable $values, int $scale = self::SCALE): string
    {
        $total = bcadd('0', '0', $scale);
        foreach ($values as $v) {
            $total = bcadd($total, self::normalize($v, $scale), $scale);
        }

        return $total;
    }
}
