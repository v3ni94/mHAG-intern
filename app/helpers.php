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

if (! function_exists('money_mask')) {
    /** Platzhalter des Datenschutzmodus (Abschnitt 126). */
    function money_mask(): string
    {
        return '•••••• €';
    }
}

if (! function_exists('money_masking_suppressed')) {
    /**
     * Interner Kontextschalter. Solange er gesetzt ist, wird nicht maskiert
     * (siehe without_money_masking()). Ohne Argument nur Abfrage.
     */
    function money_masking_suppressed(?bool $set = null): bool
    {
        static $suppressed = false;

        if ($set !== null) {
            $suppressed = $set;
        }

        return $suppressed;
    }
}

if (! function_exists('without_money_masking')) {
    /**
     * Führt den Rückruf mit garantiert echten Beträgen aus. Für Erzeugungs-
     * pfade, die Beträge dauerhaft festschreiben (Dateien, Snapshots, Mails).
     */
    function without_money_masking(callable $callback): mixed
    {
        $previous = money_masking_suppressed();
        money_masking_suppressed(true);

        try {
            return $callback();
        } finally {
            money_masking_suppressed($previous);
        }
    }
}

if (! function_exists('money_output_is_document_context')) {
    /**
     * Erkennt anhand des Aufrufstapels, ob die Ausgabe gerade in eine Datei
     * oder einen dauerhaften Text fließt (PDF via dompdf, XLSX-Writer,
     * Vertragsplatzhalter, Benachrichtigungs- und Mailtexte, Konsolenlauf).
     * Solche Ausgaben werden nie maskiert.
     */
    function money_output_is_document_context(): bool
    {
        $markers = [
            'dompdf',                  // Barryvdh\DomPDF\*, Dompdf\*
            'SimpleXlsxWriter',        // XLSX-Export
            'ContractGenerationService', // Vertragsplatzhalter und Vertrags-PDF
            'NotificationService',     // Benachrichtigungstexte
            'App\\Mail\\',
            'Illuminate\\Mail\\',
            'Illuminate\\Notifications\\',
            'Illuminate\\Console\\',
            'Symfony\\Component\\Console\\',
        ];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $class = (string) ($frame['class'] ?? '');
            if ($class === '') {
                continue;
            }
            foreach ($markers as $marker) {
                if (stripos($class, $marker) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (! function_exists('money_masking_active')) {
    /**
     * Datenschutzmodus (Abschnitt 126). Zentrale Entscheidung, damit JEDE
     * Geldausgabe sie berücksichtigt (format_money(), @money, <x-money>).
     *
     * Maskiert wird ausschließlich die Bildschirmausgabe einer angemeldeten
     * Web-Sitzung. Nicht maskiert werden PDF-Erzeugung, CSV-/XLSX-Exporte,
     * E-Mails, Benachrichtigungen, Konsolenläufe und direkte Aufrufe ohne
     * Web-Kontext (Tests, Scheduler, Queue).
     */
    function money_masking_active(): bool
    {
        if (money_masking_suppressed()) {
            return false;
        }

        try {
            if (! app()->bound('request')) {
                return false;
            }

            $request = request();
            if (! $request instanceof \Illuminate\Http\Request) {
                return false;
            }

            // Ohne aufgelöste Route liegt keine echte Web-Anfrage vor
            // (Konsolenbefehl, Scheduler, Queue-Worker).
            $route = $request->route();
            if ($route === null) {
                return false;
            }

            $user = auth()->user();
            if ($user === null || ! $user->privacy_mode) {
                return false;
            }

            // Dateiausgaben: Exportformat der Anfrage (Reports: ?format=...).
            $format = strtolower((string) $request->input('format', ''));
            if (in_array($format, ['pdf', 'csv', 'xlsx', 'xls', 'excel'], true)) {
                return false;
            }

            // Routen, die Dateien ausliefern statt Bildschirmseiten.
            $name = (string) ($route->getName() ?? '');
            foreach (['pdf', 'download', 'export', 'statement', 'csv', 'xlsx'] as $needle) {
                if ($name !== '' && str_contains($name, $needle)) {
                    return false;
                }
            }

            // Laufende Datei- oder Dokumenterzeugung innerhalb der Anfrage
            // (z. B. Vertrags-PDF beim Finalisieren, Aktionärslisten-Snapshot).
            return ! money_output_is_document_context();
        } catch (\Throwable) {
            // Im Zweifel echte Ausgabe, damit Dateien und Protokolle nie
            // stillschweigend maskierte Beträge enthalten.
            return false;
        }
    }
}

if (! function_exists('format_money')) {
    /**
     * Betrag im Format 1.234,56 EUR.
     *
     * Bei aktivem Datenschutzmodus (Bildschirmausgabe, angemeldeter Benutzer)
     * wird stattdessen der Platzhalter "•••••• €" ausgegeben. Mit
     * $respectPrivacy = false wird immer der echte Betrag geliefert.
     */
    function format_money(string|int|float|null $amount, string $currency = 'EUR', bool $respectPrivacy = true): string
    {
        if ($respectPrivacy && money_masking_active()) {
            return money_mask();
        }

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
