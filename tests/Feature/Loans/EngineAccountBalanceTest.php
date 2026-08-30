<?php

namespace Tests\Feature\Loans;

use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kontostand je Darlehen (Anforderung vom 30.08.2026).
 *
 * Der Kontostand ist die Summe aller Buchungen des Darlehenskontos bis zum
 * Stichtag. Er ist von der Gesamtforderung abzugrenzen, die zusätzlich die
 * entstandenen, aber noch nicht gebuchten Soll-Positionen enthält.
 */
class EngineAccountBalanceTest extends EngineTestCase
{
    private LoanBalanceService $balance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:00:00'));
        $this->balance = app(LoanBalanceService::class);
    }

    public function test_kontostand_ist_die_summe_der_buchungen(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->bookRepayment($loan, '25000.00', '2026-03-01');

        // 100.000,00 - 25.000,00 = 75.000,00
        $this->assertSame('75000.00', $this->balance->accountBalance($loan, Carbon::parse('2026-04-15')));
    }

    public function test_kontostand_ist_stichtagsfaehig(): void
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->bookRepayment($loan, '25000.00', '2026-03-01');

        $this->assertSame('0.00', $this->balance->accountBalance($loan, Carbon::parse('2025-12-31')));
        $this->assertSame('100000.00', $this->balance->accountBalance($loan, Carbon::parse('2026-02-28')));
        $this->assertSame('75000.00', $this->balance->accountBalance($loan, Carbon::parse('2026-03-01')));
    }

    public function test_kontostand_und_gesamtforderung_sind_klar_abgegrenzt(): void
    {
        // 100.000,00 EUR, 6 % ACT/365, monatliche Zinsen, keine Zahlungen.
        // Gebucht ist nur die Auszahlung, die Zinsen stehen als Soll im
        // Zahlungsplan. Sie gelten nach Abschnitt 24 als planmäßig erfüllt und
        // erhöhen die Forderung deshalb nicht: beide Werte sind hier gleich.
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $salden = $this->balance->balances($loan->fresh(), Carbon::parse('2026-04-15'));
        $this->assertSame('100000.00', $salden['account_balance']);
        $this->assertSame('100000.00', $salden['total_receivable']);

        // Wird eine Zinsposition als nicht bezahlt erfasst, entsteht eine
        // offene Forderung, ohne dass eine Buchung entsteht: die
        // Gesamtforderung steigt, der Kontostand bleibt unverändert.
        $januar = $loan->repaymentPlanItems()->where('item_type', 'interest')
            ->whereDate('due_date', '2026-01-31')->firstOrFail();
        $januar->update([
            'actual_amount' => '0.00',
            'status' => \App\Enums\RepaymentItemStatus::Missed,
            'origin' => \App\Enums\PaymentOrigin::ManualConfirmed,
        ]);

        $salden = $this->balance->balances($loan->fresh(), Carbon::parse('2026-04-15'));
        $this->assertSame('100000.00', $salden['account_balance'], 'Der Kontostand kennt nur Buchungen.');
        // 31 Tage auf 100.000,00 EUR bei 6 % ACT/365 = 509,59 EUR
        $this->assertSame('509.59', $salden['interest_open']);
        $this->assertSame('100509.59', $salden['total_receivable']);
    }

    public function test_kontostaende_mehrerer_darlehen_in_einer_abfrage(): void
    {
        $eins = $this->makeLoan(['contract_end' => '2026-12-31']);
        $zwei = $this->makeLoan(['contract_end' => '2026-12-31']);
        $drei = $this->makeLoan(['contract_end' => '2026-12-31']);

        $this->bookDisbursement($eins, '100000.00', '2026-01-01');
        $this->bookRepayment($eins, '10000.00', '2026-02-01');
        $this->bookDisbursement($zwei, '50000.00', '2026-01-15');
        // Darlehen drei ohne Buchungen

        DB::enableQueryLog();
        $stand = $this->balance->accountBalancesFor(
            [$eins->id, $zwei->id, $drei->id],
            Carbon::parse('2026-04-15'),
        );
        $abfragen = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame('90000.00', $stand[$eins->id]);
        $this->assertSame('50000.00', $stand[$zwei->id]);
        $this->assertSame('0.00', $stand[$drei->id], 'Ohne Buchungen ist der Kontostand 0,00 EUR.');
        $this->assertSame(1, $abfragen, 'Fuer die Liste darf nur eine Abfrage entstehen.');
    }

    public function test_leere_liste_erzeugt_keine_abfrage(): void
    {
        DB::enableQueryLog();
        $this->assertSame([], $this->balance->accountBalancesFor([]));
        $this->assertSame(0, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
