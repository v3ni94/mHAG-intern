<?php

namespace Tests\Feature\Loans;

use App\Enums\InterestDueDayMode;
use App\Enums\PaymentOrigin;
use App\Enums\RepaymentItemStatus;
use App\Models\Loan;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Support\Carbon;

/**
 * Einstellbarer Fälligkeitstag der Zinsen (Anforderung vom 30.08.2026).
 *
 * Grundlage aller Erwartungswerte: 100.000,00 EUR valutiert am 01.01.2026,
 * 6 % p. a., ACT/365. Ein Zinstag kostet damit
 * 100.000,00 * 6 / 100 / 365 = 16,438356164... EUR.
 * Die Periode endet am Fälligkeitstag einschließlich, die nächste beginnt
 * am Folgetag.
 */
class EngineInterestDueDayTest extends EngineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00'));
    }

    /** Darlehen mit Auszahlung zum Wirkungsbeginn und erzeugtem Zahlungsplan. */
    private function planLoan(array $overrides, string $disbursementDate): Loan
    {
        $loan = $this->makeLoan($overrides);
        $this->bookDisbursement($loan, '100000.00', $disbursementDate);
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        return $loan->fresh();
    }

    /** @return array<int, array{0: string, 1: string}> Fälligkeit und Betrag */
    private function interestRows(Loan $loan): array
    {
        return $loan->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->orderBy('due_date')
            ->get()
            ->map(fn ($i) => [$i->due_date->toDateString(), $i->planned_amount])
            ->all();
    }

    public function test_standard_bleibt_unveraendert(): void
    {
        // Bestehende Darlehen dürfen sich durch die neue Einstellung nicht
        // verändern: Raster aus dem Wirkungsbeginn, Fälligkeit am Vortag
        // des gleichen Tages der Folgeperiode.
        $loan = $this->planLoan(['contract_end' => '2026-04-30'], '2026-01-01');

        $this->assertSame(InterestDueDayMode::EffectiveFrom, $loan->interest_due_day_mode);
        $this->assertSame([
            // Januar: 01.01. bis 31.01. = 31 Tage * 16,438356 = 509,589... = 509,59
            ['2026-01-31', '509.59'],
            // Februar: 01.02. bis 28.02. = 28 Tage = 460,273... = 460,27
            ['2026-02-28', '460.27'],
            // März: 31 Tage = 509,59
            ['2026-03-31', '509.59'],
            // April: 30 Tage = 493,150... = 493,15
            ['2026-04-30', '493.15'],
        ], $this->interestRows($loan));
    }

    public function test_monatsletzter_erzeugt_kalendermonate(): void
    {
        // Wirkungsbeginn 15.01.2026, Fälligkeit jeweils zum Monatsletzten.
        // Erste Periode 15.01. bis 31.01. = 17 Tage (Stummelperiode).
        $loan = $this->planLoan([
            'effective_from' => '2026-01-15',
            'contract_end' => '2026-03-31',
            'interest_due_day_mode' => 'month_end',
        ], '2026-01-15');

        $this->assertSame([
            // 17 Tage * 16,438356 = 279,452... = 279,45
            ['2026-01-31', '279.45'],
            // Februar vollständig: 28 Tage = 460,27
            ['2026-02-28', '460.27'],
            // März vollständig: 31 Tage = 509,59
            ['2026-03-31', '509.59'],
        ], $this->interestRows($loan));
    }

    public function test_fester_tag_im_monat(): void
    {
        // Fälligkeit jeweils zum 15. Erste Periode 01.01. bis 15.01. = 15 Tage,
        // danach 16.01. bis 15.02. = 31 Tage, 16.02. bis 15.03. = 28 Tage.
        $loan = $this->planLoan([
            'contract_end' => '2026-03-15',
            'interest_due_day_mode' => 'fixed_day',
            'interest_due_day' => 15,
        ], '2026-01-01');

        $this->assertSame([
            // 15 Tage = 246,575... = 246,58
            ['2026-01-15', '246.58'],
            // 31 Tage = 509,59
            ['2026-02-15', '509.59'],
            // 28 Tage = 460,27
            ['2026-03-15', '460.27'],
        ], $this->interestRows($loan));
    }

    public function test_fester_tag_liegt_im_ersten_monat_vor_dem_beginn(): void
    {
        // Wirkungsbeginn 20.01.2026, Fälligkeit zum 15.: der 15.01. liegt vor
        // dem Beginn, erste Fälligkeit ist deshalb der 15.02.2026.
        // Erste Periode 20.01. bis 15.02. = 27 Tage.
        $loan = $this->planLoan([
            'effective_from' => '2026-01-20',
            'contract_end' => '2026-03-15',
            'interest_due_day_mode' => 'fixed_day',
            'interest_due_day' => 15,
        ], '2026-01-20');

        $this->assertSame([
            // 27 Tage = 443,835... = 443,84
            ['2026-02-15', '443.84'],
            // 16.02. bis 15.03. = 28 Tage = 460,27
            ['2026-03-15', '460.27'],
        ], $this->interestRows($loan));
    }

    public function test_jaehrliche_zinsen_mit_festem_tag(): void
    {
        // Jährlich zum 15., Wirkungsbeginn 20.01.2026: der 15.01.2026 liegt
        // vor dem Beginn, erste Fälligkeit ist der 15.01.2027.
        // Periode 20.01.2026 bis 15.01.2027 = 361 Tage.
        $loan = $this->planLoan([
            'effective_from' => '2026-01-20',
            'contract_end' => '2027-01-15',
            'interest_frequency' => 'annual',
            'interest_due_day_mode' => 'fixed_day',
            'interest_due_day' => 15,
        ], '2026-01-20');

        $this->assertSame([
            // 361 Tage * 16,438356 = 5.934,246... = 5.934,25
            ['2027-01-15', '5934.25'],
        ], $this->interestRows($loan));
    }

    public function test_jaehrliche_zinsen_zum_monatsletzten(): void
    {
        // Jährlich zum Monatsletzten, Wirkungsbeginn 10.03.2026:
        // erste Fälligkeit 31.03.2026 (22 Tage), dann 31.03.2027 (365 Tage).
        $loan = $this->planLoan([
            'effective_from' => '2026-03-10',
            'contract_end' => '2027-03-31',
            'interest_frequency' => 'annual',
            'interest_due_day_mode' => 'month_end',
        ], '2026-03-10');

        $this->assertSame([
            // 22 Tage = 361,643... = 361,64
            ['2026-03-31', '361.64'],
            // 01.04.2026 bis 31.03.2027 = 365 Tage = 6.000,00
            ['2027-03-31', '6000.00'],
        ], $this->interestRows($loan));
    }

    public function test_fester_tag_ohne_erfassten_tag_faellt_auf_standard_zurueck(): void
    {
        // Unvollständige Vorgabe darf nicht geraten werden und darf die
        // Plangenerierung nicht abbrechen: es bleibt beim Standardverhalten.
        $loan = $this->planLoan([
            'contract_end' => '2026-02-28',
            'interest_due_day_mode' => 'fixed_day',
            'interest_due_day' => null,
        ], '2026-01-01');

        $this->assertSame([
            ['2026-01-31', '509.59'],
            ['2026-02-28', '460.27'],
        ], $this->interestRows($loan));
    }

    public function test_tilgungsraten_folgen_demselben_raster(): void
    {
        // Ratentilgung, Fälligkeit zum 1., Wirkungsbeginn 10.01.2026:
        // der 01.01. liegt vor dem Beginn, erste Fälligkeit 01.02.2026.
        // Letzte Rate zum Vertragsende 30.06.2026 (Stummelperiode).
        $loan = $this->planLoan([
            'effective_from' => '2026-01-10',
            'contract_end' => '2026-06-30',
            'principal_amount' => '50000.00',
            'repayment_model' => 'installment',
            'interest_due_day_mode' => 'fixed_day',
            'interest_due_day' => 1,
        ], '2026-01-10');

        $tilgungen = $loan->repaymentPlanItems()
            ->where('item_type', 'principal')
            ->orderBy('due_date')
            ->get();

        $this->assertSame(
            ['2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01', '2026-06-30'],
            $tilgungen->map(fn ($i) => $i->due_date->toDateString())->all(),
        );
        // 50.000,00 / 6 = 8.333,33 je Rate, letzte Rate als Restbetrag:
        // 50.000,00 - 5 * 8.333,33 = 8.333,35
        $this->assertSame(
            ['8333.33', '8333.33', '8333.33', '8333.33', '8333.33', '8333.35'],
            $tilgungen->map(fn ($i) => $i->planned_amount)->all(),
        );
        $this->assertSame('50000.00', \App\Support\Money::sum($tilgungen->pluck('planned_amount')));
    }

    public function test_umstellung_ueberschreibt_keine_bestaetigte_zeile(): void
    {
        // Eiserne Regel: Zeilen mit erfasstem IST werden nie überschrieben
        // oder gelöscht. Die Umstellung wirkt nur auf geplante Zeilen.
        $loan = $this->planLoan(['contract_end' => '2026-04-30'], '2026-01-01');

        $januar = $loan->repaymentPlanItems()
            ->where('item_type', 'interest')
            ->whereDate('due_date', '2026-01-31')
            ->firstOrFail();
        $januar->update([
            'actual_amount' => '509.59',
            'actual_date' => '2026-01-31',
            'status' => RepaymentItemStatus::Confirmed,
            'origin' => PaymentOrigin::ManualConfirmed,
        ]);

        $loan->update([
            'interest_due_day_mode' => 'fixed_day',
            'interest_due_day' => 15,
        ]);
        app(LoanRecalculationService::class)->recalculate($loan->fresh(), 'test_umstellung');

        $zeilen = $this->interestRows($loan->fresh());
        $faelligkeiten = array_column($zeilen, 0);

        $this->assertContains('2026-01-31', $faelligkeiten, 'Die bestätigte Zeile muss erhalten bleiben.');
        $this->assertContains('2026-02-15', $faelligkeiten, 'Das neue Raster muss greifen.');
        $this->assertContains('2026-03-15', $faelligkeiten);

        $bestaetigt = $loan->repaymentPlanItems()
            ->whereDate('due_date', '2026-01-31')
            ->where('item_type', 'interest')
            ->firstOrFail();
        $this->assertSame('509.59', $bestaetigt->actual_amount);
        $this->assertSame(RepaymentItemStatus::Confirmed, $bestaetigt->status);
    }
}
