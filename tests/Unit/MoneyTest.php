<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Rechenkern für Geldbeträge (eiserne Regel 1: nie float, immer BCMath).
 * Erwartungswerte sind von Hand vorgerechnet und als Kommentar hinterlegt.
 */
class MoneyTest extends TestCase
{
    public function test_addition_und_subtraktion_mit_zwei_nachkommastellen(): void
    {
        $this->assertSame('1234.56', Money::add('1000.00', '234.56'));
        $this->assertSame('765.44', Money::sub('1000.00', '234.56'));
        $this->assertSame('0.00', Money::sub('1000.00', '1000.00'));
    }

    public function test_multiplikation_kuerzt_die_operanden_nicht_auf_zwei_stellen(): void
    {
        // Preis je Aktie ist DECIMAL(18,4). 10.000 Stück * 12,3456 EUR
        // = 123.456,00 EUR. Würde der Faktor auf 12,34 gekürzt, ergäbe
        // sich 123.400,00 EUR, also 56,00 EUR zu wenig.
        $this->assertSame(
            '123456.00',
            Money::round(Money::mul('10000', '12.3456'), 2),
        );

        // Tageszählfaktor: 460,27 EUR * 9 % * 31/365 = 3,5182... = 3,52 EUR.
        $tagesfaktor = Money::div('31', '365');
        $this->assertSame('0.0849315068', $tagesfaktor);
        $this->assertSame(
            '3.52',
            Money::round(Money::mul(Money::mul('460.27', '0.09'), $tagesfaktor), 2),
        );
    }

    public function test_division_durch_null_wird_abgewiesen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::div('100.00', '0');
    }

    public function test_kaufmaennische_rundung_half_up(): void
    {
        $this->assertSame('1.24', Money::round('1.235', 2));
        $this->assertSame('1.23', Money::round('1.234', 2));
        // Negative Beträge werden betragsmäßig aufgerundet
        $this->assertSame('-1.24', Money::round('-1.235', 2));
    }

    public function test_formatierung_nach_organisationsvorgabe(): void
    {
        $this->assertSame('1.234,56 EUR', Money::format('1234.56'));
        $this->assertSame('-1.234,56 EUR', Money::format('-1234.56'));
        $this->assertSame('0,00 EUR', Money::format(null));
        $this->assertSame('1.234.567,89', Money::format('1234567.89', 'EUR', false));
    }

    public function test_deutsche_eingabe_wird_gelesen(): void
    {
        $this->assertSame('1234.56', Money::parse('1.234,56'));
        $this->assertSame('1234.56', Money::parse('1.234,56 EUR'));
        $this->assertSame('1234.00', Money::parse('1234'));
        $this->assertNull(Money::parse(''));
        $this->assertNull(Money::parse('keine Zahl'));
    }

    public function test_tausendertrenner_ohne_dezimalstellen_wird_richtig_gelesen(): void
    {
        /*
         * Der schwerste Fehler an dieser Stelle: "25.000" ergab 25,00 EUR,
         * also einen um den Faktor 1000 falschen Betrag. In einer deutschen
         * Oberflaeche bedeutet die Eingabe fuenfundzwanzigtausend.
         */
        $this->assertSame('25000.00', Money::parse('25.000'));
        $this->assertSame('1234567.00', Money::parse('1.234.567'));
        $this->assertSame('1234.00', Money::parse('1.234'));
        $this->assertSame('-1500.50', Money::parse('-1.500,50'));
    }

    public function test_punkt_bleibt_dezimalzeichen_wenn_keine_tausendergruppierung_vorliegt(): void
    {
        $this->assertSame('25.50', Money::parse('25.5'));
        $this->assertSame('1000.00', Money::parse('1000.00'));
        $this->assertSame('0.01', Money::parse('0,01'));
    }

    public function test_zu_viele_nachkommastellen_werden_abgewiesen_statt_gekuerzt(): void
    {
        /*
         * Vorher wurde stillschweigend gekuerzt: aus 12,3456 wurde 12,34.
         * Eine Ablehnung fuehrt in den Formularen zu einer Fehlermeldung, ein
         * gekuerzter Betrag stand dagegen unbemerkt in den Buechern.
         */
        $this->assertNull(Money::parse('12.3456'));
        $this->assertNull(Money::parse('12,3456'));
        $this->assertNull(Money::parse('1.234,5678'));

        // Mit der Genauigkeit des Zielfeldes ist derselbe Wert zulaessig.
        $this->assertSame('12.3456', Money::parse('12,3456', 4));
        $this->assertSame('33.333333', Money::parse('33,333333', 6));
        $this->assertNull(Money::parse('33,3333333', 6));
    }

    public function test_nicht_deutbare_eingaben_werden_abgewiesen(): void
    {
        foreach (['abc', '12,34,56', '1.23.456', '25.000,-', '--5', '1.2.3'] as $eingabe) {
            $this->assertNull(Money::parse($eingabe),
                'Die Eingabe "'.$eingabe.'" darf keinen Betrag ergeben.');
        }
    }

    public function test_prozentsatz_liest_den_punkt_als_dezimalzeichen(): void
    {
        /*
         * Andere Regel als bei Betraegen, und das ist beabsichtigt: "3.125"
         * ist ein Zinssatz von 3,125 Prozent, nicht dreitausend.
         */
        $this->assertSame('3.125', Money::parsePercent('3.125'));
        $this->assertSame('3.125', Money::parsePercent('3,125'));
        $this->assertSame('6.5', Money::parsePercent('6,5 %'));
        $this->assertSame('33.333333', Money::parsePercent('33,333333'));
        $this->assertNull(Money::parsePercent('33,3333333'));
        $this->assertNull(Money::parsePercent('keine Zahl'));
    }

    public function test_vergleiche_und_summe(): void
    {
        $this->assertSame(1, Money::cmp('100.01', '100.00'));
        $this->assertSame(0, Money::cmp('100.00', '100.00'));
        $this->assertSame(-1, Money::cmp('99.99', '100.00'));
        $this->assertTrue(Money::isZero('0.00'));
        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertTrue(Money::isNegative('-0.01'));
        $this->assertSame('600.00', Money::sum(['100.00', '200.00', '300.00']));
    }
}
