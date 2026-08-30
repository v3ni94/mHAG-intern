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

    public static function mul(string|int|float|null $a, string|int|float|null $b, int $scale = self::CALC_SCALE): string
    {
        return bcmul(self::normalize($a), self::normalize($b), $scale);
    }

    public static function div(string|int|float|null $a, string|int|float|null $b, int $scale = self::CALC_SCALE): string
    {
        $divisor = self::normalize($b);
        if (bccomp($divisor, '0', self::CALC_SCALE) === 0) {
            throw new \InvalidArgumentException('Division durch 0.');
        }

        return bcdiv(self::normalize($a), $divisor, $scale);
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
     * Deutsche Eingabe ("1.234,56") in Dezimal-String wandeln.
     */
    public static function parse(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }
        $s = trim(str_replace([' ', "\u{a0}", 'EUR', '€'], '', $input));
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }

        return self::normalize($s);
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
