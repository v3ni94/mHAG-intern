<?php

namespace Tests\Feature\Loans;

use App\Enums\RepaymentItemStatus;
use App\Models\RepaymentPlanItem;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Support\Carbon;

/**
 * Eine Wahrheit je Frage.
 *
 * Befund vom 30.08.2026: Für dieselbe Zahlungsplan-Position nannten zwei
 * Stellen verschiedene Geldbeträge. RepaymentPlanItem::openAmount() behandelte
 * den Status "Geplant" als nicht erfüllt und wies den vollen Sollbetrag als
 * offen aus. LoanBalanceService führte "Geplant" dagegen ausdrücklich als
 * planmäßig erfüllt und meldete 0,00. Beide Zahlen erschienen gleichzeitig auf
 * dem Bildschirm: der Darlehensreiter zeigte den vollen Betrag, die Kennzahl
 * "Offene Zinsen" daneben 0,00.
 *
 * Der Zustand war der Regelfall, nicht die Ausnahme: Positionen entstehen als
 * "Geplant" und werden nur durch eine Neuberechnung fortgeschrieben; einen
 * täglichen Lauf dafür gibt es nicht.
 *
 * Auflösung: Zwei Fragen, zwei Namen.
 *   openAmount()     Was führen die Bücher als offen? Eine angenommene
 *                    Erfüllung gilt als Erfüllung.
 *   expectedAmount() Welcher Betrag muss noch tatsächlich fließen? Eine
 *                    angenommene Erfüllung ist kein Geldeingang.
 */
class EngineOpenAmountConsistencyTest extends EngineTestCase
{
    private LoanBalanceService $balance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));
        $this->balance = app(LoanBalanceService::class);
    }

    public function test_geplante_position_gilt_in_den_buechern_als_erfuellt(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        // Eine faellige Zinsposition ausdruecklich auf "Geplant" zuruecksetzen,
        // wie sie ohne Fortschreibung dasteht.
        $position = $loan->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->whereDate('due_date', '<=', '2026-06-15')
            ->orderBy('due_date')
            ->firstOrFail();
        $position->update(['status' => RepaymentItemStatus::Planned, 'actual_amount' => null]);
        $position = $position->fresh();

        $this->assertTrue($position->status->giltAlsErfuelltDurchAnnahme());

        // Buchsicht: nichts offen.
        $this->assertSame('0.00', $position->openAmount(),
            'Die Buchsicht muss dieselbe sein wie im LoanBalanceService.');

        // Planungssicht: der Sollbetrag muss noch fliessen.
        $this->assertSame(
            \App\Support\Money::normalize($position->planned_amount),
            $position->expectedAmount(),
            'Fuer die Planung ist eine angenommene Erfuellung kein Geldeingang.',
        );
    }

    public function test_buchsicht_der_position_stimmt_mit_der_forderungsaufstellung_ueberein(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $loan->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->update(['status' => RepaymentItemStatus::Planned->value, 'actual_amount' => null]);

        $salden = $this->balance->balances($loan->fresh(), Carbon::parse('2026-06-15'));

        $summeAusPositionen = '0.00';
        foreach ($loan->fresh()->repaymentPlanItems()->where('item_type', 'interest')->get() as $item) {
            $summeAusPositionen = \App\Support\Money::add($summeAusPositionen, $item->openAmount());
        }

        $this->assertSame($salden['interest_open'], $summeAusPositionen,
            'Die Summe der offenen Positionen muss der Kennzahl "Offene Zinsen" entsprechen. '
            .'Zwei Zahlen fuer denselben Sachverhalt sind ein Fehler, nicht eine Sichtweise.');
    }

    public function test_nicht_bezahlte_position_ist_in_beiden_sichten_offen(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $position = $loan->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->orderBy('due_date')
            ->firstOrFail();
        $position->update([
            'status' => RepaymentItemStatus::Missed,
            'actual_amount' => '0.00',
        ]);
        $position = $position->fresh();

        $soll = \App\Support\Money::normalize($position->planned_amount);
        $this->assertSame($soll, $position->openAmount());
        $this->assertSame($soll, $position->expectedAmount());
    }

    public function test_erlassene_und_stornierte_positionen_schulden_nichts(): void
    {
        // Zuvor wies openAmount() auch fuer erlassene und stornierte
        // Positionen den vollen Sollbetrag als offen aus.
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        foreach ([RepaymentItemStatus::Waived, RepaymentItemStatus::Cancelled, RepaymentItemStatus::Capitalized] as $status) {
            $position = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->firstOrFail();
            $position->update(['status' => $status, 'actual_amount' => null]);
            $position = $position->fresh();

            $this->assertSame('0.00', $position->openAmount(), 'Status '.$status->value);
            $this->assertSame('0.00', $position->expectedAmount(), 'Status '.$status->value);
        }
    }

    public function test_teilzahlung_wird_in_beiden_sichten_gleich_gerechnet(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $position = $loan->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->orderBy('due_date')
            ->firstOrFail();
        $haelfte = \App\Support\Money::div($position->planned_amount, '2', 2);
        $position->update(['status' => RepaymentItemStatus::Partial, 'actual_amount' => $haelfte]);
        $position = $position->fresh();

        $rest = \App\Support\Money::sub($position->planned_amount, $haelfte);
        $this->assertSame($rest, $position->openAmount());
        $this->assertSame($rest, $position->expectedAmount());
    }

    public function test_die_zuordnung_der_zustaende_liegt_nur_an_einer_stelle(): void
    {
        /*
         * Absicherung gegen einen Rueckfall: Waechst die Aufzaehlung, muss
         * jeder Zustand genau einer Gruppe angehoeren oder ausdruecklich
         * keiner. Frueher lag die Zuordnung doppelt vor, im Modell und im
         * LoanBalanceService, und war nicht deckungsgleich.
         */
        foreach (RepaymentItemStatus::cases() as $status) {
            $gruppen = 0;
            $gruppen += $status->giltAlsErfuelltDurchAnnahme() ? 1 : 0;
            $gruppen += $status->hatBestaetigtenIst() ? 1 : 0;
            $gruppen += $status->istAbgeschlossenOhneZahlung() ? 1 : 0;

            $this->assertLessThanOrEqual(1, $gruppen,
                'Der Zustand "'.$status->value.'" gehoert mehreren Gruppen an. Das ergibt '
                .'widerspruechliche Betraege.');
        }

        // Der einzige Zustand ohne Gruppe ist "Nicht bezahlt": voll offen.
        $ohneGruppe = array_values(array_filter(
            RepaymentItemStatus::cases(),
            fn (RepaymentItemStatus $s) => ! $s->giltAlsErfuelltDurchAnnahme()
                && ! $s->hatBestaetigtenIst()
                && ! $s->istAbgeschlossenOhneZahlung(),
        ));

        $this->assertSame([RepaymentItemStatus::Missed], $ohneGruppe);
    }
}
