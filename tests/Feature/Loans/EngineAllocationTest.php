<?php

namespace Tests\Feature\Loans;

use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Models\Setting;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use App\Services\Loans\PaymentAllocationService;
use Illuminate\Support\Carbon;

/**
 * Zahlungsverrechnung (Abschnitte 46-47): konfigurierbare Reihenfolge,
 * aelteste offene Position zuerst, Rest in Kapital, Ueberzahlung ohne Ziel
 * in Bucket "other", Storno mit Gegenbuchung.
 *
 * Basis: 30/360-Darlehen mit exakt 500,00 EUR Monatszins
 * (100.000 * 6 % * 30/360).
 */
class EngineAllocationTest extends EngineTestCase
{
    private PaymentAllocationService $allocation;

    private LoanRecalculationService $recalc;

    private LoanBalanceService $balance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $this->allocation = app(PaymentAllocationService::class);
        $this->recalc = app(LoanRecalculationService::class);
        $this->balance = app(LoanBalanceService::class);
    }

    private function makeThirty360Loan(bool $withFee = false): Loan
    {
        $loan = $this->makeLoan(['interest_method' => 'thirty_360', 'contract_end' => '2026-12-31']);
        if ($withFee) {
            $loan->fees()->create(['type' => 'processing', 'name' => 'Bearbeitungsgebühr', 'amount' => '200.00', 'recurrence' => 'one_time', 'due_date' => '2026-01-31']);
        }
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->recalc->recalculate($loan, 'test_setup');

        return $loan;
    }

    public function test_default_allocation_order(): void
    {
        // Default-Reihenfolge: Kosten, Gebuehren, Verzugszinsen, Zinsen, Kapital.
        // Zahlung 800,00 am 05.02.2026; faellig: Gebuehr 200 (31.01.), Zins Januar 500 (31.01.).
        // Erwartung: 200 Gebuehr + 500 Zins + 100 Rest als Tilgung ins Kapital.
        $loan = $this->makeThirty360Loan(withFee: true);
        $payment = $this->makePayment($loan, '800.00', '2026-02-05');

        $result = $this->allocation->allocate($payment);

        $this->assertSame(['fees' => '200.00', 'interest' => '500.00', 'principal' => '100.00'], $result);

        $fee = $loan->repaymentPlanItems()->where('item_type', 'fee')->first();
        $this->assertSame(RepaymentItemStatus::Late, $fee->status); // voll, aber nach Faelligkeit
        $this->assertSame('200.00', $fee->actual_amount);

        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertSame(RepaymentItemStatus::Late, $jan->status);
        $this->assertSame('500.00', $jan->actual_amount);

        // Darlehenskonto: drei NEGATIVE Buchungen (Forderung sinkt).
        $bookings = $loan->transactions()->where('amount', '<', 0)->orderBy('id')->get();
        $this->assertSame(['fee_payment', 'interest_payment', 'repayment'], $bookings->pluck('booking_type')->map(fn ($b) => $b->value)->all());
        $this->assertSame(['-200.00', '-500.00', '-100.00'], $bookings->pluck('amount')->all());

        // Kapital sinkt durch die Resttilgung auf 99.900,00.
        $this->assertSame('99900.00', $this->balance->balances($loan)['principal_outstanding']);
        $this->assertSame(3, $payment->allocations()->count());
    }

    public function test_configured_allocation_order_from_setting(): void
    {
        // Geaenderte Reihenfolge: Zinsen vor Gebuehren.
        // Zahlung 550,00: 500 Zins Januar, 50 Teilbetrag auf die Gebuehr.
        Setting::set('loans', 'allocation_order', ['interest', 'fees', 'principal']);
        $loan = $this->makeThirty360Loan(withFee: true);
        $payment = $this->makePayment($loan, '550.00', '2026-02-05');

        $result = $this->allocation->allocate($payment);

        $this->assertSame(['interest' => '500.00', 'fees' => '50.00'], $result);

        $fee = $loan->repaymentPlanItems()->where('item_type', 'fee')->first();
        $this->assertSame(RepaymentItemStatus::Partial, $fee->status);
        $this->assertSame('50.00', $fee->actual_amount);
        $this->assertSame('150.00', $fee->openAmount());
    }

    public function test_oldest_open_item_first_and_second_payment_completes(): void
    {
        // Teilzahlung 300 auf Januar, danach 200: aelteste offene Position zuerst;
        // zweite Zahlung fuellt Januar auf (verspaetet, da nach dem 31.01.).
        $loan = $this->makeThirty360Loan();
        $this->allocation->allocate($this->makePayment($loan, '300.00', '2026-02-05'));

        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertSame(RepaymentItemStatus::Partial, $jan->status);
        $this->assertSame('300.00', $jan->actual_amount);

        $this->allocation->allocate($this->makePayment($loan, '200.00', '2026-03-01'));
        $jan->refresh();
        $this->assertSame('500.00', $jan->actual_amount);
        $this->assertSame(RepaymentItemStatus::Late, $jan->status);
        $this->assertSame('2026-03-01', $jan->actual_date->toDateString());
    }

    public function test_manual_buckets(): void
    {
        // Manuelle Aufteilung: 300,00 gezielt auf Vertragszinsen.
        $loan = $this->makeThirty360Loan();
        $payment = $this->makePayment($loan, '300.00', '2026-02-05');

        $result = $this->allocation->allocate($payment, ['interest' => '300.00']);

        $this->assertSame(['interest' => '300.00'], $result);
        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertSame('300.00', $jan->actual_amount);
        $this->assertSame(RepaymentItemStatus::Partial, $jan->status);
    }

    public function test_manual_buckets_exceeding_payment_rejected(): void
    {
        $loan = $this->makeThirty360Loan();
        $payment = $this->makePayment($loan, '100.00', '2026-02-05');

        $this->expectException(\InvalidArgumentException::class);
        $this->allocation->allocate($payment, ['interest' => '200.00']);
    }

    public function test_overpayment_without_target_goes_to_other(): void
    {
        // Kein Kapital, keine faelligen Positionen: Ueberzahlung -> Bucket "other".
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->recalc->recalculate($loan, 'test_setup'); // keine Auszahlung -> keine Zinszeilen
        $payment = $this->makePayment($loan, '100.00', '2026-02-05');

        $result = $this->allocation->allocate($payment);

        $this->assertSame(['other' => '100.00'], $result);
        $tx = $loan->transactions()->orderByDesc('id')->first();
        $this->assertSame('other', $tx->booking_type->value);
        $this->assertSame('-100.00', $tx->amount);
    }

    public function test_cancel_payment_reverses_bookings_and_resets_item(): void
    {
        // Storno (Abschnitte 26/49): Gegenbuchung statt Loeschen; die Planzeile
        // faellt in den Planzustand zurueck, die Neuberechnung stellt die
        // Grundannahme wieder her.
        $loan = $this->makeThirty360Loan();
        $payment = $this->makePayment($loan, '500.00', '2026-02-05');
        $this->allocation->allocate($payment);

        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertSame(RepaymentItemStatus::Late, $jan->status);

        $this->allocation->cancel($payment, 'Fehlbuchung', null);

        $payment->refresh();
        $this->assertSame('cancelled', $payment->status);
        $this->assertSame('Fehlbuchung', $payment->cancel_reason);

        // Gegenbuchung: interest_payment -500 und cancellation +500 mit reversal_of.
        $paymentTxs = $loan->transactions()
            ->where('source_type', $payment->getMorphClass())
            ->where('source_id', $payment->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $paymentTxs);
        $this->assertSame('interest_payment', $paymentTxs[0]->booking_type->value);
        $this->assertSame('-500.00', $paymentTxs[0]->amount);
        $this->assertSame('cancellation', $paymentTxs[1]->booking_type->value);
        $this->assertSame('500.00', $paymentTxs[1]->amount);
        $this->assertSame($paymentTxs[0]->id, $paymentTxs[1]->reversal_of);

        // Zeile ohne verbleibendes IST: zurueck in den Planzustand.
        $jan->refresh();
        $this->assertSame(RepaymentItemStatus::Planned, $jan->status);
        $this->assertNull($jan->actual_amount);
        $this->assertStringContainsString('Zahlung storniert', $jan->comment);

        // Neuberechnung stellt die Grundannahme wieder her; stornierte Zahlung
        // zaehlt nicht mehr zu den Zahlungseingaengen.
        $this->recalc->recalculate($loan, 'payment_cancelled', Carbon::parse('2026-01-31'));
        $jan->refresh();
        $this->assertSame(RepaymentItemStatus::Assumed, $jan->status);

        $b = $this->balance->balances($loan);
        $this->assertSame('0.00', $b['payments_received']);
        $this->assertSame('0.00', $b['interest_confirmed']);
        $this->assertSame('100000.00', $b['total_receivable']);
    }

    public function test_cancel_partial_of_two_payments_keeps_remaining_actual(): void
    {
        // Zwei Zahlungen auf dieselbe Zeile; Storno der ersten laesst das IST
        // der zweiten bestehen (Korrekturherkunft).
        $loan = $this->makeThirty360Loan();
        $p1 = $this->makePayment($loan, '300.00', '2026-02-05');
        $this->allocation->allocate($p1);
        $p2 = $this->makePayment($loan, '200.00', '2026-02-10');
        $this->allocation->allocate($p2);

        $jan = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-01-31')->first();
        $this->assertSame('500.00', $jan->actual_amount);

        $this->allocation->cancel($p1, 'Doppelbuchung', null);

        $jan->refresh();
        $this->assertSame('200.00', $jan->actual_amount);
        $this->assertSame(RepaymentItemStatus::Partial, $jan->status);
        $this->assertSame('corrected', $jan->origin->value);
        $this->assertSame('2026-02-10', $jan->actual_date->toDateString());
    }

    public function test_cancelled_payment_cannot_be_allocated(): void
    {
        $loan = $this->makeThirty360Loan();
        $payment = $this->makePayment($loan, '100.00', '2026-02-05', ['status' => 'cancelled']);

        $this->expectException(\InvalidArgumentException::class);
        $this->allocation->allocate($payment);
    }
}
