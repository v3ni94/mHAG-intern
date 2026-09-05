<?php

namespace Tests\Feature\Holding;

use App\Models\Resolution;
use App\Models\User;

/**
 * Beschlussregister (Abschnitt 98): Filter nach Jahr, Organ, Status und
 * Volltext sowie PDF-Export mit übernommenen Filtern.
 */
class ResolutionRegisterTest extends HoldingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Resolution::create([
            'resolution_number' => 'VOR-2025-001',
            'title' => 'Kauf Objekt Rheinpromenade',
            'company_entity_id' => $this->mhagEntityId(),
            'type' => 'board',
            'resolved_on' => '2025-06-01',
            'recorded_at' => now(),
            'status' => 'accepted',
            'result' => 'accepted',
        ]);

        Resolution::create([
            'resolution_number' => 'AR-2026-001',
            'title' => 'Zustimmung Darlehensvergabe',
            'company_entity_id' => $this->mhagEntityId(),
            'type' => 'supervisory_board',
            'resolved_on' => '2026-02-10',
            'recorded_at' => now(),
            'status' => 'voting',
        ]);
    }

    public function test_register_zeigt_alle_beschluesse(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('resolutions.index'))
            ->assertOk()
            ->assertSee('VOR-2025-001')
            ->assertSee('AR-2026-001');
    }

    public function test_filter_nach_jahr(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('resolutions.index', ['year' => 2025]))
            ->assertOk()
            ->assertSee('VOR-2025-001')
            ->assertDontSee('AR-2026-001');
    }

    public function test_filter_nach_organ_und_status(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('resolutions.index', ['type' => 'supervisory_board']))
            ->assertOk()
            ->assertSee('AR-2026-001')
            ->assertDontSee('VOR-2025-001');

        $this->get(route('resolutions.index', ['status' => 'accepted']))
            ->assertOk()
            ->assertSee('VOR-2025-001')
            ->assertDontSee('AR-2026-001');
    }

    public function test_volltextsuche(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('resolutions.index', ['q' => 'Rheinpromenade']))
            ->assertOk()
            ->assertSee('VOR-2025-001')
            ->assertDontSee('AR-2026-001');
    }

    public function test_pdf_export_liefert_pdf(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get(route('resolutions.index', ['format' => 'pdf', 'year' => 2025]));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_register_erfordert_berechtigung(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        // Rolle ohne resolutions.view
        $user->assignRole('Darlehensnehmer');

        $this->actingAs($user);
        $this->get(route('resolutions.index'))->assertForbidden();
    }
}
