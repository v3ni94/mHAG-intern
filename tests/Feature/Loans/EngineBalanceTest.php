<?php

namespace Tests\Feature\Loans;

use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use App\Services\Loans\PaymentAllocationService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Salden (balances) und Forderungsaufstellung (statementRows), stichtagsfaehig.
 * Basisdarlehen mit 30/360, damit die Beispielwerte des Masterprompts
 * (Soll 500, Ist 300, offen 200; Abschnitt 27) exakt entstehen:
 * 100.000,00 EUR * 6 % * 30/360 = 500,00 je Monat.
 */
class EngineBalanceTest extends EngineTestCase
{
    private LoanBalanceService $balance;

    private LoanRecalculationService $recalc;

    private PaymentAllocationService $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $this->balance = app(LoanBalanceService::class);
        $this->recalc = app(LoanRecalculationService::class);
        $this->allocation = app(PaymentAllocationService::class);
    }

    private function makeThirty360Loan(): Loan
    {
        $loan = $this->makeLoan(['interest_method' => 'thirty_360', 'contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->recalc->recalculate($loan, 'test_setup');

        return $loan;
    }

    public function test_balances_with_pure_assumption(): void
    {
        // Heute 30.08.2026: Zeilen Januar bis Juli (Faelligkeit 31.07.) sind
        // systemseitig angenommen; 31.08. ist noch geplant.
        // interest_charged = 7 * 500 = 3500; alles Annahme, nichts bestaetigt.
        $loan = $this->makeThirty360Loan();
        $b = $this->balance->balances($loan);

        $this->assertSame('100000.00', $b['disbursed']);
        $this->assertSame('0.00', $b['repaid']);
        $this->assertSame('100000.00', $b['principal_outstanding']);
        $this->assertSame('3500.00', $b['interest_charged']);
        $this->assertSame('3500.00', $b['interest_assumed']);
        $this->assertSame('0.00', $b['interest_confirmed']);
        $this->assertSame('0.00', $b['interest_open']);
        $this->assertSame('0.00', $b['overdue_amount']); // Annahme gilt als erfuellt
        $this->assertSame('100000.00', $b['total_receivable']);
        $this->assertSame('0.00', $b['payments_received']);
        $this->assertSame('2026-08-31', $b['next_due_date']);
        $this->assertSame('500.00', $b['next_due_amount']);
    }

    public function test_partial_payment_500_paid_300_open_200(): void
    {
        // Abschnitt 27: Soll 500, Ist 300, offen 200, Status teilweise bezahlt.
        $loan = $this->makeThirty360Loan();
        $payment = $this->makePayment($loan, '300.00', '2026-02-05');
        $this->allocation->allocate($payment);

        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertSame('300.00', $jan->actual_amount);
        $this->assertSame(RepaymentItemStatus::Partial, $jan->status);
        $this->assertSame('200.00', $jan->openAmount());
        $this->assertSame('bank_import', $jan->origin->value);

        $b = $this->balance->balances($loan);
        $this->assertSame('3500.00', $b['interest_charged']);
        $this->assertSame('300.00', $b['interest_confirmed']);
        $this->assertSame('3000.00', $b['interest_assumed']); // Februar bis Juli
        $this->assertSame('200.00', $b['interest_open']);
        $this->assertSame('200.00', $b['overdue_amount']); // partial zaehlt als ueberfaellig
        $this->assertSame('100200.00', $b['total_receivable']);
        $this->assertSame('300.00', $b['payments_received']);
    }

    public function test_stichtag_calculation(): void
    {
        // Stichtagsberechnung (Abschnitt 50): beliebiger Stichtag.
        $loan = $this->makeThirty360Loan();

        // 30.06.2026: 6 Zeilen faellig (Jan-Jun) = 3000,00 SOLL, alles Annahme.
        $b = $this->balance->balances($loan, Carbon::parse('2026-06-30'));
        $this->assertSame('3000.00', $b['interest_charged']);
        $this->assertSame('3000.00', $b['interest_assumed']);
        $this->assertSame('100000.00', $b['total_receivable']);

        // 31.12.2026: volles Jahr = 6000,00 SOLL; kuenftige planmaessige
        // Zeilen gelten am Stichtag als planmaessig erfuellt (Abschnitt 24).
        $b = $this->balance->balances($loan, Carbon::parse('2026-12-31'));
        $this->assertSame('6000.00', $b['interest_charged']);
        $this->assertSame('6000.00', $b['interest_assumed']);
        $this->assertSame('0.00', $b['interest_open']);
        $this->assertSame('100000.00', $b['total_receivable']);
    }

    public function test_late_payment_visible_at_earlier_stichtag(): void
    {
        // Verspaetete Zahlung: Dezember-Zinsen (faellig 31.12.2026) werden erst
        // am 15.01.2027 gezahlt. Am Stichtag 31.12.2026 sind sie offen,
        // am 31.01.2027 beglichen.
        Carbon::setTestNow(Carbon::parse('2027-02-01 12:00:00'));
        $loan = $this->makeThirty360Loan();

        // Januar bis November: bestaetigt bezahlt am jeweiligen Faelligkeitstag.
        foreach ($loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get()->take(11) as $item) {
            $item->update([
                'status' => RepaymentItemStatus::Confirmed,
                'actual_amount' => $item->planned_amount,
                'actual_date' => $item->due_date->toDateString(),
                'origin' => 'bank_import',
            ]);
        }
        // Dezember: zunaechst als nicht bezahlt erfasst (Abschnitt 26).
        $dec = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-12-31')->first();
        $dec->update(['status' => RepaymentItemStatus::Missed, 'actual_amount' => '0.00', 'origin' => 'manual_entered']);

        // Zahlung 500,00 am 15.01.2027 -> Zeile wird verspaetet erfuellt.
        $payment = $this->makePayment($loan, '500.00', '2027-01-15');
        $this->allocation->allocate($payment);

        $dec->refresh();
        $this->assertSame(RepaymentItemStatus::Late, $dec->status);
        $this->assertSame('500.00', $dec->actual_amount);
        $this->assertSame('2027-01-15', $dec->actual_date->toDateString());

        // Stichtag 31.12.2026: Zahlung lag noch nicht vor -> 500,00 offen.
        $b = $this->balance->balances($loan, Carbon::parse('2026-12-31'));
        $this->assertSame('6000.00', $b['interest_charged']);
        $this->assertSame('5500.00', $b['interest_confirmed']);
        $this->assertSame('500.00', $b['interest_open']);
        $this->assertSame('100500.00', $b['total_receivable']);

        // Stichtag 31.01.2027: beglichen.
        $b = $this->balance->balances($loan, Carbon::parse('2027-01-31'));
        $this->assertSame('6000.00', $b['interest_confirmed']);
        $this->assertSame('0.00', $b['interest_open']);
        $this->assertSame('100000.00', $b['total_receivable']);
    }

    public function test_statement_rows_add_up(): void
    {
        // Forderungsaufstellung (Abschnitt 51):
        // Kapital + Vertragszinsen - Zahlungen = Gesamtforderung.
        Carbon::setTestNow(Carbon::parse('2027-02-01 12:00:00'));
        $loan = $this->makeThirty360Loan();
        foreach ($loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get()->take(11) as $item) {
            $item->update([
                'status' => RepaymentItemStatus::Confirmed,
                'actual_amount' => $item->planned_amount,
                'actual_date' => $item->due_date->toDateString(),
                'origin' => 'bank_import',
            ]);
        }
        $dec = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-12-31')->first();
        $dec->update(['status' => RepaymentItemStatus::Missed, 'actual_amount' => '0.00', 'origin' => 'manual_entered']);

        $statement = $this->balance->statementRows($loan, Carbon::parse('2026-12-31'));

        // Erwartung: 100000 (Kapital) + 6000 (Zinsen) - 0 (Tilgungen) - 5500 (Zinszahlungen) = 100500.
        $this->assertSame('2026-12-31', $statement['as_of']);
        $this->assertSame('100500.00', $statement['total']);

        $sum = '0.00';
        foreach ($statement['rows'] as $row) {
            $this->assertContains($row['sign'], ['+', '-']);
            $sum = $row['sign'] === '+' ? Money::add($sum, $row['amount']) : Money::sub($sum, $row['amount']);
        }
        $this->assertSame($statement['total'], $sum);

        $labels = array_column($statement['rows'], 'label');
        $this->assertSame('Ausgezahltes Kapital', $labels[0]);
        $this->assertStringContainsString('Vertragszinsen', $labels[1]);
    }

    public function test_fees_in_balances(): void
    {
        // Gebuehren: 500,00 einmalig, faellig 15.02.2026, noch unbezahlt erfasst.
        $loan = $this->makeLoan(['interest_method' => 'thirty_360', 'contract_end' => '2026-12-31']);
        $loan->fees()->create(['type' => 'processing', 'name' => 'Bearbeitungsgebühr', 'amount' => '500.00', 'recurrence' => 'one_time', 'due_date' => '2026-02-15']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->recalc->recalculate($loan, 'test_setup');

        $fee = $loan->repaymentPlanItems()->where('item_type', 'fee')->first();
        $fee->update(['status' => RepaymentItemStatus::Missed, 'actual_amount' => '0.00', 'origin' => 'manual_entered']);

        $b = $this->balance->balances($loan);
        $this->assertSame('500.00', $b['fees_charged']);
        $this->assertSame('0.00', $b['fees_paid']);
        $this->assertSame('500.00', $b['fees_open']);
        $this->assertSame('500.00', $b['overdue_amount']);
        $this->assertSame('100500.00', $b['total_receivable']);
    }
}
