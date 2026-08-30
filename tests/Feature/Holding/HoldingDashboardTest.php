<?php

namespace Tests\Feature\Holding;

use App\Models\Investment;

/**
 * Holding-Dashboard (Abschnitt 106): KPI-Karten und Widgets.
 */
class HoldingDashboardTest extends HoldingTestCase
{
    public function test_dashboard_zeigt_kpis_und_aktionaersstruktur(): void
    {
        $this->actingAs($this->admin());

        Investment::create([
            'company_entity_id' => $this->mhagEntityId(),
            'share_percentage' => '100.000000',
            'status' => 'active',
        ]);

        $this->get(route('holding.dashboard'))
            ->assertOk()
            ->assertSee('Grundkapital')
            ->assertSee('100.000,00 EUR')
            ->assertSee('Aktionäre')
            ->assertSee('Beteiligungen')
            ->assertSee('Offene Beschlüsse')
            ->assertSee('Offene Signaturen')
            ->assertSee('Aktionärsstruktur')
            ->assertSee('Timo Müller')
            ->assertSee('AB-INITIAL-0001');
    }

    public function test_dashboard_erfordert_shares_view(): void
    {
        $user = \App\Models\User::factory()->create(['is_active' => true]);
        $user->assignRole('Darlehensnehmer');

        $this->actingAs($user);
        $this->get(route('holding.dashboard'))->assertForbidden();
    }

    public function test_aktionaersliste_index_mit_stichtag(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('shareholders.index'))
            ->assertOk()
            ->assertSee('Timo Müller')
            ->assertSee('100.000');

        // Historischer Stichtag vor der Ersterfassung: Bestand 0
        $this->get(route('shareholders.index', ['as_of' => now()->subYear()->toDateString()]))
            ->assertOk()
            ->assertSee('Historischer Stand');
    }
}
