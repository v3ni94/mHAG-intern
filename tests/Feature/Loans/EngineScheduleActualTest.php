<?php

namespace Tests\Feature\Loans;

use App\Enums\BookingType;
use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Models\RepaymentPlanItem;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\PaymentAllocationService;
use App\Services\Loans\ScheduleActualService;
use Illuminate\Support\Carbon;

/**
 * IST-Erfassung über den Zahlungsplan (Abschnitte 23, 26-29, 48).
 *
 * Prüfbericht Befund 1: Eine auf "Bestätigt bezahlt" gesetzte Tilgungszeile
 * hat das Kapital nicht gesenkt. Der ScheduleActualService erzeugt die
 * Buchung im Darlehenskonto; Rücknahme wirkt per Gegenbuchung.
 */
class EngineScheduleActualTest extends EngineTestCase
{
    private ScheduleActualService $actuals;

    private LoanBalanceService $balance;

    private PaymentAllocationService $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $this->actuals = app(ScheduleActualService::class);
        $this->balance = app(LoanBalanceService::class);
        $this->allocation = app(PaymentAllocationService::class);
    }

    /** Darlehen mit ausgezahltem Kapital 100.000,00 zum 01.01.2026. */
    private function makeDisbursedLoan(): Loan
    {
        $loan = $this->makeLoan(['interest_method' => 'thirty_360', 'contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');

        return $loan;
    }

    private function makeItem(Loan $loan, string $type, string $due, string $planned): RepaymentPlanItem
    {
        return $loan->repaymentPlanItems()->create([
            'item_type' => $type,
            'due_date' => $due,
            'planned_amount' => $planned,
            'status' => RepaymentItemStatus::Assumed,
            'origin' => 'assumed',
        ]);
    }

    public function test_bestaetigte_tilgung_senkt_kapital_und_erhoeht_getilgt(): void
    {
        // Rechenweg: ausgezahlt 100.000,00; Tilgung 50.000,00 bestätigt
        // zum 30.06.2026 -> getilgt 50.000,00,
        // offenes Kapital 100.000,00 - 50.000,00 = 50.000,00.
        $loan = $this->makeDisbursedLoan();
        $item = $this->makeItem($loan, 'principal', '2026-06-30', '50000.00');

        $item->update([
            'status' => RepaymentItemStatus::Confirmed,
            'actual_amount' => '50000.00',
            'actual_date' => '2026-06-30',
            'origin' => 'manual_confirmed',
        ]);

        $booking = $this->actuals->reconcile($item->refresh());

        $this->assertNotNull($booking, 'Für die bestätigte Tilgung muss eine Buchung entstehen.');
        $this->assertSame(BookingType::Repayment, $booking->booking_type);
        $this->assertSame('-50000.00', $booking->amount);
        $this->assertSame('2026-06-30', $booking->effective_date->toDateString());

        $b = $this->balance->balances($loan);
        $this->assertSame('50000.00', $b['repaid']);
        $this->assertSame('50000.00', $b['principal_outstanding']);
    }

    public function test_ruecknahme_hebt_die_wirkung_per_gegenbuchung_auf(): void
    {
        // Erst bestätigt (Kapital 50.000,00), dann auf "nicht bezahlt"
        // korrigiert: Kapital wieder 100.000,00, getilgt 0,00.
        // Append-only: die Erstbuchung bleibt, es kommt eine Gegenbuchung hinzu.
        $loan = $this->makeDisbursedLoan();
        $item = $this->makeItem($loan, 'principal', '2026-06-30', '50000.00');

        $item->update([
            'status' => RepaymentItemStatus::Confirmed,
            'actual_amount' => '50000.00',
            'actual_date' => '2026-06-30',
        ]);
        $this->actuals->reconcile($item->refresh());

        $item->update([
            'status' => RepaymentItemStatus::Missed,
            'actual_amount' => '0.00',
            'actual_date' => null,
        ]);
        $this->actuals->reconcile($item->refresh());

        $own = $loan->transactions()
            ->where('source_type', $item->getMorphClass())
            ->where('source_id', $item->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $own, 'Storno erfolgt als Gegenbuchung, nicht als Löschung.');
        $this->assertSame(BookingType::Repayment, $own[0]->booking_type);
        $this->assertSame(BookingType::Cancellation, $own[1]->booking_type);
        $this->assertSame('50000.00', $own[1]->amount);
        $this->assertSame($own[0]->id, $own[1]->reversal_of);
        $this->assertSame('2026-06-30', $own[1]->effective_date->toDateString());

        $b = $this->balance->balances($loan);
        $this->assertSame('0.00', $b['repaid']);
        $this->assertSame('100000.00', $b['principal_outstanding']);
        $this->assertSame('50000.00', $b['overdue_amount']);
    }

    public function test_keine_doppelbuchung_bei_zahlung_mit_verrechnung(): void
    {
        // Wird dieselbe Tilgungszeile über eine Zahlung verrechnet, ist der
        // Betrag bereits gebucht: getilgt bleibt 50.000,00 (nicht 100.000,00).
        $loan = $this->makeDisbursedLoan();
        $item = $this->makeItem($loan, 'principal', '2026-06-30', '50000.00');

        $payment = $this->makePayment($loan, '50000.00', '2026-06-30');
        $this->allocation->allocate($payment, ['principal' => '50000.00']);

        $item->refresh();
        $this->assertSame(RepaymentItemStatus::Confirmed, $item->status);
        $this->assertSame('50000.00', $item->actual_amount);

        $booking = $this->actuals->reconcile($item);

        $this->assertNull($booking, 'Bereits verrechnete Beträge dürfen nicht doppelt gebucht werden.');
        $b = $this->balance->balances($loan);
        $this->assertSame('50000.00', $b['repaid']);
        $this->assertSame('50000.00', $b['principal_outstanding']);
    }

    public function test_teilzahlung_ueber_zahlung_und_rest_ueber_planzeile(): void
    {
        // Zahlung verrechnet 20.000,00 auf die Tilgungszeile, der Rest wird
        // manuell als bezahlt erfasst: 50.000,00 - 20.000,00 = 30.000,00
        // werden aus der Planzeile gebucht; getilgt insgesamt 50.000,00.
        $loan = $this->makeDisbursedLoan();
        $item = $this->makeItem($loan, 'principal', '2026-06-30', '50000.00');

        $payment = $this->makePayment($loan, '20000.00', '2026-06-30');
        $this->allocation->allocate($payment, ['principal' => '20000.00']);

        $item->refresh();
        $item->update([
            'status' => RepaymentItemStatus::Confirmed,
            'actual_amount' => '50000.00',
            'actual_date' => '2026-06-30',
        ]);

        $booking = $this->actuals->reconcile($item->refresh());

        $this->assertNotNull($booking);
        $this->assertSame('-30000.00', $booking->amount);

        $b = $this->balance->balances($loan);
        $this->assertSame('50000.00', $b['repaid']);
        $this->assertSame('50000.00', $b['principal_outstanding']);
    }

    public function test_zins_und_gebuehrenzeilen_buchen_eigene_buchungsarten(): void
    {
        $loan = $this->makeDisbursedLoan();

        $interest = $this->makeItem($loan, 'interest', '2026-01-31', '500.00');
        $interest->update(['status' => RepaymentItemStatus::Confirmed, 'actual_amount' => '500.00', 'actual_date' => '2026-02-02']);
        $interestBooking = $this->actuals->reconcile($interest->refresh());

        $fee = $this->makeItem($loan, 'fee', '2026-01-31', '200.00');
        $fee->update(['status' => RepaymentItemStatus::Late, 'actual_amount' => '200.00', 'actual_date' => '2026-02-05']);
        $feeBooking = $this->actuals->reconcile($fee->refresh());

        $this->assertSame(BookingType::InterestPayment, $interestBooking->booking_type);
        $this->assertSame('-500.00', $interestBooking->amount);
        $this->assertSame('2026-02-02', $interestBooking->effective_date->toDateString());

        $this->assertSame(BookingType::FeePayment, $feeBooking->booking_type);
        $this->assertSame('-200.00', $feeBooking->amount);

        // Zins- und Gebührenzahlungen sind nicht kapitalwirksam.
        $b = $this->balance->balances($loan);
        $this->assertSame('100000.00', $b['principal_outstanding']);
        $this->assertSame('0.00', $b['repaid']);
    }

    public function test_wiederholter_aufruf_erzeugt_keine_zweite_buchung(): void
    {
        $loan = $this->makeDisbursedLoan();
        $item = $this->makeItem($loan, 'principal', '2026-06-30', '50000.00');
        $item->update([
            'status' => RepaymentItemStatus::Confirmed,
            'actual_amount' => '50000.00',
            'actual_date' => '2026-06-30',
        ]);

        $this->actuals->reconcile($item->refresh());
        $second = $this->actuals->reconcile($item->refresh());

        $this->assertNull($second);
        $this->assertSame(1, $loan->transactions()->where('booking_type', BookingType::Repayment->value)->count());
    }
}
