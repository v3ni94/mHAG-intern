<?php

namespace Tests\Feature\Loans;

/**
 * Fälligkeiten (Abschnitt 72-nah), Liquiditätsplanung (Abschnitt 71),
 * Sicherheiten-Übersicht mit Ablaufwarnung (Abschnitt 66).
 */
class UiDueItemsAndLiquidityTest extends LoansUiTestCase
{
    public function test_faelligkeiten_zeigen_ueberfaellige_und_kommende_positionen(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser('Buchhaltung');
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        $loan->repaymentPlanItems()->create([
            'item_type' => 'interest',
            'due_date' => now()->subMonth()->toDateString(),
            'planned_amount' => '777.77',
            'status' => 'missed',
            'origin' => 'manual_confirmed',
        ]);
        $loan->repaymentPlanItems()->create([
            'item_type' => 'principal',
            'due_date' => now()->addDays(10)->toDateString(),
            'planned_amount' => '888.88',
            'status' => 'planned',
            'origin' => 'assumed',
        ]);

        $response = $this->actingAs($user)->get(route('due-items.index'));

        $response->assertOk();
        $response->assertSee('Überfällig');
        $response->assertSee('777,77');
        $response->assertSee('Kommend');
        $response->assertSee('888,88');
        $response->assertSee('Nicht bezahlt');
    }

    public function test_liquiditaet_summiert_offene_positionen_und_geplante_auszahlungen(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        $loan->repaymentPlanItems()->create([
            'item_type' => 'interest',
            'due_date' => now()->addMonth()->startOfMonth()->addDays(4)->toDateString(),
            'planned_amount' => '500.00',
            'status' => 'planned',
            'origin' => 'assumed',
        ]);
        $loan->disbursements()->create([
            'planned_amount' => '10000.00',
            'planned_date' => now()->addMonths(2)->startOfMonth()->addDays(9)->toDateString(),
            'status' => 'planned',
            'origin' => 'assumed',
        ]);

        $response = $this->actingAs($user)->get(route('liquidity.index', ['preset' => 'next12']));

        $response->assertOk();
        $response->assertSee('Liquiditätsplanung');
        $response->assertSee('500,00');
        $response->assertSee('10.000,00');
        $response->assertSee('liquidityChart', false);
    }

    public function test_sicherheiten_uebersicht_mit_ablaufwarnung(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));
        $provider = $this->makeEntity('Sicherungsgeber GmbH');

        $loan->securities()->create([
            'provider_entity_id' => $provider->id,
            'type' => 'land_charge',
            'nominal_value' => '50000.00',
            'valid_until' => now()->addDays(30)->toDateString(),
            'status' => 'active',
        ]);
        $loan->guarantees()->create([
            'guarantor_entity_id' => $provider->id,
            'guarantee_type' => 'selbstschuldnerisch',
            'max_amount' => '25000.00',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('securities.index'));

        $response->assertOk();
        $response->assertSee('Grundschuld');
        $response->assertSee('Läuft ab am');
        $response->assertSee('Bürgschaften');
        $response->assertSee('25.000,00');
    }

    public function test_forderungsaufstellung_liefert_pdf(): void
    {
        $this->mockLoanServices();
        $user = $this->makeInternalUser();
        $loan = $this->makeLoan($this->makeEntity('Geber'), $this->makeEntity('Nehmer'));

        $response = $this->actingAs($user)->get(route('loans.statement', [$loan, 'date' => now()->toDateString()]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Forderungsaufstellung-'.$loan->loan_number, (string) $response->headers->get('content-disposition'));
    }
}
