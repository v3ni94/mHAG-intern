<?php

namespace Tests\Feature\Loans;

use App\Enums\BookingType;
use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Services\Loans\InterestCapitalizationService;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Zinskapitalisierung: Zuschreibung fälliger Zinsen auf den valutierten
 * Betrag (Anforderung vom 30.08.2026).
 *
 * Grundlage aller Erwartungswerte: 100.000,00 EUR valutiert am 01.01.2026,
 * 6 % p. a., ACT/365, monatliche Zinsfälligkeit, Systemtag 15.04.2026.
 * Fällig sind damit die Perioden Januar, Februar und März.
 *
 * Handrechnung mit Zinseszins (Zuschreibung wirkt zum Fälligkeitstag):
 *   Januar  01.01. bis 31.01. = 31 Tage auf 100.000,00 = 509,589... = 509,59
 *           Kapital danach 100.509,59
 *   Februar 01.02. bis 28.02. = 28 Tage auf 100.509,59 = 462,619... = 462,62
 *           Kapital danach 100.972,21
 *   März    01.03. bis 31.03. = 31 Tage auf 100.972,21 = 514,543... = 514,54
 *           Kapital danach 101.486,75
 *   Summe der Zuschreibungen 1.486,75
 */
class EngineInterestCapitalizationTest extends EngineTestCase
{
    private const ERWARTET = [
        ['2026-01-31', '509.59'],
        ['2026-02-28', '462.62'],
        ['2026-03-31', '514.54'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:00:00'));
    }

    private function makeCapitalizingLoan(array $overrides = []): Loan
    {
        $loan = $this->makeLoan(array_merge([
            'contract_end' => '2026-12-31',
            'interest_capitalization' => true,
        ], $overrides));
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');

        return $loan->fresh();
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function capitalizations(Loan $loan): array
    {
        return LoanTransaction::where('loan_id', $loan->id)
            ->where('booking_type', BookingType::InterestCapitalization->value)
            ->orderBy('effective_date')
            ->get()
            ->map(fn (LoanTransaction $t) => [$t->effective_date->toDateString(), Money::normalize($t->amount)])
            ->all();
    }

    public function test_ohne_einstellung_wird_nichts_zugeschrieben(): void
    {
        $loan = $this->makeCapitalizingLoan(['interest_capitalization' => false]);
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $this->assertSame([], $this->capitalizations($loan));
        $this->assertSame(
            0,
            $loan->repaymentPlanItems()->where('status', RepaymentItemStatus::Capitalized->value)->count(),
        );
        // Unveraendertes Verhalten: einfache Zinsen ohne Zinseszins
        $januar = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-01-31')->firstOrFail();
        $februar = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-02-28')->firstOrFail();
        $this->assertSame('509.59', $januar->planned_amount);
        $this->assertSame('460.27', $februar->planned_amount, '28 Tage auf 100.000,00 EUR ohne Zinseszins');
    }

    public function test_faellige_perioden_werden_mit_zinseszins_zugeschrieben(): void
    {
        $loan = $this->makeCapitalizingLoan();
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $this->assertSame(self::ERWARTET, $this->capitalizations($loan));

        // Die Planzeilen tragen den Status "Dem Kapital zugeschrieben"
        foreach (self::ERWARTET as [$due, $amount]) {
            $item = $loan->repaymentPlanItems()->where('item_type', 'interest')
                ->whereDate('due_date', $due)->firstOrFail();
            $this->assertSame(RepaymentItemStatus::Capitalized, $item->status, 'Faelligkeit '.$due);
            $this->assertSame($amount, $item->planned_amount, 'Faelligkeit '.$due);
            $this->assertNull($item->actual_amount, 'Eine Zuschreibung ist keine Zahlung.');
        }

        $salden = app(LoanBalanceService::class)->balances($loan->fresh(), Carbon::parse('2026-04-15'));
        $this->assertSame('100000.00', $salden['disbursed']);
        $this->assertSame('1486.75', $salden['capitalized']);
        $this->assertSame('101486.75', $salden['principal_outstanding']);
        $this->assertSame('1486.75', $salden['interest_capitalized']);
    }

    public function test_zukuenftige_perioden_werden_nicht_zugeschrieben(): void
    {
        $loan = $this->makeCapitalizingLoan();
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        // Systemtag 15.04.2026: die Aprilzinsen sind erst am 30.04. faellig.
        $april = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-04-30')->firstOrFail();
        $this->assertSame(RepaymentItemStatus::Planned, $april->status);

        $this->assertCount(3, $this->capitalizations($loan));
    }

    public function test_zugeschriebene_zinsen_sind_keine_offene_forderung(): void
    {
        $loan = $this->makeCapitalizingLoan();
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $salden = app(LoanBalanceService::class)->balances($loan->fresh(), Carbon::parse('2026-04-15'));

        $this->assertSame('0.00', $salden['interest_open'], 'Zugeschriebene Zinsen sind nicht offen.');
        $this->assertSame('0.00', $salden['overdue_amount'], 'Zugeschriebene Zinsen sind nicht ueberfaellig.');
        $this->assertSame('0.00', $salden['interest_confirmed']);
        $this->assertSame('0.00', $salden['interest_assumed'], 'Eine Zuschreibung ist keine angenommene Zahlung.');
        // Forderung = erhoehtes Kapital, ohne Doppelzaehlung der Zinsen
        $this->assertSame('101486.75', $salden['total_receivable']);
    }

    public function test_forderungsaufstellung_zaehlt_nicht_doppelt(): void
    {
        $loan = $this->makeCapitalizingLoan();
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $aufstellung = app(LoanBalanceService::class)
            ->statementRows($loan->fresh(), Carbon::parse('2026-04-15'));

        $summe = '0.00';
        foreach ($aufstellung['rows'] as $row) {
            $summe = $row['sign'] === '-'
                ? Money::sub($summe, $row['amount'])
                : Money::add($summe, $row['amount']);
        }

        $this->assertSame(
            $aufstellung['total'],
            $summe,
            'Die Summe der ausgewiesenen Zeilen muss die Gesamtforderung ergeben.',
        );
        $this->assertSame('101486.75', $aufstellung['total']);

        $bezeichnungen = array_column($aufstellung['rows'], 'label');
        $this->assertContains('Zugeschriebene Zinsen im valutierten Betrag', $bezeichnungen);
        $this->assertContains('Kapitalisierte Zinsen, bereits im valutierten Betrag enthalten', $bezeichnungen);
    }

    public function test_kapitalverlauf_ist_stichtagsfaehig(): void
    {
        $loan = $this->makeCapitalizingLoan();
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');
        $balance = app(LoanBalanceService::class);

        // Am 30.01. ist noch nichts zugeschrieben, am 31.01. die Januarzinsen.
        $this->assertSame(
            '100000.00',
            $balance->balances($loan->fresh(), Carbon::parse('2026-01-30'))['principal_outstanding'],
        );
        $this->assertSame(
            '100509.59',
            $balance->balances($loan->fresh(), Carbon::parse('2026-01-31'))['principal_outstanding'],
        );
        $this->assertSame(
            '100972.21',
            $balance->balances($loan->fresh(), Carbon::parse('2026-02-28'))['principal_outstanding'],
        );
    }

    public function test_wirkungsdatum_der_umstellung_wird_beachtet(): void
    {
        // Umstellung ab 01.03.2026: Januar und Februar bleiben unveraendert,
        // erst die Maerzzinsen werden zugeschrieben.
        $loan = $this->makeCapitalizingLoan(['interest_capitalization_from' => '2026-03-01']);
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        // Maerz rechnet ohne Zinseszins, weil vorher nichts zugeschrieben wurde:
        // 31 Tage auf 100.000,00 EUR = 509,59 EUR.
        $this->assertSame([['2026-03-31', '509.59']], $this->capitalizations($loan));

        $januar = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-01-31')->firstOrFail();
        $this->assertSame(RepaymentItemStatus::Assumed, $januar->status);
        $this->assertSame('509.59', $januar->planned_amount);

        $maerz = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-03-31')->firstOrFail();
        $this->assertSame(RepaymentItemStatus::Capitalized, $maerz->status);
    }

    public function test_bestaetigte_zinszahlung_wird_nicht_zugeschrieben(): void
    {
        $loan = $this->makeCapitalizingLoan();

        // Januarzinsen zuerst als bestaetigt bezahlt erfassen
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup_1');
        $januar = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-01-31')->firstOrFail();

        // Nur zur Sicherheit: der erste Durchlauf hat bereits zugeschrieben,
        // deshalb wird die Zeile hier bewusst zurueckgesetzt, um den Fall
        // "IST erfasst, bevor kapitalisiert wurde" abzubilden.
        LoanTransaction::where('loan_id', $loan->id)
            ->where('booking_type', BookingType::InterestCapitalization->value)
            ->delete();
        $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-01-31')
            ->update([
                'planned_amount' => '509.59',
                'actual_amount' => '509.59',
                'actual_date' => '2026-01-31',
                'status' => RepaymentItemStatus::Confirmed->value,
                'origin' => PaymentOrigin::ManualConfirmed->value,
            ]);
        $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '>', '2026-01-31')
            ->update(['status' => RepaymentItemStatus::Planned->value, 'actual_amount' => null]);

        $ergebnis = app(InterestCapitalizationService::class)->process($loan->fresh());

        $faelligkeiten = array_column($ergebnis['periods'], 'due');
        $this->assertNotContains('2026-01-31', $faelligkeiten, 'Eine bestaetigte Zahlung bleibt unberuehrt.');
        $this->assertSame(
            RepaymentItemStatus::Confirmed,
            $januar->fresh()->status,
        );
        // Februar rechnet danach auf unveraendertem Kapital: 28 Tage auf
        // 100.000,00 EUR = 460,27 EUR.
        $this->assertContains(['2026-02-28', '460.27'], $this->capitalizations($loan));
    }

    public function test_erneuter_durchlauf_bucht_nicht_doppelt(): void
    {
        $loan = $this->makeCapitalizingLoan();
        $recalc = app(LoanRecalculationService::class);

        $recalc->recalculate($loan, 'test_setup');
        $nachErstem = $this->capitalizations($loan);

        $recalc->recalculate($loan->fresh(), 'test_zweiter_lauf');
        $recalc->recalculate($loan->fresh(), 'test_dritter_lauf');

        $this->assertSame($nachErstem, $this->capitalizations($loan));
        $this->assertSame(
            '101486.75',
            app(LoanBalanceService::class)
                ->balances($loan->fresh(), Carbon::parse('2026-04-15'))['principal_outstanding'],
        );
    }

    public function test_abschalten_laesst_bestehende_zuschreibungen_stehen(): void
    {
        $loan = $this->makeCapitalizingLoan();
        $recalc = app(LoanRecalculationService::class);
        $recalc->recalculate($loan, 'test_setup');

        $loan->update(['interest_capitalization' => false]);
        $recalc->recalculate($loan->fresh(), 'test_abgeschaltet');

        // Append-only: gebuchte Zuschreibungen bleiben erhalten.
        $this->assertSame(self::ERWARTET, $this->capitalizations($loan));

        // Die Aprilzinsen werden nun als Forderung erwartet, gerechnet auf dem
        // erhoehten Kapital: 30 Tage auf 101.486,75 EUR
        // = 101.486,75 * 6 / 100 * 30 / 365 = 500,4826... = 500,48 EUR.
        $april = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-04-30')->firstOrFail();
        $this->assertSame('500.48', $april->planned_amount);
        $this->assertSame(RepaymentItemStatus::Planned, $april->status);
    }
}
