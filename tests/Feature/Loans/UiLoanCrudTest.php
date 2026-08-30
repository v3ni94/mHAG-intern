<?php

namespace Tests\Feature\Loans;

use App\Enums\LoanStatus;
use App\Models\Loan;

class UiLoanCrudTest extends LoansUiTestCase
{
    public function test_index_zeigt_darlehen_mit_status_und_betrag(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($lender, $borrower);

        $response = $this->actingAs($user)->get(route('loans.index'));

        $response->assertOk();
        $response->assertSee($loan->loan_number);
        $response->assertSee('Beispiel GmbH');
        $response->assertSee('Aktiv');
        $response->assertSee('100.000,00');
    }

    public function test_index_filtert_nach_suchbegriff(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Geber');
        $borrower = $this->makeEntity('Nehmer');
        $treffer = $this->makeLoan($lender, $borrower, ['title' => 'Zwischenfinanzierung Ost']);
        $anderes = $this->makeLoan($lender, $borrower, ['title' => 'Betriebsmittel West']);

        $response = $this->actingAs($user)->get(route('loans.index', ['q' => 'Zwischenfinanzierung']));

        $response->assertOk();
        $response->assertSee($treffer->loan_number);
        $response->assertDontSee($anderes->loan_number);
    }

    public function test_create_formular_wird_angezeigt(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $this->makeEntity('Müller Holding AG');

        $response = $this->actingAs($user)->get(route('loans.create'));

        $response->assertOk();
        $response->assertSee('Neues Darlehen');
        $response->assertSee('Darlehensgeber');
        $response->assertSee('Zinssatz');
    }

    public function test_store_legt_darlehen_mit_nummer_und_zinsstaffel_an(): void
    {
        $mocks = $this->mockLoanServices();
        $mocks['schedule']->shouldReceive('generate')->once();

        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');

        $response = $this->actingAs($user)->post(route('loans.store'), [
            'title' => 'Neues Testdarlehen',
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'effective_from' => now()->addDay()->toDateString(),
            'principal_amount' => '250.000,00',
            'interest_rate' => '3,125',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
        ]);

        $loan = Loan::where('title', 'Neues Testdarlehen')->first();
        $this->assertNotNull($loan, 'Darlehen wurde nicht angelegt.');
        $response->assertRedirect(route('loans.show', $loan));

        $this->assertSame('DAR-'.now()->year.'-00001', $loan->loan_number);
        $this->assertSame(LoanStatus::Draft, $loan->status);
        $this->assertSame('250000.00', (string) $loan->principal_amount);

        // Zinssatz als erste historisierte Staffelzeile ab Wirkungsbeginn
        $term = $loan->interestTerms()->first();
        $this->assertNotNull($term, 'Zinssatz-Zeile fehlt.');
        $this->assertSame(0, bccomp('3.125', (string) $term->rate, 6));
        $this->assertSame($loan->effective_from->toDateString(), $term->valid_from->toDateString());

        $this->assertDatabaseHas('audit_logs', ['action' => 'loans.created']);
    }

    public function test_store_rueckwirkend_loest_neuberechnung_aus(): void
    {
        $mocks = $this->mockLoanServices();
        $mocks['schedule']->shouldReceive('generate')->once();
        $mocks['schedule']->shouldReceive('rollForwardAssumed')->once();
        $mocks['recalculation']->shouldReceive('recalculate')
            ->once()
            ->withArgs(fn ($loan, $trigger) => $trigger === 'loans.created_retroactively')
            ->andReturn(new \App\Models\LoanRecalculation);

        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');

        $response = $this->actingAs($user)->post(route('loans.store'), [
            'title' => 'Rückwirkendes Darlehen',
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'effective_from' => '2024-01-01',
            'principal_amount' => '100.000,00',
            'interest_rate' => '6',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loans', ['title' => 'Rückwirkendes Darlehen', 'effective_from' => '2024-01-01 00:00:00']);
    }

    public function test_store_mit_geplanter_auszahlung_ruft_disbursement_service(): void
    {
        $mocks = $this->mockLoanServices();
        $mocks['disbursement']->shouldReceive('plan')
            ->once()
            ->withArgs(fn ($loan, $data) => $data['planned_amount'] === '50000.00')
            ->andReturn(new \App\Models\LoanDisbursement);

        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Geber');
        $borrower = $this->makeEntity('Nehmer');

        $this->actingAs($user)->post(route('loans.store'), [
            'title' => 'Darlehen mit Auszahlung',
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'effective_from' => now()->addDay()->toDateString(),
            'principal_amount' => '50.000,00',
            'interest_rate' => '5',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
            'plan_disbursement' => '1',
            'disbursement_planned_amount' => '50.000,00',
            'disbursement_planned_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();
    }

    public function test_show_rendert_kopf_kpi_und_tabs(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $lender = $this->makeEntity('Müller Holding AG');
        $borrower = $this->makeEntity('Beispiel GmbH');
        $loan = $this->makeLoan($lender, $borrower);
        $loan->interestTerms()->create(['rate' => '6.000000', 'valid_from' => $loan->effective_from]);

        $response = $this->actingAs($user)->get(route('loans.show', $loan));

        $response->assertOk();
        $response->assertSee($loan->loan_number);
        $response->assertSee('Müller Holding AG');
        $response->assertSee('Beispiel GmbH');
        $response->assertSee('Gesamtforderung');
        $response->assertSee('100.500,00');
        $response->assertSee('Zahlungsplan');
        $response->assertSee('Neuberechnungen');
        $response->assertSee('Forderungsaufstellung');
    }

    public function test_show_zahlungsplan_tab_zeigt_soll_ist_und_herkunft(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));
        $loan->repaymentPlanItems()->create([
            'item_type' => 'interest',
            'due_date' => now()->subMonth()->toDateString(),
            'planned_amount' => '500.00',
            'status' => 'assumed',
            'origin' => 'assumed',
        ]);

        $response = $this->actingAs($user)->get(route('loans.show', [$loan, 'tab' => 'zahlungsplan']));

        $response->assertOk();
        $response->assertSee('500,00');
        $response->assertSee('Systemseitig angenommen');
    }

    public function test_update_aendert_stammdaten_und_loest_recalc_aus(): void
    {
        $mocks = $this->mockLoanServices();
        $mocks['recalculation']->shouldReceive('recalculate')
            ->once()
            ->withArgs(fn ($loan, $trigger) => $trigger === 'loans.updated')
            ->andReturn(new \App\Models\LoanRecalculation);

        $user = $this->makeInternalUser();
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        $response = $this->actingAs($user)->put(route('loans.update', $loan), [
            'title' => $loan->title,
            'lender_entity_id' => $loan->lender_entity_id,
            'borrower_entity_id' => $loan->borrower_entity_id,
            'effective_from' => $loan->effective_from->toDateString(),
            'principal_amount' => '120.000,00',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
        ]);

        $response->assertRedirect(route('loans.show', $loan));
        $this->assertSame('120000.00', (string) $loan->fresh()->principal_amount);
    }

    public function test_statuswechsel_ueber_transition_mit_historie(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'), [
            'status' => LoanStatus::Draft,
        ]);

        $response = $this->actingAs($user)->post(route('loans.transition', $loan), [
            'status' => 'active',
            'note' => 'Vertrag mündlich bestätigt',
        ]);

        $response->assertRedirect(route('loans.show', $loan));
        $this->assertSame(LoanStatus::Active, $loan->fresh()->status);
        $this->assertDatabaseHas('loan_status_history', [
            'loan_id' => $loan->id,
            'from_status' => 'draft',
            'to_status' => 'active',
        ]);
    }

    public function test_unzulaessiger_statuswechsel_wird_abgelehnt(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'), [
            'status' => LoanStatus::Draft,
        ]);

        $this->actingAs($user)->post(route('loans.transition', $loan), ['status' => 'repaid']);

        $this->assertSame(LoanStatus::Draft, $loan->fresh()->status);
    }
}
