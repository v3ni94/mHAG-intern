<?php

namespace Tests\Feature\Loans;

use App\Enums\BookingType;
use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Services\Loans\DefaultInterestService;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Verzugszinsen (Masterprompt Abschnitt 44).
 *
 * Kernanforderung: Das System darf KEINEN gesetzlichen Verzugszinssatz
 * unterstellen und keinen Verzugsbeginn erfinden. Berechnet wird
 * ausschließlich mit den vom Benutzer erfassten Vorgaben.
 */
class EngineDefaultInterestTest extends EngineTestCase
{
    private DefaultInterestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Fester Systemtag: die Plangenerierung und die Statusfortschreibung
        // ("systemseitig angenommen" bis heute) müssen reproduzierbar sein.
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $this->service = app(DefaultInterestService::class);
    }

    /**
     * Darlehen mit ausgezahltem Kapital und vollständigem Zahlungsplan.
     * 100.000,00 EUR, 6 % ACT/365, monatliche Zinsen, endfällig 31.12.2026.
     */
    private function darlehenMitPlan(array $attribute = []): Loan
    {
        $loan = $this->makeLoan(array_merge([
            'contract_end' => '2026-12-31',
            'due_date' => '2026-12-31',
        ], $attribute));

        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        return $loan->fresh();
    }

    public function test_ohne_vorgaben_wird_nichts_berechnet(): void
    {
        $loan = $this->darlehenMitPlan();

        // Weder aktiviert, noch Satz, noch Verzugsbeginn erfasst
        $ergebnis = $this->service->calculate($loan, Carbon::parse('2026-12-31'));

        $this->assertFalse($ergebnis['configured']);
        $this->assertNotEmpty($ergebnis['missing'], 'Die fehlenden Vorgaben müssen benannt werden.');
        $this->assertSame('0.00', $ergebnis['amount']);
        $this->assertNull($this->service->book($loan, Carbon::parse('2026-12-31'), null));
        $this->assertSame(0, LoanTransaction::where('booking_type', BookingType::DefaultInterest->value)->count());
    }

    public function test_aktiviert_aber_ohne_satz_wird_nichts_berechnet(): void
    {
        $loan = $this->darlehenMitPlan();
        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_start' => '2026-03-01',
            'default_interest_rate' => null,
        ]);

        $ergebnis = $this->service->calculate($loan->fresh(), Carbon::parse('2026-12-31'));

        $this->assertFalse($ergebnis['configured'], 'Ohne Satz darf nichts berechnet werden.');
        $this->assertSame('0.00', $ergebnis['amount']);
    }

    public function test_aktiviert_aber_ohne_verzugsbeginn_wird_nichts_berechnet(): void
    {
        $loan = $this->darlehenMitPlan();
        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_rate' => '9.000000',
            'default_interest_start' => null,
        ]);

        $ergebnis = $this->service->calculate($loan->fresh(), Carbon::parse('2026-12-31'));

        $this->assertFalse($ergebnis['configured'], 'Ohne Verzugsbeginn darf nichts berechnet werden.');
        $this->assertSame('0.00', $ergebnis['amount']);
    }

    public function test_nicht_bezahlte_zinsposition_ergibt_taggenaue_verzugszinsen(): void
    {
        $loan = $this->darlehenMitPlan();

        // Februarzinsen als nicht bezahlt erfassen (Fälligkeit 28.02.2026)
        $item = $loan->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->orderBy('due_date')
            ->skip(1)
            ->first();
        $this->assertNotNull($item, 'Zinsposition für Februar muss vorhanden sein.');
        $faelligkeit = $item->due_date->toDateString();
        $offen = Money::normalize($item->planned_amount);

        $item->update([
            'actual_amount' => '0.00',
            'status' => RepaymentItemStatus::Missed,
            'origin' => PaymentOrigin::ManualConfirmed,
        ]);

        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_rate' => '9.000000',
            'default_interest_start' => $faelligkeit,
            'default_interest_basis' => DefaultInterestService::BASIS_OVERDUE_TOTAL,
            'default_interest_method' => 'act_365',
        ]);

        $stichtag = Carbon::parse($faelligkeit)->addDays(30);
        $ergebnis = $this->service->calculate($loan->fresh(), $stichtag);

        $this->assertTrue($ergebnis['configured']);

        /*
         * Rechenweg von Hand: überfälliger Betrag = offene Februarzinsen
         * (100.000,00 EUR * 6 % * 28/365 = 460,27 EUR).
         * Zeitraum vom Fälligkeitstag bis Stichtag einschließlich, also
         * 31 Tage (28.02.2026 bis 30.03.2026 mitgezählt).
         * Verzugszins = 460,27 * 9 / 100 * 31 / 365 = 3,5182... = 3,52 EUR.
         *
         * Bewusst mit bcmul/bcdiv gerechnet: Money::mul rundet seine
         * Operanden auf zwei Nachkommastellen und ist damit für den
         * Tageszählfaktor nicht geeignet.
         */
        $this->assertSame('460.27', $offen, 'Erwartete Soll-Zinsen für Februar.');
        $erwartet = Money::round(
            bcmul(bcdiv(bcmul($offen, '9', 10), '100', 10), bcdiv('31', '365', 10), 10),
            2,
        );
        $this->assertSame('3.52', $erwartet, 'Handrechnung: 460,27 * 9 % * 31/365.');

        $this->assertSame(
            $erwartet,
            $ergebnis['amount'],
            'Verzugszinsen müssen taggenau auf den überfälligen Betrag gerechnet werden.',
        );
        $this->assertNotEmpty($ergebnis['segments'], 'Die Segmentierung muss nachvollziehbar sein.');
    }

    public function test_systemseitig_angenommene_position_ist_kein_verzug(): void
    {
        $loan = $this->darlehenMitPlan();

        // Keine Abweichung erfasst: planmäßige Erfüllung gilt als angenommen
        // (Masterprompt Abschnitt 24) und darf nie Verzug auslösen.
        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_rate' => '9.000000',
            'default_interest_start' => '2026-02-01',
        ]);

        $ergebnis = $this->service->calculate($loan->fresh(), Carbon::parse('2026-06-30'));

        $this->assertTrue($ergebnis['configured']);
        $this->assertSame(
            '0.00',
            $ergebnis['amount'],
            'Systemseitig angenommene Zahlungen sind kein Verzug.',
        );
    }

    public function test_buchung_erscheint_im_konto_und_in_den_salden(): void
    {
        $loan = $this->darlehenMitPlan();

        $item = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->first();
        $item->update([
            'actual_amount' => '0.00',
            'status' => RepaymentItemStatus::Missed,
            'origin' => PaymentOrigin::ManualConfirmed,
        ]);

        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_rate' => '9.000000',
            'default_interest_start' => $item->due_date->toDateString(),
        ]);
        $loan = $loan->fresh();

        $stichtag = Carbon::parse($item->due_date)->addDays(60);
        $buchung = $this->service->book($loan, $stichtag, null);

        $this->assertNotNull($buchung, 'Bei erfassten Vorgaben muss gebucht werden.');
        $this->assertSame(BookingType::DefaultInterest, $buchung->booking_type);
        $this->assertTrue(Money::isPositive($buchung->amount));

        $salden = app(LoanBalanceService::class)->balances($loan->fresh(), $stichtag);
        $this->assertSame(
            Money::normalize($buchung->amount),
            Money::normalize($salden['default_interest']),
            'Die Buchung muss in den Salden erscheinen.',
        );

        // Forderungsaufstellung weist die Zeile aus
        $aufstellung = app(LoanBalanceService::class)->statementRows($loan->fresh(), $stichtag);
        $bezeichnungen = array_column($aufstellung['rows'], 'label');
        $this->assertContains('Verzugszinsen', $bezeichnungen);
    }

    public function test_ohne_verzugszinsen_keine_nullzeile_in_der_aufstellung(): void
    {
        // Masterprompt Abschnitt 143: keine Schein-Funktion. Ist die Funktion
        // nicht aktiviert, darf keine Zeile mit 0,00 EUR erscheinen.
        $loan = $this->darlehenMitPlan();

        $aufstellung = app(LoanBalanceService::class)->statementRows($loan, Carbon::parse('2026-06-30'));
        $bezeichnungen = array_column($aufstellung['rows'], 'label');

        $this->assertNotEmpty($bezeichnungen, 'Die Aufstellung muss Zeilen enthalten.');
        $this->assertNotContains('Verzugszinsen', $bezeichnungen);
    }

    public function test_erneute_buchung_storniert_die_eigene_vorbuchung(): void
    {
        $loan = $this->darlehenMitPlan();

        $item = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->first();
        $item->update([
            'actual_amount' => '0.00',
            'status' => RepaymentItemStatus::Missed,
            'origin' => PaymentOrigin::ManualConfirmed,
        ]);
        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_rate' => '9.000000',
            'default_interest_start' => $item->due_date->toDateString(),
        ]);
        $loan = $loan->fresh();

        $ersteBuchung = $this->service->book($loan, Carbon::parse($item->due_date)->addDays(30), null);
        $this->assertNotNull($ersteBuchung);

        // Späterer Stichtag: höherer Betrag, die alte Buchung wird per
        // Gegenbuchung aufgehoben, nicht gelöscht (Abschnitt 49).
        $zweiteBuchung = $this->service->book($loan->fresh(), Carbon::parse($item->due_date)->addDays(90), null);
        $this->assertNotNull($zweiteBuchung);
        $this->assertTrue(
            Money::cmp($zweiteBuchung->amount, $ersteBuchung->amount) > 0,
            'Der längere Verzugszeitraum muss zu einem höheren Betrag führen.',
        );

        $this->assertDatabaseHas('loan_transactions', ['reversal_of' => $ersteBuchung->id]);
        $this->assertDatabaseHas('loan_transactions', ['id' => $ersteBuchung->id]);

        // Saldo enthält nur den aktuellen Betrag, nicht die Summe beider
        $salden = app(LoanBalanceService::class)->balances(
            $loan->fresh(),
            Carbon::parse($item->due_date)->addDays(90),
        );
        $this->assertSame(
            Money::normalize($zweiteBuchung->amount),
            Money::normalize($salden['default_interest']),
            'Es darf keine Doppelzählung entstehen.',
        );
    }

    public function test_unveraenderter_betrag_erzeugt_keine_zweite_buchung(): void
    {
        $loan = $this->darlehenMitPlan();

        $item = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->first();
        $item->update([
            'actual_amount' => '0.00',
            'status' => RepaymentItemStatus::Missed,
            'origin' => PaymentOrigin::ManualConfirmed,
        ]);
        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_rate' => '9.000000',
            'default_interest_start' => $item->due_date->toDateString(),
        ]);
        $loan = $loan->fresh();

        $stichtag = Carbon::parse($item->due_date)->addDays(30);
        $this->service->book($loan, $stichtag, null);
        $anzahlNachErster = LoanTransaction::where('loan_id', $loan->id)
            ->where('booking_type', BookingType::DefaultInterest->value)
            ->count();

        $this->assertNull(
            $this->service->book($loan->fresh(), $stichtag, null),
            'Gleicher Stichtag und gleicher Betrag: keine erneute Buchung.',
        );
        $this->assertSame(
            $anzahlNachErster,
            LoanTransaction::where('loan_id', $loan->id)
                ->where('booking_type', BookingType::DefaultInterest->value)
                ->count(),
        );
    }

    public function test_verzugsbeginn_nach_stichtag_ergibt_null(): void
    {
        $loan = $this->darlehenMitPlan();
        $loan->update([
            'default_interest_enabled' => true,
            'default_interest_rate' => '9.000000',
            'default_interest_start' => '2026-09-01',
        ]);

        $ergebnis = $this->service->calculate($loan->fresh(), Carbon::parse('2026-06-30'));

        $this->assertSame('0.00', $ergebnis['amount']);
    }
}
