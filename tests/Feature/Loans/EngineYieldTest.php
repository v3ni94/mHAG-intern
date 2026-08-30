<?php

namespace Tests\Feature\Loans;

use App\Services\Loans\LoanRecalculationService;
use App\Services\Loans\LoanYieldService;
use App\Services\Loans\PaymentAllocationService;
use Illuminate\Support\Carbon;

/**
 * Ertrag und Rendite (Anforderung vom 30.08.2026).
 *
 * Grundlage: 100.000,00 EUR, 6 % p. a., ACT/365, monatliche Zinsfälligkeit,
 * Wirkungsbeginn 01.01.2026, Vertragsende 31.12.2026.
 * Ein Zinstag kostet 100.000,00 * 6 / 100 / 365 = 16,438356164... EUR.
 */
class EngineYieldTest extends EngineTestCase
{
    private LoanYieldService $yield;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2027-01-02 09:00:00'));
        $this->yield = app(LoanYieldService::class);
    }

    public function test_rendite_entspricht_dem_nominalzins_bei_planmaessiger_erfuellung(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $ergebnis = $this->yield->analyse($loan->fresh(), Carbon::parse('2026-12-31'));

        /*
         * Summe der zwölf Soll-Zinszeilen 2026:
         *   7 Monate mit 31 Tagen  * 509,59 = 3.567,13
         *   4 Monate mit 30 Tagen  * 493,15 = 1.972,60
         *   1 Monat  mit 28 Tagen  *        =   460,27
         *   Summe                            = 6.000,00
         * Alle Zeilen sind systemseitig angenommen, also nicht bestätigt.
         */
        $this->assertSame('0.00', $ergebnis['interest_confirmed']);
        $this->assertSame('6000.00', $ergebnis['interest_assumed']);
        $this->assertSame('0.00', $ergebnis['interest_capitalized']);
        $this->assertSame('0.00', $ergebnis['yield_confirmed'], 'Ohne bestätigte Zahlung ist kein Ertrag belegt.');
        $this->assertSame('6000.00', $ergebnis['yield_total']);

        // Kapital 100.000,00 EUR über 365 Tage: Mittelwert = 100.000,00 EUR
        $this->assertSame('2026-01-01', $ergebnis['period_from']);
        $this->assertSame(365, $ergebnis['period_days']);
        $this->assertSame('100000.00', $ergebnis['average_capital']);
        $this->assertSame('1.0000000000', $ergebnis['year_fraction']);

        // 6.000,00 / 100.000,00 / 1 Jahr = 6,0000 % p. a.
        $this->assertSame('0.0000', $ergebnis['return_pa']);
        $this->assertSame('6.0000', $ergebnis['return_pa_total']);
    }

    public function test_durchschnittlich_gebundenes_kapital_ist_zeitgewichtet(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->bookRepayment($loan, '50000.00', '2026-07-01');

        $ergebnis = $this->yield->analyse($loan->fresh(), Carbon::parse('2026-12-31'));

        /*
         * 01.01. bis 30.06. = 181 Tage auf 100.000,00 EUR
         * 01.07. bis 31.12. = 184 Tage auf  50.000,00 EUR
         * (100.000,00 * 181 + 50.000,00 * 184) / 365
         * = (18.100.000 + 9.200.000) / 365 = 74.794,5205... = 74.794,52 EUR
         */
        $this->assertSame(365, $ergebnis['period_days']);
        $this->assertSame('74794.52', $ergebnis['average_capital']);
    }

    public function test_effektivrendite_aus_tatsaechlichen_zahlungsstroemen(): void
    {
        // 100.000,00 EUR am 01.01.2026 ausgezahlt, am 01.01.2027 fließen
        // 106.000,00 EUR zurück. 2026 hat 365 Tage, also
        // 100.000,00 * (1 + i) = 106.000,00 und damit i = 6,0000 % p. a.
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $payment = $this->makePayment($loan, '106000.00', '2027-01-01');
        app(PaymentAllocationService::class)->allocate($payment);

        $ergebnis = $this->yield->analyse($loan->fresh(), Carbon::parse('2027-01-01'));

        $this->assertSame('0.00', $ergebnis['receivable'], 'Das Darlehen ist vollständig zurückgeführt.');
        $this->assertNull($ergebnis['irr_note']);
        $this->assertSame('6.0000', $ergebnis['irr']);

        // Zahlungsströme: eine Auszahlung negativ, ein Rückfluss positiv
        $this->assertSame('-100000.00', $ergebnis['cash_flows'][0]['amount']);
        $this->assertSame('2026-01-01', $ergebnis['cash_flows'][0]['date']);
        $rueckfluss = array_slice($ergebnis['cash_flows'], 1);
        $this->assertSame(
            '106000.00',
            \App\Support\Money::sum(array_column($rueckfluss, 'amount')),
        );
    }

    public function test_bestaetigte_zahlungen_sind_belegter_ertrag(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        // Januarzinsen tatsächlich bezahlt: 509,59 EUR
        $payment = $this->makePayment($loan, '509.59', '2026-02-05');
        app(PaymentAllocationService::class)->allocate($payment);

        $ergebnis = $this->yield->analyse($loan->fresh(), Carbon::parse('2026-02-28'));

        $this->assertSame('509.59', $ergebnis['interest_confirmed']);
        $this->assertSame('509.59', $ergebnis['yield_confirmed']);
        // Die Februarzeile ist zum 28.02. fällig und gilt als angenommen:
        // 28 Tage auf 100.000,00 EUR = 460,27 EUR.
        $this->assertSame('460.27', $ergebnis['interest_assumed']);
        $this->assertSame('969.86', $ergebnis['yield_total']);
    }

    public function test_kapitalisierte_zinsen_sind_belegter_ertrag(): void
    {
        $loan = $this->makeLoan([
            'contract_end' => '2026-12-31',
            'interest_capitalization' => true,
        ]);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:00:00'));
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $ergebnis = $this->yield->analyse($loan->fresh(), Carbon::parse('2026-04-15'));

        // Zuschreibungen Januar bis März: 509,59 + 462,62 + 514,54 = 1.486,75
        $this->assertSame('1486.75', $ergebnis['interest_capitalized']);
        $this->assertSame('1486.75', $ergebnis['yield_confirmed'], 'Eine Zuschreibung ist belegt, kein Zahlungseingang.');
        $this->assertSame('0.00', $ergebnis['interest_confirmed']);
        $this->assertSame('0.00', $ergebnis['interest_assumed'], 'Zugeschriebene Perioden sind keine angenommenen Zahlungen.');
    }

    public function test_ohne_kapital_keine_rendite_und_kein_erfundener_wert(): void
    {
        // Vertrag angelegt, aber nie ausgezahlt: es gibt kein gebundenes
        // Kapital. Statt einer Zahl wird nichts ausgewiesen.
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);

        $ergebnis = $this->yield->analyse($loan, Carbon::parse('2026-12-31'));

        $this->assertNull($ergebnis['period_from']);
        $this->assertSame(0, $ergebnis['period_days']);
        $this->assertSame('0.00', $ergebnis['average_capital']);
        $this->assertNull($ergebnis['return_pa']);
        $this->assertNull($ergebnis['return_pa_total']);
        $this->assertNull($ergebnis['irr']);
        $this->assertNotNull($ergebnis['irr_note'], 'Die Nichtberechenbarkeit muss begründet werden.');
    }

    public function test_ohne_rueckfluss_ist_die_effektivrendite_nicht_berechenbar(): void
    {
        // Auszahlung und vollständige Abschreibung: es gibt keinen positiven
        // Zahlungsstrom, also keine Effektivrendite.
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        \App\Models\LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'write_off',
            'booking_date' => '2026-06-30',
            'effective_date' => '2026-06-30',
            'amount' => '-100000.00',
            'description' => 'Test: Abschreibung',
        ]);

        $ergebnis = $this->yield->analyse($loan->fresh(), Carbon::parse('2026-12-31'));

        $this->assertSame('0.00', $ergebnis['receivable']);
        $this->assertNull($ergebnis['irr']);
        $this->assertNotNull($ergebnis['irr_note']);
    }

    public function test_zinszuschreibung_ist_kein_zahlungsstrom(): void
    {
        // Eine Zuschreibung bewegt kein Geld: sie darf nicht als Rückfluss
        // in die Effektivrendite eingehen, sondern wirkt über die
        // Restforderung.
        $loan = $this->makeLoan([
            'contract_end' => '2026-12-31',
            'interest_capitalization' => true,
        ]);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:00:00'));
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $ergebnis = $this->yield->analyse($loan->fresh(), Carbon::parse('2026-04-15'));

        $bezeichnungen = array_column($ergebnis['cash_flows'], 'label');
        $this->assertNotContains('Zinszuschreibung', $bezeichnungen);
        $this->assertContains('Auszahlung', $bezeichnungen);
        $this->assertContains('Restforderung zum Stichtag', $bezeichnungen);
        $this->assertSame('101486.75', $ergebnis['receivable']);
    }
}
