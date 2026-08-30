<?php

namespace Tests\Feature\Loans;

use App\Models\LoanTransaction;
use App\Services\Loans\LoanRecalculationService;
use Illuminate\Support\Carbon;

/**
 * Oberfläche der Ertrags- und Renditeauswertung (Anforderung vom 30.08.2026):
 * Reiter am Darlehen und Report über alle sichtbaren Darlehen.
 */
class UiYieldTest extends LoansUiTestCase
{
    public function test_reiter_ertrag_zeigt_kennzahlen_und_rechenweg(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-02 09:00:00'));

        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($lender, $borrower, [
            'effective_from' => '2026-01-01',
            'contract_end' => '2026-12-31',
            'principal_amount' => '100000.00',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
        ]);
        $loan->interestTerms()->create(['rate' => '6.000000', 'valid_from' => '2026-01-01']);
        LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'disbursement',
            'booking_date' => '2026-01-01',
            'effective_date' => '2026-01-01',
            'amount' => '100000.00',
            'description' => 'Auszahlung',
        ]);
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $response = $this->actingAs($user)->get(route('loans.show', [$loan, 'tab' => 'ertrag']));

        $response->assertOk();
        $response->assertSee('Ertrag (belegt)');
        $response->assertSee('Durchschnittlich gebundenes Kapital');
        $response->assertSee('Effektivrendite (interner Zinsfuß)', false);
        $response->assertSee('Rechenweg der Rendite');
        // Kapital 100.000,00 EUR über das ganze Jahr gebunden
        $response->assertSee('100.000,00');
        // Der Hinweis auf die fachliche Zurückhaltung muss stehen
        $response->assertSee('Bewertung der Forderung und keine Prognose');
    }

    public function test_reiter_ertrag_ohne_auszahlung_weist_nichts_aus(): void
    {
        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($lender, $borrower);
        $loan->interestTerms()->create(['rate' => '6.000000', 'valid_from' => $loan->effective_from]);

        $response = $this->actingAs($user)->get(route('loans.show', [$loan, 'tab' => 'ertrag']));

        $response->assertOk();
        $response->assertSee('nicht berechenbar');
        $response->assertSee('Ohne gebundenes Kapital im Betrachtungszeitraum wird keine Rendite ausgewiesen.');
    }

    public function test_report_ertrag_und_rendite(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-02 09:00:00'));

        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($lender, $borrower, [
            'effective_from' => '2026-01-01',
            'contract_end' => '2026-12-31',
            'principal_amount' => '100000.00',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
        ]);
        $loan->interestTerms()->create(['rate' => '6.000000', 'valid_from' => '2026-01-01']);
        LoanTransaction::create([
            'loan_id' => $loan->id,
            'booking_type' => 'disbursement',
            'booking_date' => '2026-01-01',
            'effective_date' => '2026-01-01',
            'amount' => '100000.00',
            'description' => 'Auszahlung',
        ]);
        app(LoanRecalculationService::class)->recalculate($loan, 'test_setup');

        $response = $this->actingAs($user)->get(route('reports.show', ['key' => 'ertrag-rendite']));

        $response->assertOk();
        $response->assertSee('Ertrag und Rendite');
        $response->assertSee($loan->loan_number);
        $response->assertSee('Durchschnittlich gebundenes Kapital');
        $response->assertSee('davon nur angenommen');
        // Alle Zinszeilen 2026 gelten als angenommen: 6.000,00 EUR
        $response->assertSee('6.000,00');
    }

    public function test_report_ertrag_als_csv(): void
    {
        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');
        $this->makeLoan($lender, $borrower);

        $response = $this->actingAs($user)
            ->get(route('reports.show', ['key' => 'ertrag-rendite', 'format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }
}
