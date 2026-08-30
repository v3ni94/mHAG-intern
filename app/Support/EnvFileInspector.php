<?php

namespace App\Support;

/**
 * Prüfung der Konfigurationsdatei .env auf Syntaxfehler.
 *
 * Hintergrund: Die .env wird gelesen, BEVOR Laravel eine Fehlerbehandlung
 * oder ein Protokoll besitzt. Eine einzige ungültige Zeile führt deshalb zu
 * einem nackten Serverfehler 500 auf jeder Seite, ohne Meldung und ohne
 * Eintrag im Anwendungsprotokoll.
 *
 * Verschärfend: Solange bootstrap/cache/config.php vorhanden ist, wird die
 * .env überhaupt nicht gelesen. Der Fehler bleibt also unbemerkt und tritt
 * erst in dem Moment auf, in dem der Zwischenspeicher geleert wird, also
 * typischerweise nach einem Datei-Upload. Diese Klasse macht den Fehler
 * vorher sichtbar.
 *
 * Werte aus der .env werden NIE in einen Befund übernommen, auch nicht in
 * Auszügen: eine gestörte Zeile kann Teil eines Schlüssels oder Kennworts sein.
 * Ein Befund nennt Zeilennummer, Name der Einstellung und Ursache.
 *
 * Diese Klasse hat bewusst KEINE Abhängigkeiten. Sie wird von den
 * Webwerkzeugen in tools/web-setup per require_once eingebunden und muss
 * daher auch ohne geladenes Framework arbeiten.
 *
 * Die geprüften Regeln sind an vlucas/phpdotenv 5.7 nachgestellt und wurden
 * gegen den Parser abgeglichen:
 * - Zeile ohne Gleichheitszeichen: Abbruch ("invalid name").
 * - Leerer Name vor dem Gleichheitszeichen: Abbruch ("unexpected equals").
 * - Bindestrich im Namen: Abbruch ("invalid name").
 * - Unmaskiertes Leerzeichen in einem Wert ohne Anführungszeichen: Abbruch
 *   ("unexpected whitespace").
 * - Nicht geschlossenes Anführungszeichen: kein Abbruch, aber alle
 *   Folgezeilen gehen im Wert unter. Daher als Warnung geführt.
 */
class EnvFileInspector
{
    public const FEHLER = 'fehler';

    public const WARNUNG = 'warnung';

    /**
     * Findings zu einem Dateiinhalt.
     *
     * @return array<int, array{line: int, key: string, severity: string, message: string}>
     */
    public static function inspect(string $inhalt): array
    {
        $befunde = [];
        $zeilen = preg_split('/\r\n|\r|\n/', $inhalt) ?: [];

        $offenesZeichen = null;
        $offeneZeile = 0;
        $offenerSchluessel = '';

        foreach ($zeilen as $index => $zeile) {
            $nummer = $index + 1;

            // Fortsetzung eines mehrzeiligen Wertes: bis zum schliessenden
            // Anfuehrungszeichen gehoert alles zum Wert und wird nicht geprueft.
            if ($offenesZeichen !== null) {
                if (self::schliesst($zeile, $offenesZeichen)) {
                    $offenesZeichen = null;
                }

                continue;
            }

            $roh = trim($zeile);
            if ($roh === '' || str_starts_with($roh, '#')) {
                continue;
            }

            if (! str_contains($roh, '=')) {
                $befunde[] = self::befund($nummer, '', self::FEHLER,
                    'Die Zeile enthält kein Gleichheitszeichen ('.strlen($roh).' Zeichen). Sie ist '
                    .'damit weder Einstellung noch Kommentar; die Anwendung bricht beim Lesen der '
                    .'.env ab. Typische Ursache: Reste eines mehrzeiligen Schlüssels. Entweder die '
                    .'Zeile löschen oder ein # davorsetzen.');

                continue;
            }

            $teile = explode('=', $roh, 2);
            $schluessel = trim($teile[0]);
            $wert = ltrim($teile[1]);

            $name = preg_replace('/^export[ \t]+/', '', $schluessel) ?? $schluessel;

            if ($name === '') {
                $befunde[] = self::befund($nummer, '', self::FEHLER,
                    'Vor dem Gleichheitszeichen steht kein Name. Die Anwendung bricht beim Lesen '
                    .'der .env ab.');

                continue;
            }

            if (str_contains($name, '-')) {
                $befunde[] = self::befund($nummer, $name, self::FEHLER,
                    'Der Name enthält einen Bindestrich. Zulässig sind Buchstaben, Ziffern, '
                    .'Unterstrich und Punkt. Die Anwendung bricht beim Lesen der .env ab.');

                continue;
            }

            if (preg_match('/\s/', $name) === 1) {
                $befunde[] = self::befund($nummer, $name, self::FEHLER,
                    'Der Name enthält ein Leerzeichen. Die Anwendung bricht beim Lesen der .env ab.');

                continue;
            }

            $ersteszeichen = $wert === '' ? '' : $wert[0];

            if ($ersteszeichen === '"' || $ersteszeichen === "'") {
                $ende = self::endePosition($wert, $ersteszeichen);
                if ($ende === null) {
                    $offenesZeichen = $ersteszeichen;
                    $offeneZeile = $nummer;
                    $offenerSchluessel = $name;

                    continue;
                }

                $rest = trim(substr($wert, $ende + 1));
                if ($rest !== '' && ! str_starts_with($rest, '#')) {
                    $befunde[] = self::befund($nummer, $name, self::FEHLER,
                        'Nach dem schließenden Anführungszeichen steht weiterer Text. Die '
                        .'Anwendung bricht beim Lesen der .env ab. Der gesamte Wert muss '
                        .'innerhalb der Anführungszeichen stehen.');
                }

                continue;
            }

            // Wert ohne Anfuehrungszeichen: ein nachgestellter Kommentar ist
            // zulaessig, ein Leerzeichen innerhalb des Wertes nicht.
            $ohneKommentar = preg_split('/[ \t]+#/', $wert, 2)[0] ?? $wert;
            $ohneKommentar = rtrim($ohneKommentar);

            if ($ohneKommentar !== '' && preg_match('/\s/', $ohneKommentar) === 1) {
                $befunde[] = self::befund($nummer, $name, self::FEHLER,
                    'Der Wert enthält ein Leerzeichen, steht aber nicht in Anführungszeichen. '
                    .'Die Anwendung bricht beim Lesen der .env ab. Bitte den Wert in "..." setzen.');

                continue;
            }

            if (str_contains($ohneKommentar, '<') || str_contains($ohneKommentar, '>')) {
                $befunde[] = self::befund($nummer, $name, self::WARNUNG,
                    'Der Wert steht noch in spitzen Klammern, der Platzhalter aus der '
                    .'Beispieldatei wurde also nicht ersetzt.');
            }
        }

        if ($offenesZeichen !== null) {
            $befunde[] = self::befund($offeneZeile, $offenerSchluessel, self::WARNUNG,
                'Das Anführungszeichen wird bis zum Dateiende nicht geschlossen. Alle '
                .'nachfolgenden Zeilen zählen damit zum Wert und wirken nicht mehr als '
                .'eigene Einstellung.');
        }

        return $befunde;
    }

    /** Findings zu einer Datei. Eine fehlende Datei ist selbst ein Fehler. */
    public static function inspectFile(string $pfad): array
    {
        if (! is_readable($pfad)) {
            return [self::befund(0, '', self::FEHLER,
                'Die Datei .env wurde nicht gefunden oder ist nicht lesbar.')];
        }

        return self::inspect((string) file_get_contents($pfad));
    }

    /** Nur die Befunde, die den Start der Anwendung verhindern. */
    public static function fehler(array $befunde): array
    {
        return array_values(array_filter($befunde, fn ($b) => $b['severity'] === self::FEHLER));
    }

    public static function hatFehler(array $befunde): bool
    {
        return self::fehler($befunde) !== [];
    }

    /**
     * Position des schliessenden Anfuehrungszeichens, Maskierung mit
     * Backslash beruecksichtigt. null, wenn es in dieser Zeile fehlt.
     */
    private static function endePosition(string $wert, string $zeichen): ?int
    {
        $laenge = strlen($wert);
        for ($i = 1; $i < $laenge; $i++) {
            if ($wert[$i] === '\\') {
                $i++;

                continue;
            }
            if ($wert[$i] === $zeichen) {
                return $i;
            }
        }

        return null;
    }

    /** Schliesst diese Fortsetzungszeile den offenen Wert? */
    private static function schliesst(string $zeile, string $zeichen): bool
    {
        $laenge = strlen($zeile);
        for ($i = 0; $i < $laenge; $i++) {
            if ($zeile[$i] === '\\') {
                $i++;

                continue;
            }
            if ($zeile[$i] === $zeichen) {
                return true;
            }
        }

        return false;
    }

    private static function befund(int $zeile, string $schluessel, string $schwere, string $meldung): array
    {
        return [
            'line' => $zeile,
            'key' => $schluessel,
            'severity' => $schwere,
            'message' => $meldung,
        ];
    }
}
