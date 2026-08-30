<?php

namespace Tests\Feature\Loans;

use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Services\Loans\DisbursementService;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use App\Services\Loans\PaymentAllocationService;
use Illuminate\Support\Carbon;

/**
 * Recalculation Engine (Abschnitte 33, 35-38): rueckwirkende Vertragserfassung,
 * Neuberechnung ueber mehrere Jahre, ausgefallene und Teil-Tilgung mit
 * Kapitalwirkung auf kuenftige Zinsen, Determinismus, Protokoll.
 */
class EngineRecalculationTest extends EngineTestCase
{
    private DisbursementService $disbursement;

    private LoanRecalculationService $recalc;

    private LoanBalanceService $balance;

    private PaymentAllocationService $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disbursement = app(DisbursementService::class);
        $this->recalc = app(LoanRecalculationService::class);
        $this->balance = app(LoanBalanceService::class);
        $this->allocation = app(PaymentAllocationService::class);
    }

    /**
     * Rueckwirkende Vertragserfassung: erfasst am 30.08.2026,
     * Vertragsbeginn 01.01.2024, unbefristet, 100.000 EUR, 5 %, ACT/365,
     * monatliche Zinsen. Auszahlung geplant zum 01.01.2024.
     */
    private function makeRetroactiveLoan(): Loan
    {
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $loan = $this->makeLoan(
            ['effective_from' => '2024-01-01', 'repayment_model' => 'open_ended'],
            [['rate' => '5.000000', 'valid_from' => '2024-01-01']],
        );
        $this->disbursement->plan($loan, ['planned_amount' => '100000.00', 'planned_date' => '2024-01-01']);

        return $loan;
    }

    public function test_retroactive_contract_builds_full_history(): void
    {
        // Abschnitt 33: kompletter Verlauf ab 01.01.2024 wird automatisch berechnet.
        //
        // Zinszeilen (rollierender Horizont bis 30.08.2027, volle Monate):
        // Januar 2024 bis Juli 2027 = 43 Zeilen; davon faellig bis 30.08.2026
        // (Jan 2024 - Jul 2026) = 31 systemseitig angenommen, 12 geplant.
        //
        // Handrechnung je Monat (5 % auf 100.000, ACT/365):
        //   31 Tage: 5000*31/365 = 424,66   30 Tage: 5000*30/365 = 410,96
        //   29 Tage: 5000*29/365 = 397,26   28 Tage: 5000*28/365 = 383,56
        // Jahressummen: 2024 (Schaltjahr, 7*31+4*30+29 Tage) = 5013,72;
        // 2025 = 5000,02; Jan-Jul 2026 (4*31+2*30+28) = 2904,12.
        // Angenommene Zinsen gesamt = 5013,72 + 5000,02 + 2904,12 = 12.917,86.
        $loan = $this->makeRetroactiveLoan();

        $interest = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get();
        $this->assertCount(43, $interest);
        $this->assertSame('2024-01-31', $interest->first()->due_date->toDateString());
        $this->assertSame('2027-07-31', $interest->last()->due_date->toDateString());
        $this->assertSame('424.66', $interest[0]->planned_amount);  // Januar 2024, 31 Tage
        $this->assertSame('397.26', $interest[1]->planned_amount);  // Februar 2024, Schaltjahr: 29 Tage
        $this->assertSame(31, $interest->where('status', RepaymentItemStatus::Assumed)->count());
        $this->assertSame(12, $interest->where('status', RepaymentItemStatus::Planned)->count());

        $b = $this->balance->balances($loan);
        $this->assertSame('100000.00', $b['disbursed']);
        $this->assertSame('100000.00', $b['principal_outstanding']);
        $this->assertSame('12917.86', $b['interest_charged']);
        $this->assertSame('12917.86', $b['interest_assumed']);
        $this->assertSame('0.00', $b['interest_open']);
        $this->assertSame('100000.00', $b['total_receivable']);
    }

    public function test_missed_interest_recalculated_across_years(): void
    {
        // Abschnitt 36: Heute 30.08.2026 wird erfasst "Zinszahlung Maerz 2025
        // nicht erfolgt" -> Folgewerte werden automatisch neu berechnet.
        $loan = $this->makeRetroactiveLoan();

        $march2025 = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2025-03-31')->first();
        $march2025->update(['status' => RepaymentItemStatus::Missed, 'actual_amount' => '0.00', 'origin' => 'manual_entered']);

        $record = $this->recalc->recalculate($loan, 'interest_marked_missed', Carbon::parse('2025-03-01'));

        // Protokoll (Abschnitt 38): Ausloeser, fruehestes Datum, alter/neuer Stand.
        // Hinweis: Der Alt-Snapshot wird nach der IST-Erfassung gezogen; die
        // offene Position ist darin bereits sichtbar.
        $this->assertSame('ok', $record->status);
        $this->assertSame('interest_marked_missed', $record->trigger_action);
        $this->assertSame('2025-03-01', $record->earliest_affected_date->toDateString());
        $this->assertSame('424.66', $record->new_state['interest_open']);
        $this->assertNotNull($record->duration_ms);

        // Alt/Neu-Differenz am Erstprotokoll der rueckwirkenden Erfassung:
        // vor der ersten Neuberechnung existierten keine Zins-SOLL-Zeilen.
        $initial = $loan->recalculations()->where('trigger_action', 'disbursement_planned')->first();
        $this->assertSame('0.00', $initial->old_state['interest_charged']);
        $this->assertSame('12917.86', $initial->new_state['interest_charged']);

        // Maerz 2025 (31 Tage, 424,66) offen und ueberfaellig; Annahmen sinken entsprechend:
        // 12.917,86 - 424,66 = 12.493,20; Forderung = 100.000 + 424,66.
        $b = $this->balance->balances($loan);
        $this->assertSame('424.66', $b['interest_open']);
        $this->assertSame('424.66', $b['overdue_amount']);
        $this->assertSame('12493.20', $b['interest_assumed']);
        $this->assertSame('100424.66', $b['total_receivable']);

        // Die IST-Zeile wurde von der Neuberechnung nicht angefasst.
        $march2025->refresh();
        $this->assertSame(RepaymentItemStatus::Missed, $march2025->status);
        $this->assertSame('424.66', $march2025->planned_amount);
    }

    public function test_recalculation_is_deterministic(): void
    {
        // Abschnitt 37: gleiche Eingangsdaten -> identisches Ergebnis.
        $loan = $this->makeRetroactiveLoan();

        $first = $this->recalc->recalculate($loan, 'test_run');
        $countAfterFirst = $loan->repaymentPlanItems()->count();
        $second = $this->recalc->recalculate($loan, 'test_run');

        $this->assertSame('ok', $first->status);
        $this->assertSame('ok', $second->status);
        $this->assertSame($first->new_state, $second->new_state);
        $this->assertSame($countAfterFirst, $loan->repaymentPlanItems()->count());
        // Keine doppelten Kapitalbuchungen durch wiederholte Neuberechnung.
        $this->assertSame(1, $loan->transactions()->count());
    }

    /**
     * Ratendarlehen fuer die Tilgungsszenarien: 100.000 EUR, 6 % ACT/365,
     * jaehrliche Perioden, Laufzeit 01.01.2026 - 31.12.2027,
     * zwei Tilgungsraten je 50.000 (31.12.2026 und 31.12.2027).
     * Betrachtung am 15.01.2028 (alles faellig).
     */
    private function makeInstallmentLoan(): Loan
    {
        Carbon::setTestNow(Carbon::parse('2028-01-15 12:00:00'));
        $loan = $this->makeLoan([
            'contract_end' => '2027-12-31',
            'interest_frequency' => 'annual',
            'repayment_model' => 'installment',
        ]);
        $this->disbursement->plan($loan, ['planned_amount' => '100000.00', 'planned_date' => '2026-01-01']);

        return $loan;
    }

    public function test_missed_principal_keeps_capital_high_and_future_interest_up(): void
    {
        // Abschnitt 30: faellt die Tilgung 31.12.2026 aus, bleibt das Kapital
        // bei 100.000 -> Zins-SOLL 2027 bleibt 100000*0,06*365/365 = 6000,00.
        $loan = $this->makeInstallmentLoan();

        $principal2026 = $loan->repaymentPlanItems()->where('item_type', 'principal')->whereDate('due_date', '2026-12-31')->first();
        $this->assertSame('50000.00', $principal2026->planned_amount);
        $principal2026->update(['status' => RepaymentItemStatus::Missed, 'actual_amount' => '0.00', 'origin' => 'manual_entered']);

        $this->recalc->recalculate($loan, 'principal_marked_missed', Carbon::parse('2026-12-31'));

        $interest2027 = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2027-12-31')->first();
        $this->assertSame('6000.00', $interest2027->planned_amount);

        $b = $this->balance->balances($loan);
        $this->assertSame('100000.00', $b['principal_outstanding']); // Kapital bleibt hoch
        $this->assertSame('50000.00', $b['overdue_amount']);
        $this->assertSame('0.00', $b['repaid']);
    }

    public function test_paid_principal_reduces_capital_and_future_interest(): void
    {
        // Gegenprobe: Tilgung 50.000 tatsaechlich am 31.12.2026 gezahlt.
        // Kapitalverlauf: 100.000 bis 30.12.2026, ab 31.12.2026 50.000.
        // Zins-SOLL 2026: 100000*0,06*364/365 + 50000*0,06*1/365
        //   = (2.184.000 + 3.000)/365 = 5991,78.
        // Zins-SOLL 2027: 50000*0,06*365/365 = 3000,00.
        $loan = $this->makeInstallmentLoan();

        $payment = $this->makePayment($loan, '50000.00', '2026-12-31');
        $this->allocation->allocate($payment, ['principal' => '50000.00']);
        $this->recalc->recalculate($loan, 'payment_recorded', Carbon::parse('2026-12-31'));

        $principal2026 = $loan->repaymentPlanItems()->where('item_type', 'principal')->whereDate('due_date', '2026-12-31')->first();
        $this->assertSame(RepaymentItemStatus::Confirmed, $principal2026->status); // puenktlich
        $this->assertSame('50000.00', $principal2026->actual_amount);

        $interest2026 = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-12-31')->first();
        $this->assertSame('5991.78', $interest2026->planned_amount);
        $interest2027 = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2027-12-31')->first();
        $this->assertSame('3000.00', $interest2027->planned_amount);

        $b = $this->balance->balances($loan);
        $this->assertSame('50000.00', $b['principal_outstanding']);
        $this->assertSame('50000.00', $b['repaid']);
        $this->assertSame('0.00', $b['overdue_amount']);
    }

    public function test_partial_principal_payment(): void
    {
        // Teiltilgung: 20.000 von 50.000 am 31.12.2026 -> Zeile teilweise,
        // Kapital 80.000 ab 31.12.2026, Zins-SOLL 2027 = 80000*0,06 = 4800,00.
        $loan = $this->makeInstallmentLoan();

        $payment = $this->makePayment($loan, '20000.00', '2026-12-31');
        $this->allocation->allocate($payment, ['principal' => '20000.00']);
        $this->recalc->recalculate($loan, 'payment_recorded', Carbon::parse('2026-12-31'));

        $principal2026 = $loan->repaymentPlanItems()->where('item_type', 'principal')->whereDate('due_date', '2026-12-31')->first();
        $this->assertSame(RepaymentItemStatus::Partial, $principal2026->status);
        $this->assertSame('20000.00', $principal2026->actual_amount);
        $this->assertSame('30000.00', $principal2026->openAmount());

        $interest2027 = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2027-12-31')->first();
        $this->assertSame('4800.00', $interest2027->planned_amount);

        $b = $this->balance->balances($loan);
        $this->assertSame('80000.00', $b['principal_outstanding']);
        $this->assertSame('30000.00', $b['overdue_amount']);
    }

    public function test_staffelzins_schedule_via_recalculation(): void
    {
        // Staffelzins (Abschnitt 40): 6 % fuer 2026, 7 % ab 01.01.2027,
        // jaehrliche Zinsfaelligkeit, Laufzeit bis 31.12.2027:
        //   2026: 100000*0,06*365/365 = 6000,00
        //   2027: 100000*0,07*365/365 = 7000,00
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $loan = $this->makeLoan(
            ['contract_end' => '2027-12-31', 'interest_frequency' => 'annual'],
            [
                ['rate' => '6.000000', 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31'],
                ['rate' => '7.000000', 'valid_from' => '2027-01-01'],
            ],
        );
        $this->disbursement->plan($loan, ['planned_amount' => '100000.00', 'planned_date' => '2026-01-01']);

        $interest = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get();
        $this->assertCount(2, $interest);
        $this->assertSame('6000.00', $interest[0]->planned_amount);
        $this->assertSame('2026-12-31', $interest[0]->due_date->toDateString());
        $this->assertSame('7000.00', $interest[1]->planned_amount);
        $this->assertSame('2027-12-31', $interest[1]->due_date->toDateString());
    }

    public function test_recalculation_protocol_is_written_per_run(): void
    {
        $loan = $this->makeRetroactiveLoan();
        $before = $loan->recalculations()->count();

        $this->recalc->recalculate($loan, 'manual_test');

        $this->assertSame($before + 1, $loan->recalculations()->count());
        $latest = $loan->recalculations()->first(); // latest('created_at')
        $this->assertContains($latest->trigger_action, ['manual_test', 'disbursement_planned']);
        $this->assertSame('ok', $loan->recalculations()->where('trigger_action', 'manual_test')->first()->status);
    }
}
