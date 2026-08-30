<?php

namespace Tests\Feature\Loans;

use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Services\Loans\LoanScheduleService;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Zahlungsplan-Erzeugung (SOLL): Zinsperioden, Tilgungsmodelle, Gebuehren,
 * Schutz erfasster IST-Zeilen, Grundannahme (rollForwardAssumed).
 * Erwartungswerte von Hand vorgerechnet (Rechenweg in den Kommentaren).
 */
class EngineScheduleTest extends EngineTestCase
{
    private LoanScheduleService $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
        $this->schedule = app(LoanScheduleService::class);
    }

    private function makeStandardLoan(): Loan
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');

        return $loan;
    }

    public function test_monthly_interest_rows_act_365(): void
    {
        // 100.000,00 EUR, 6 % p. a., ACT/365, monatlich, 01.01.-31.12.2026.
        // Periodenende = Faelligkeitstag, Zeitraum [Periodenbeginn, Folgeperiodenbeginn):
        //   Januar (31 Tage): 100000*0,06*31/365 = 509,59
        //   Februar (28 Tage): 100000*0,06*28/365 = 460,27
        //   April (30 Tage): 100000*0,06*30/365 = 493,15
        // Jahressumme = 6000,00 (365/365).
        $loan = $this->makeStandardLoan();
        $this->schedule->generate($loan);

        $interest = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get();
        $this->assertCount(12, $interest);
        $this->assertSame('2026-01-31', $interest[0]->due_date->toDateString());
        $this->assertSame('509.59', $interest[0]->planned_amount);
        $this->assertSame('460.27', $interest[1]->planned_amount);
        $this->assertSame('493.15', $interest[3]->planned_amount);
        $this->assertSame('2026-12-31', $interest[11]->due_date->toDateString());
        $this->assertSame('509.59', $interest[11]->planned_amount);
        $this->assertSame('6000.00', Money::sum($interest->pluck('planned_amount')));

        // Endfaellig: eine Tilgungszeile am Vertragsende ueber die Darlehenssumme.
        $principal = $loan->repaymentPlanItems()->where('item_type', 'principal')->get();
        $this->assertCount(1, $principal);
        $this->assertSame('2026-12-31', $principal[0]->due_date->toDateString());
        $this->assertSame('100000.00', $principal[0]->planned_amount);
    }

    public function test_generate_is_idempotent(): void
    {
        $loan = $this->makeStandardLoan();
        $this->schedule->generate($loan);
        $countFirst = $loan->repaymentPlanItems()->count();
        $this->schedule->generate($loan);

        $this->assertSame($countFirst, $loan->repaymentPlanItems()->count());
    }

    public function test_leap_year_february_2024(): void
    {
        // Schaltjahr: Februar 2024 hat 29 Tage; ACT/365: 100000*0,06*29/365 = 476,71
        $loan = $this->makeLoan(
            ['effective_from' => '2024-01-01', 'contract_end' => '2024-12-31'],
            [['rate' => '6.000000', 'valid_from' => '2024-01-01']],
        );
        $this->bookDisbursement($loan, '100000.00', '2024-01-01');
        $this->schedule->generate($loan);

        $feb = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2024-02-29')->first();
        $this->assertNotNull($feb);
        $this->assertSame('476.71', $feb->planned_amount);
    }

    public function test_retroactive_rate_change_updates_only_system_rows(): void
    {
        // Zinssatz rueckwirkend geaendert (Abschnitt 35): Zeilen mit erfasstem IST
        // oder manueller Anpassung werden NIE ueberschrieben.
        $loan = $this->makeStandardLoan();
        $this->schedule->generate($loan);

        $items = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get();
        $items[0]->update([ // Januar: IST bestaetigt
            'status' => RepaymentItemStatus::Confirmed,
            'actual_amount' => '509.59',
            'actual_date' => '2026-01-31',
            'origin' => 'manual_confirmed',
        ]);
        $items[3]->update(['planned_amount' => '999.99', 'manually_adjusted' => true]); // April manuell

        // Rueckwirkend auf 5 % geaendert: Februar neu 100000*0,05*28/365 = 383,56;
        // Mai neu 100000*0,05*31/365 = 424,66.
        $loan->interestTerms()->first()->update(['rate' => '5.000000']);
        $this->schedule->generate($loan);

        $items = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get();
        $this->assertSame('509.59', $items[0]->planned_amount); // geschuetzt (IST erfasst)
        $this->assertSame(RepaymentItemStatus::Confirmed, $items[0]->status);
        $this->assertSame('383.56', $items[1]->planned_amount); // aktualisiert
        $this->assertSame('999.99', $items[3]->planned_amount); // geschuetzt (manuell)
        $this->assertSame('424.66', $items[4]->planned_amount); // aktualisiert
    }

    public function test_installment_principal_rows(): void
    {
        // Ratendarlehen: 12.000 / 12 Monatsperioden = 1.000,00 je Periode.
        $loan = $this->makeLoan([
            'principal_amount' => '12000.00',
            'contract_end' => '2026-12-31',
            'repayment_model' => 'installment',
        ]);
        $this->schedule->generate($loan);

        $principal = $loan->repaymentPlanItems()->where('item_type', 'principal')->orderBy('due_date')->get();
        $this->assertCount(12, $principal);
        $this->assertSame('1000.00', $principal[0]->planned_amount);
        $this->assertSame('1000.00', $principal[11]->planned_amount);
        $this->assertSame('12000.00', Money::sum($principal->pluck('planned_amount')));
    }

    public function test_annuity_principal_rows(): void
    {
        // Annuitaet: P = 12.000, i = 6 %/12 = 0,005, n = 12.
        // q = 1,005^12 = 1,0616778118...; A = 12000*0,005*q/(q-1) = 1032,797... -> 1032,80.
        // Tilgung 1 = 1032,80 - Zins(12000*0,005 = 60,00) = 972,80.
        // Letzte Rate = Restschuld: 1027,64. Summe aller Tilgungen = 12.000,00.
        $loan = $this->makeLoan([
            'principal_amount' => '12000.00',
            'contract_end' => '2026-12-31',
            'repayment_model' => 'annuity',
        ]);
        $this->schedule->generate($loan);

        $principal = $loan->repaymentPlanItems()->where('item_type', 'principal')->orderBy('due_date')->get();
        $this->assertCount(12, $principal);
        $this->assertSame('972.80', $principal[0]->planned_amount);
        $this->assertSame('1027.64', $principal[11]->planned_amount);
        $this->assertSame('12000.00', Money::sum($principal->pluck('planned_amount')));
    }

    public function test_fee_rows_one_time_percentage_and_recurring(): void
    {
        // Gebuehren (Abschnitt 43): einmalig fester Betrag, einmalig prozentual,
        // wiederkehrend quartalsweise.
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $loan->fees()->createMany([
            ['type' => 'processing', 'name' => 'Bearbeitungsgebühr', 'amount' => '500.00', 'recurrence' => 'one_time', 'due_date' => '2026-02-15'],
            // 1,5 % von 100.000,00 = 1.500,00; ohne Faelligkeit -> Wirkungsbeginn
            ['type' => 'contract', 'name' => 'Vertragsgebühr', 'percentage' => '1.500000', 'recurrence' => 'one_time'],
            ['type' => 'administration', 'name' => 'Verwaltungsgebühr', 'amount' => '100.00', 'recurrence' => 'quarterly', 'due_date' => '2026-03-31'],
        ]);
        $this->schedule->generate($loan);

        $fees = $loan->repaymentPlanItems()->where('item_type', 'fee')->orderBy('due_date')->orderBy('planned_amount')->get();
        $this->assertCount(6, $fees); // 1 + 1 + 4 Quartale (31.03., 30.06., 30.09., 31.12.)

        $this->assertSame('2026-01-01', $fees[0]->due_date->toDateString());
        $this->assertSame('1500.00', $fees[0]->planned_amount);
        $this->assertSame('2026-02-15', $fees[1]->due_date->toDateString());
        $this->assertSame('500.00', $fees[1]->planned_amount);

        $quarterly = $fees->filter(fn ($f) => $f->planned_amount === '100.00')->values();
        $this->assertSame(
            ['2026-03-31', '2026-06-30', '2026-09-30', '2026-12-31'],
            $quarterly->map(fn ($f) => $f->due_date->toDateString())->all(),
        );
    }

    public function test_at_maturity_single_interest_row(): void
    {
        // Zum Vertragsende: eine Zinszeile; Faelligkeitstag zaehlt mit:
        // [01.01.2026, 01.01.2027) = 365 Tage -> 100000*0,06 = 6000,00.
        $loan = $this->makeLoan([
            'interest_frequency' => 'at_maturity',
            'due_date' => '2026-12-31',
        ]);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->schedule->generate($loan);

        $interest = $loan->repaymentPlanItems()->where('item_type', 'interest')->get();
        $this->assertCount(1, $interest);
        $this->assertSame('2026-12-31', $interest[0]->due_date->toDateString());
        $this->assertSame('6000.00', $interest[0]->planned_amount);
    }

    public function test_rolling_horizon_without_contract_end(): void
    {
        // Unbefristet: rollierender Horizont +12 Monate ab heute (30.08.2026):
        // vollstaendige Monatsperioden bis Faelligkeit 31.07.2027
        // -> Januar 2026 bis Juli 2027 = 19 Zeilen.
        $loan = $this->makeLoan(['repayment_model' => 'open_ended']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        $this->schedule->generate($loan);

        $interest = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get();
        $this->assertCount(19, $interest);
        $this->assertSame('2027-07-31', $interest->last()->due_date->toDateString());
        // Unbefristet: keine automatischen Tilgungszeilen.
        $this->assertSame(0, $loan->repaymentPlanItems()->where('item_type', 'principal')->count());
    }

    public function test_no_interest_rows_without_capital(): void
    {
        // Ohne Auszahlung (kein Kapitalverlauf) entstehen keine Zins-SOLL-Zeilen.
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->schedule->generate($loan);

        $this->assertSame(0, $loan->repaymentPlanItems()->where('item_type', 'interest')->count());
        $this->assertSame(1, $loan->repaymentPlanItems()->where('item_type', 'principal')->count());
    }

    public function test_roll_forward_assumed(): void
    {
        // Grundannahme Abschnitt 24: vergangene planned-Zeilen werden zu assumed,
        // Zeilen mit erfasstem IST bleiben unangetastet.
        $loan = $this->makeStandardLoan();
        $this->schedule->generate($loan);

        $feb = $loan->repaymentPlanItems()->where('item_type', 'interest')->whereDate('due_date', '2026-02-28')->first();
        $feb->update(['status' => RepaymentItemStatus::Missed, 'actual_amount' => '0.00', 'origin' => 'manual_entered']);

        $this->schedule->rollForwardAssumed($loan, Carbon::parse('2026-06-30'));

        $items = $loan->repaymentPlanItems()->where('item_type', 'interest')->orderBy('due_date')->get();
        $this->assertSame(RepaymentItemStatus::Assumed, $items[0]->status); // Januar
        $this->assertSame('assumed', $items[0]->origin->value);
        $this->assertSame(RepaymentItemStatus::Missed, $items[1]->status);  // Februar: IST bleibt
        $this->assertSame(RepaymentItemStatus::Assumed, $items[5]->status); // Juni (30.06. <= Stichtag)
        $this->assertSame(RepaymentItemStatus::Planned, $items[6]->status); // Juli bleibt geplant
    }

    public function test_removed_capital_deletes_system_interest_rows(): void
    {
        // Faellt das Kapital rueckwirkend weg (Storno), verschwinden die
        // abgeleiteten Zins-SOLL-Zeilen (nur planned/assumed) wieder.
        $loan = $this->makeStandardLoan();
        $this->schedule->generate($loan);
        $this->assertSame(12, $loan->repaymentPlanItems()->where('item_type', 'interest')->count());

        $tx = $loan->transactions()->first();
        LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'cancellation',
            'booking_date' => '2026-08-30',
            'effective_date' => $tx->effective_date->toDateString(),
            'amount' => '-100000.00',
            'reversal_of' => $tx->id,
            'description' => 'Test: Storno',
        ]);
        $this->schedule->generate($loan);

        $this->assertSame(0, $loan->repaymentPlanItems()->where('item_type', 'interest')->count());
    }
}
