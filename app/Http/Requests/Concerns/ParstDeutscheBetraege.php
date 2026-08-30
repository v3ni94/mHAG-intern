<?php

namespace App\Http\Requests\Concerns;

use App\Support\Money;
use Illuminate\Validation\Validator;

/**
 * Deutsche Betragseingaben in Formularen umwandeln, ohne stillschweigend zu
 * verändern oder zu verlieren.
 *
 * Anlass ist ein Befund vom 30.08.2026. Zwei Muster waren im Einsatz:
 *
 *   $this->merge(['betrag' => Money::parse(...)]);        // gefährlich
 *   $this->merge(['betrag' => Money::parse(...) ?? $roh]); // besser, reicht nicht
 *
 * Beim ersten Muster wird eine nicht deutbare Eingabe zu null. Da die Regel
 * "nullable" lautet, läuft der Vorgang durch, und der Betrag ist verschwunden,
 * ohne Meldung. Beim Ausfall eines Darlehens hätte das bedeutet: Status
 * geändert, Abschreibung nicht gebucht, kein Hinweis.
 *
 * Beim zweiten Muster bleibt der Rohwert stehen und die Regel "numeric" greift.
 * Das genügt für "abc", nicht aber für eine Eingabe, die zwar eine Zahl ist,
 * aber mehr Nachkommastellen führt als das Feld. "12.3456" in einem Feld mit
 * zwei Stellen ist numerisch gültig; die Datenbank kürzt dann stillschweigend.
 *
 * Dieser Weg beanstandet beides ausdrücklich und nennt die zulässige Form.
 * Der Rohwert bleibt in der Anfrage, damit das Formular ihn wieder anzeigt.
 */
trait ParstDeutscheBetraege
{
    /** @var array<string, int> Feld auf Zielgenauigkeit, für die Meldung. */
    private array $unlesbareBetraege = [];

    /**
     * Ein Betragsfeld umwandeln.
     *
     * @param  int  $scale  Nachkommastellen des Zielfeldes: 2 für Beträge,
     *                      4 für Kurse je Aktie, 6 für Quoten und Zinssätze.
     */
    protected function parstBetrag(string $feld, int $scale = Money::SCALE): void
    {
        if (! $this->filled($feld)) {
            return;
        }

        $roh = $this->input($feld);
        if (is_array($roh) || is_bool($roh)) {
            return;
        }

        $geparst = Money::parse((string) $roh, $scale);

        if ($geparst === null) {
            // Rohwert stehen lassen und ausdrücklich beanstanden.
            $this->unlesbareBetraege[$feld] = $scale;

            return;
        }

        $this->merge([$feld => $geparst]);
    }

    /**
     * Ein Prozent- oder Quotenfeld umwandeln.
     *
     * Andere Regel als bei Beträgen: Ein Punkt ist hier das Dezimalzeichen,
     * "3.125" ist also 3,125 Prozent und nicht dreitausend.
     */
    protected function parstProzent(string $feld, int $scale = 6): void
    {
        if (! $this->filled($feld)) {
            return;
        }

        $roh = $this->input($feld);
        if (is_array($roh) || is_bool($roh)) {
            return;
        }

        $geparst = Money::parsePercent((string) $roh, $scale);

        if ($geparst === null) {
            $this->unlesbareBetraege[$feld] = $scale;

            return;
        }

        $this->merge([$feld => $geparst]);
    }

    /** Mehrere Prozentfelder mit derselben Genauigkeit. */
    protected function parstProzente(array $felder, int $scale = 6): void
    {
        foreach ($felder as $feld) {
            $this->parstProzent($feld, $scale);
        }
    }

    /** Mehrere Felder mit derselben Genauigkeit. */
    protected function parstBetraege(array $felder, int $scale = Money::SCALE): void
    {
        foreach ($felder as $feld) {
            $this->parstBetrag($feld, $scale);
        }
    }

    /**
     * Beanstandungen in die Validierung geben. Aufzurufen in withValidator().
     */
    protected function betragsfehlerMelden(Validator $validator): void
    {
        foreach ($this->unlesbareBetraege as $feld => $scale) {
            $validator->errors()->add($feld, self::betragsmeldung($scale));
        }
    }

    /** Gibt es beanstandete Betragsfelder? */
    protected function hatBetragsfehler(): bool
    {
        return $this->unlesbareBetraege !== [];
    }

    private static function betragsmeldung(int $scale): string
    {
        $beispiel = match ($scale) {
            0 => '1.234',
            2 => '1.234,56',
            4 => '12,3456',
            6 => '33,333333',
            default => '1.234,'.str_repeat('5', $scale),
        };

        return 'Der Wert ist keine zulässige Zahl. Zulässig sind höchstens '
            .$scale.' Nachkommastellen, Komma als Dezimalzeichen, Punkt als '
            .'Tausendertrennzeichen. Beispiel: '.$beispiel.'.';
    }
}
