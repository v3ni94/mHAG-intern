<?php

namespace Tests\Feature\Loans;

use App\Enums\DisbursementStatus;
use App\Enums\PaymentOrigin;
use App\Models\Loan;
use App\Services\Loans\DisbursementService;
use App\Services\Loans\LoanBalanceService;
use Illuminate\Support\Carbon;

/**
 * Auszahlungen (Abschnitte 31-32): SOLL/IST getrennt, Grundannahme,
 * Teil-Auszahlung, nicht erfolgte Auszahlung mit automatischer Korrektur
 * aller Folgewerte, Storno. Historie append-only (Gegenbuchungen).
 */
class EngineDisbursementTest extends EngineTestCase
{
    private DisbursementService $service;

    private LoanBalanceService $balance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $this->service = app(DisbursementService::class);
        $this->balance = app(LoanBalanceService::class);
    }

    private function makeContractLoan(): Loan
    {
        return $this->makeLoan(['contract_end' => '2026-12-31']);
    }

    public function test_plan_future_disbursement_books_nothing(): void
    {
        $loan = $this->makeContractLoan();
        $d = $this->service->plan($loan, ['planned_amount' => '50000.00', 'planned_date' => '2026-10-01']);

        $this->assertSame(DisbursementStatus::Planned, $d->status);
        $this->assertNull($d->actual_amount);
        $this->assertSame(0, $loan->transactions()->count());
        // Kein Kapital -> keine Zins-SOLL-Zeilen.
        $this->assertSame(0, $loan->repaymentPlanItems()->where('item_type', 'interest')->count());
        // Neuberechnung wurde protokolliert.
        $this->assertSame(1, $loan->recalculations()->where('trigger_action', 'disbursement_planned')->where('status', 'ok')->count());
    }

    public function test_plan_past_disbursement_is_assumed_and_booked(): void
    {
        // Rueckwirkend geplante Auszahlung: Grundannahme (Abschnitt 24) bucht
        // sie als systemseitig angenommen mit Wirkungsdatum = Plandatum.
        $loan = $this->makeContractLoan();
        $d = $this->service->plan($loan, ['planned_amount' => '100000.00', 'planned_date' => '2026-01-01']);

        $this->assertSame(DisbursementStatus::Assumed, $d->status);
        $this->assertSame(PaymentOrigin::Assumed, $d->origin);

        $tx = $loan->transactions()->first();
        $this->assertSame('disbursement', $tx->booking_type->value);
        $this->assertSame('100000.00', $tx->amount);
        $this->assertSame('2026-01-01', $tx->effective_date->toDateString());

        // Zins-SOLL entsteht automatisch: Januar = 100000*0,06*31/365 = 509,59.
        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertNotNull($jan);
        $this->assertSame('509.59', $jan->planned_amount);

        $this->assertSame('100000.00', $this->balance->balances($loan)['disbursed']);
    }

    public function test_confirm_partial_disbursement_rebooks_with_actual_date(): void
    {
        // Teil-Auszahlung: Soll 50.000 am 01.01., tatsaechlich 30.000 am 15.01.
        // Die angenommene Buchung wird gegengebucht, die bestaetigte neu gebucht.
        // Januar-Zinsen: nur [15.01., 01.02.) = 17 Tage auf 30.000:
        // 30000*0,06*17/365 = 30600/365 = 83,8356... -> 83,84.
        $loan = $this->makeContractLoan();
        $d = $this->service->plan($loan, ['planned_amount' => '50000.00', 'planned_date' => '2026-01-01']);

        $this->service->confirm($d, '30000.00', Carbon::parse('2026-01-15'), PaymentOrigin::BankImport);

        $d->refresh();
        $this->assertSame(DisbursementStatus::Partial, $d->status);
        $this->assertSame('30000.00', $d->actual_amount);
        $this->assertSame('2026-01-15', $d->actual_date->toDateString());
        $this->assertSame(PaymentOrigin::BankImport, $d->origin);
        $this->assertSame('50000.00', $d->planned_amount); // SOLL bleibt erhalten

        // Historie: +50000 (angenommen), -50000 (Gegenbuchung, Wirkung 01.01.), +30000 (15.01.).
        $txs = $loan->transactions()->orderBy('id')->get();
        $this->assertCount(3, $txs);
        $this->assertSame('50000.00', $txs[0]->amount);
        $this->assertSame('-50000.00', $txs[1]->amount);
        $this->assertSame('cancellation', $txs[1]->booking_type->value);
        $this->assertSame($txs[0]->id, $txs[1]->reversal_of);
        $this->assertSame('2026-01-01', $txs[1]->effective_date->toDateString());
        $this->assertSame('30000.00', $txs[2]->amount);
        $this->assertSame('2026-01-15', $txs[2]->effective_date->toDateString());

        $b = $this->balance->balances($loan);
        $this->assertSame('30000.00', $b['disbursed']);
        $this->assertSame('30000.00', $b['principal_outstanding']);

        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertSame('83.84', $jan->planned_amount);
    }

    public function test_mark_failed_zeroes_capital_and_interest(): void
    {
        // Nicht erfolgte Auszahlung (Abschnitt 32): Soll 50.000, Ist 0,
        // Status nicht ausgezahlt; Kapital, Zinsen und Forderung werden
        // automatisch korrigiert; Historie bleibt (Gegenbuchung).
        $loan = $this->makeContractLoan();
        $d = $this->service->plan($loan, ['planned_amount' => '50000.00', 'planned_date' => '2026-01-01']);
        $this->assertGreaterThan(0, $loan->repaymentPlanItems()->where('item_type', 'interest')->count());

        $this->service->markFailed($d, 'Bank hat nicht ausgeführt');

        $d->refresh();
        $this->assertSame(DisbursementStatus::Failed, $d->status);
        $this->assertSame('0.00', $d->actual_amount);
        $this->assertSame('50000.00', $d->planned_amount); // SOLL unveraendert

        $txs = $loan->transactions()->orderBy('id')->get();
        $this->assertCount(2, $txs); // Buchung + Gegenbuchung, nichts geloescht
        $this->assertSame('cancellation', $txs[1]->booking_type->value);
        $this->assertSame('-50000.00', $txs[1]->amount);

        $b = $this->balance->balances($loan);
        $this->assertSame('0.00', $b['disbursed']);
        $this->assertSame('0.00', $b['principal_outstanding']);
        $this->assertSame('0.00', $b['interest_charged']);
        // Abgeleitete Zins-SOLL-Zeilen sind wieder entfernt.
        $this->assertSame(0, $loan->repaymentPlanItems()->where('item_type', 'interest')->count());
    }

    public function test_cancel_planned_disbursement(): void
    {
        $loan = $this->makeContractLoan();
        $d = $this->service->plan($loan, ['planned_amount' => '50000.00', 'planned_date' => '2026-10-01']);

        $this->service->cancel($d, 'Vertrag geaendert');

        $d->refresh();
        $this->assertSame(DisbursementStatus::Cancelled, $d->status);
        $this->assertSame(PaymentOrigin::Cancelled, $d->origin);
        $this->assertSame(0, $loan->transactions()->count());
    }

    public function test_plan_requires_positive_amount(): void
    {
        $loan = $this->makeContractLoan();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->plan($loan, ['planned_amount' => '0.00', 'planned_date' => '2026-10-01']);
    }
}
