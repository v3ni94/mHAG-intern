<?php

use App\Support\Money;

if (! function_exists('format_date')) {
    /** Datum im Format TT.MM.JJJJ (Organisationsvorgabe). */
    function format_date(mixed $date): string
    {
        if ($date === null) {
            return '';
        }
        if (! $date instanceof \Carbon\CarbonInterface) {
            $date = \Illuminate\Support\Carbon::parse($date);
        }

        return $date->format('d.m.Y');
    }
}

if (! function_exists('format_datetime')) {
    function format_datetime(mixed $date): string
    {
        if ($date === null) {
            return '';
        }
        if (! $date instanceof \Carbon\CarbonInterface) {
            $date = \Illuminate\Support\Carbon::parse($date);
        }

        return $date->format('d.m.Y H:i');
    }
}

if (! function_exists('format_money')) {
    /** Betrag im Format 1.234,56 EUR. */
    function format_money(string|int|float|null $amount, string $currency = 'EUR'): string
    {
        return Money::format($amount, $currency);
    }
}

if (! function_exists('format_percent')) {
    /** Prozentwert mit deutschem Dezimaltrennzeichen, z. B. 6,000000 -> "6,00 %". */
    function format_percent(string|int|float|null $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $rounded = Money::round(Money::normalize($value, 6), $decimals);
        [$int, $dec] = array_pad(explode('.', $rounded, 2), 2, '');

        return $int.($decimals > 0 ? ','.str_pad(substr($dec, 0, $decimals), $decimals, '0') : '').' %';
    }
}
