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
