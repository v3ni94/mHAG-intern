<?php

namespace Tests\Feature\Loans;

use App\Enums\BookingType;
use App\Enums\LoanStatus;
use App\Models\LoanTransaction;
use App\Services\Loans\LoanBalanceService;
use App\Services\Loans\LoanDefaultService;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Support\Carbon;

/**
 * Ausfall erfassen und zurücknehmen (Anforderung vom 30.08.2026).
 *
 * Grundlage: 100.000,00 EUR valutiert am 01.01.2026, 6 % p. a., ACT/365,
 * monatliche Zinsfälligkeit, Vertragsende 31.12.2026, Systemtag 30.08.2026.
 * Ein Zinstag kostet 16,438356164... EUR.
 */
class EngineDefaultEventTest extends EngineTestCase
{
    private LoanDefaultService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 09:00:00'));
        $this->service = app(LoanDefaultService::class);
    }

    private function makeDisbursedLoan(): \App\Models\Loan
    {
        $loan = $this->makeLoan(['contract_end' => '2026-12-31']);
        $this->bookDisbursement($loan, '100000.00', '2026-01-01');
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        return $loan->fresh();
    }

    public function test_ab_dem_ausfalldatum_entstehen_keine_weiteren_sollzinsen(): void
    {
        $loan = $this->makeDisbursedLoan();
        // Vorher: zwoelf Zinszeilen bis zum Vertragsende
        $this->assertSame(12, $loan->repaymentPlanItems()->where('item_type', 'interest')->count());

        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Insolvenzantrag des Darlehensnehmers');

        $zinszeilen = $loan->fresh()->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->orderBy('due_date')
            ->get();

        // Januar bis April vollstaendig, Mai als Stummelperiode bis zum
        // Ausfalltag: 01.05. bis 15.05. = 15 Tage * 16,438356 = 246,575... = 246,58
        $this->assertSame([
            '2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-15',
        ], $zinszeilen->map(fn ($i) => $i->due_date->toDateString())->all());
        $this->assertSame('246.58', $zinszeilen->last()->planned_amount);
    }

    public function test_bereits_entstandene_zinsen_bleiben_erhalten(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Zahlungsunfähigkeit');

        $salden = app(LoanBalanceService::class)->balances($loan->fresh(), Carbon::parse('2026-08-30'));

        /*
         * Zinsen 01.01. bis 15.05.2026 = 135 Tage
         * (31 + 28 + 31 + 30 + 15), gerundet je Periode:
         * 509,59 + 460,27 + 509,59 + 493,15 + 246,58 = 2.219,18 EUR
         */
        $this->assertSame('2219.18', $salden['interest_charged']);
        $this->assertSame('100000.00', $salden['principal_outstanding'], 'Ohne Abschreibung bleibt das Kapital.');
    }

    public function test_status_und_grund_werden_gesetzt(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Insolvenzverfahren eröffnet');

        $loan = $loan->fresh();
        $this->assertSame(LoanStatus::Defaulted, $loan->status);
        $this->assertSame('2026-05-15', $loan->defaulted_on->toDateString());
        $this->assertSame('Insolvenzverfahren eröffnet', $loan->default_reason);

        // Statushistorie mit Wirkungsdatum
        $eintrag = $loan->statusHistory()->latest('id')->firstOrFail();
        $this->assertSame('defaulted', $eintrag->to_status);
        $this->assertSame('2026-05-15', $eintrag->effective_date->toDateString());

        $this->assertDatabaseHas('audit_logs', ['action' => 'loans.default_recorded']);
    }

    public function test_ohne_betrag_bleibt_die_forderung_bestehen(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Ausfall ohne Abschreibung');

        $this->assertSame(
            0,
            LoanTransaction::where('loan_id', $loan->id)
                ->where('booking_type', BookingType::WriteOff->value)
                ->count(),
        );

        $salden = app(LoanBalanceService::class)->balances($loan->fresh(), Carbon::parse('2026-08-30'));
        // Die Zinsen bis zum Ausfall gelten als systemseitig angenommen
        // erfuellt; offen ist das Kapital. Entscheidend fuer diesen Test:
        // ohne Abschreibungsbetrag besteht die Forderung unveraendert.
        $this->assertSame('100000.00', $salden['principal_outstanding']);
    }

    public function test_abschreibung_reduziert_die_forderung(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Teilausfall', '25000.00');

        $buchung = LoanTransaction::where('loan_id', $loan->id)
            ->where('booking_type', BookingType::WriteOff->value)
            ->firstOrFail();

        // Forderungssicht: die Abschreibung reduziert die Forderung
        $this->assertSame('-25000.00', $buchung->amount);
        $this->assertSame('2026-05-15', $buchung->effective_date->toDateString());

        $salden = app(LoanBalanceService::class)->balances($loan->fresh(), Carbon::parse('2026-08-30'));
        // 100.000,00 - 25.000,00 = 75.000,00
        $this->assertSame('75000.00', $salden['principal_outstanding']);
        $this->assertSame('25000.00', $salden['written_off']);
    }

    public function test_abschreibung_wirkt_erst_ab_dem_ausfalldatum(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Teilausfall', '25000.00');
        $balance = app(LoanBalanceService::class);

        $this->assertSame(
            '100000.00',
            $balance->balances($loan->fresh(), Carbon::parse('2026-05-14'))['principal_outstanding'],
        );
        $this->assertSame(
            '75000.00',
            $balance->balances($loan->fresh(), Carbon::parse('2026-05-15'))['principal_outstanding'],
        );
    }

    public function test_ruecknahme_hebt_die_abschreibung_per_gegenbuchung_auf(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Teilausfall', '25000.00');

        $abschreibung = LoanTransaction::where('loan_id', $loan->id)
            ->where('booking_type', BookingType::WriteOff->value)
            ->firstOrFail();

        $anzahl = $this->service->revoke($loan->fresh(), true, 'Vergleich geschlossen');

        $this->assertSame(1, $anzahl);
        // Append-only: die Originalbuchung bleibt, die Gegenbuchung kommt hinzu
        $this->assertDatabaseHas('loan_transactions', ['id' => $abschreibung->id]);
        $this->assertDatabaseHas('loan_transactions', ['reversal_of' => $abschreibung->id]);

        $loan = $loan->fresh();
        $this->assertNull($loan->defaulted_on);
        $this->assertNull($loan->default_reason);
        $this->assertSame(LoanStatus::Active, $loan->status);

        $salden = app(LoanBalanceService::class)->balances($loan, Carbon::parse('2026-08-30'));
        $this->assertSame('100000.00', $salden['principal_outstanding'], 'Keine Doppelzählung nach dem Storno.');
        $this->assertSame('0.00', $salden['written_off']);
    }

    public function test_ruecknahme_laesst_die_abschreibung_auf_wunsch_stehen(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Teilausfall', '25000.00');

        $anzahl = $this->service->revoke($loan->fresh(), false, 'Abschreibung bleibt');

        $this->assertSame(0, $anzahl);
        $salden = app(LoanBalanceService::class)->balances($loan->fresh(), Carbon::parse('2026-08-30'));
        $this->assertSame('75000.00', $salden['principal_outstanding']);
    }

    public function test_nach_der_ruecknahme_laufen_die_sollzinsen_weiter(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Ausfall');
        $this->assertSame(5, $loan->fresh()->repaymentPlanItems()->where('item_type', 'interest')->count());

        $this->service->revoke($loan->fresh(), true, null);

        // Wieder alle zwoelf Perioden bis zum Vertragsende. Die
        // Stummelperiode bis zum Ausfalltag bleibt nicht zurueck: sie war
        // geplant und wird durch die vollstaendige Maiperiode ersetzt.
        $zeilen = $loan->fresh()->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->orderBy('due_date')
            ->get();
        $this->assertSame(12, $zeilen->count());
        $this->assertSame('2026-05-31', $zeilen[4]->due_date->toDateString());
        $this->assertSame('509.59', $zeilen[4]->planned_amount);
    }

    public function test_erneute_ruecknahme_erzeugt_keine_zweite_gegenbuchung(): void
    {
        $loan = $this->makeDisbursedLoan();
        $this->service->record($loan, Carbon::parse('2026-05-15'), 'Teilausfall', '25000.00');
        $this->service->revoke($loan->fresh(), true, null);

        $this->assertSame(0, $this->service->revoke($loan->fresh(), true, null));
        $this->assertSame(
            1,
            LoanTransaction::where('loan_id', $loan->id)
                ->where('booking_type', BookingType::Cancellation->value)
                ->count(),
        );
    }
}
