<?php

namespace Tests\Unit\Loans;

use App\Models\LoanTransaction;
use App\Services\Loans\InterestCalculationService;
use Illuminate\Support\Carbon;
use Tests\Feature\Loans\EngineTestCase;

/**
 * interestForLoanPeriod: Segmentierung nach Zinssatzwechseln (Staffelzins)
 * und Kapitalaenderungen aus loan_transactions. Erwartungswerte von Hand
 * vorgerechnet (Rechenweg in den Kommentaren).
 */
class EngineLoanPeriodInterestTest extends EngineTestCase
{
    private InterestCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InterestCalculationService::class);
    }

    public function test_staffelzins_splits_at_rate_change(): void
    {
        // Staffelzins gem. Abschnitt 40: 6 % fuer 2026, 7 % ab 01.01.2027.
        $loan = $this->makeLoan([], [
            ['rate' => '6.000000', 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31'],
            ['rate' => '7.000000', 'valid_from' => '2027-01-01'],
        ]);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');

        // [01.07.2026, 01.07.2027):
        //   Segment 1 [01.07.2026, 01.01.2027) = 184 Tage zu 6 %:
        //     100000 * 0,06 * 184/365 = 1.104.000/365 = 3024,6575342...
        //   Segment 2 [01.01.2027, 01.07.2027) = 181 Tage zu 7 %:
        //     100000 * 0,07 * 181/365 = 1.267.000/365 = 3471,2328767...
        //   Summe = 6495,8904109... -> gerundet 6495,89
        $interest = $this->service->interestForLoanPeriod($loan, Carbon::parse('2026-07-01'), Carbon::parse('2027-07-01'));
        $this->assertSame('6495.89', $interest);
    }

    public function test_no_interest_before_first_term(): void
    {
        // Vor dem ersten Zinsterm gilt 0 (zinslos).
        $loan = $this->makeLoan();
        $this->bookDisbursement($loan, '100000.00', '2025-01-01');

        $interest = $this->service->interestForLoanPeriod($loan, Carbon::parse('2025-01-01'), Carbon::parse('2025-06-01'));
        $this->assertSame('0.00', $interest);
    }

    public function test_last_rate_continues_after_term_end(): void
    {
        // Term endet 30.06.2026; danach gilt der letzte gueltige Satz weiter.
        $loan = $this->makeLoan([], [
            ['rate' => '6.000000', 'valid_from' => '2026-01-01', 'valid_until' => '2026-06-30'],
        ]);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');

        // Juli 2026, 31 Tage: 100000 * 0,06 * 31/365 = 509,589... -> 509,59
        $interest = $this->service->interestForLoanPeriod($loan, Carbon::parse('2026-07-01'), Carbon::parse('2026-08-01'));
        $this->assertSame('509.59', $interest);
    }

    public function test_capital_change_splits_period(): void
    {
        // Teiltilgung 40.000 am 01.04.2026 (effective_date zaehlt):
        // [01.03., 01.04.) 31 Tage auf 100.000: 100000*0,06*31/365 = 509,5890...
        // [01.04., 01.05.) 30 Tage auf  60.000:  60000*0,06*30/365 = 295,8904...
        // Summe exakt: (186000 + 108000)/365 = 294000/365 = 805,4794... -> 805,48
        $loan = $this->makeLoan();
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->bookRepayment($loan, '40000.00', '2026-04-01');

        $interest = $this->service->interestForLoanPeriod($loan, Carbon::parse('2026-03-01'), Carbon::parse('2026-05-01'));
        $this->assertSame('805.48', $interest);
    }

    public function test_act_act_leap_year_full_year(): void
    {
        // ACT/ACT (ISDA), Schaltjahr 2024: 100000 * 5 % * 366/366 = 5000,00
        $loan = $this->makeLoan(
            ['interest_method' => 'act_act', 'effective_from' => '2024-01-01'],
            [['rate' => '5.000000', 'valid_from' => '2024-01-01']],
        );
        $this->bookDisbursement($loan, '100000.00', '2024-01-01');

        $interest = $this->service->interestForLoanPeriod($loan, Carbon::parse('2024-01-01'), Carbon::parse('2025-01-01'));
        $this->assertSame('5000.00', $interest);
    }

    public function test_capital_at_respects_effective_date(): void
    {
        $loan = $this->makeLoan();
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->bookRepayment($loan, '40000.00', '2026-04-01');

        $this->assertSame('100000.00', $this->service->capitalAt($loan, Carbon::parse('2026-03-31')));
        $this->assertSame('60000.00', $this->service->capitalAt($loan, Carbon::parse('2026-04-01')));
    }

    public function test_reversed_disbursement_has_no_capital_effect(): void
    {
        // Gegenbuchung (Storno) neutralisiert die Auszahlung rueckwirkend:
        // Kapital 0 -> keine Zinsen, Historie bleibt aber erhalten (2 Buchungen).
        $loan = $this->makeLoan();
        $tx = $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'cancellation',
            'booking_date' => '2026-02-01',
            'effective_date' => '2026-01-01',
            'amount' => '-100000.00',
            'reversal_of' => $tx->id,
            'description' => 'Test: Storno der Auszahlung',
        ]);

        $this->assertSame('0.00', $this->service->capitalAt($loan, Carbon::parse('2026-02-01')));
        $this->assertSame('0.00', $this->service->interestForLoanPeriod($loan, Carbon::parse('2026-01-01'), Carbon::parse('2026-02-01')));
        $this->assertSame(2, $loan->transactions()->count());
    }
}
