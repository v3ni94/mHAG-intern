<?php

namespace Tests\Feature\Holding;

use App\Enums\EntityScopeMode;
use App\Models\CorporateBody;
use App\Models\Entity;
use App\Models\Investment;
use App\Models\Resolution;
use App\Models\Setting;
use App\Models\Shareholder;
use App\Models\ShareTransaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Datenscope im Holding-Bereich (Entscheidung vom 05.09.2026).
 *
 * Bis dahin hatte kein Modell dieses Bereichs eine Einschränkung nach
 * Gesellschaft. Für die Rolle Partner ohne Folgen, weil dort schon die
 * Berechtigung sperrt. Die externen Aufsichtsratsrollen besitzen aber
 * shares.view und resolutions.view; ein je Benutzer gesetzter Ausschluss
 * wirkte dort nicht. Ein Aufsichtsratsmitglied einer Gesellschaft sah den
 * Holding-Bereich der ganzen Gruppe.
 *
 * Besonderheit, die den Ausschlag gab: Aktionäre und Aktienbewegungen führen
 * keine Gesellschaft als Feld, weil es nur eine gibt. Ohne eigene Regel hätte
 * ein Ausschluss der Müller Holding AG das Aktienregister nicht verborgen,
 * obwohl es genau ihre Angelegenheit ist.
 */
class HoldingScopeTest extends TestCase
{
    use RefreshDatabase;

    private Entity $holding;

    private Entity $fremd;

    private Entity $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);

        $this->holding = Entity::create(['type' => 'company', 'display_name' => 'Müller Holding AG', 'status' => 'active']);
        $this->fremd = Entity::create(['type' => 'company', 'display_name' => 'Beispiel GmbH', 'status' => 'active']);
        $this->person = Entity::create(['type' => 'person', 'display_name' => 'Timo Müller', 'status' => 'active']);

        Setting::set('holding', 'company_entity_id', $this->holding->id);
    }

    /** Aufsichtsratsmitglied mit Ausschluss der genannten Gesellschaften. */
    private function aufsichtsrat(array $ausgeschlossen): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'entity_scope_mode' => EntityScopeMode::Exclude->value,
        ]);
        $user->assignRole('Aufsichtsratsmitglied');
        foreach ($ausgeschlossen as $entity) {
            $user->entityAssignments()->create(['entity_id' => $entity->id, 'context' => 'self']);
        }

        return $user->fresh(['roles', 'entityAssignments']);
    }

    private function intern(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Administrator');

        return $user->fresh();
    }

    private function aktionaer(): Shareholder
    {
        return Shareholder::create([
            'shareholder_number' => 'AK-'.random_int(1000, 9999),
            'entity_id' => $this->person->id,
            'status' => 'active',
        ]);
    }

    private function beschluss(Entity $gesellschaft, string $nummer): Resolution
    {
        return Resolution::create([
            'resolution_number' => $nummer,
            'title' => 'Beschluss '.$gesellschaft->display_name,
            'company_entity_id' => $gesellschaft->id,
            'type' => 'board',
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function das_aktienregister_ist_bei_ausschluss_der_holding_verborgen(): void
    {
        $aktionaer = $this->aktionaer();
        $user = $this->aufsichtsrat([$this->holding]);

        $this->assertSame(0, Shareholder::query()->visibleTo($user)->count(),
            'Anteile an der ausgeschlossenen Gesellschaft duerfen nicht sichtbar sein.');

        $this->actingAs($user)->get(route('shareholders.index'))
            ->assertOk()
            ->assertDontSee($aktionaer->shareholder_number);
    }

    #[Test]
    public function ohne_ausschluss_der_holding_bleibt_das_register_sichtbar(): void
    {
        // Gegenprobe: Der Ausschluss einer anderen Gesellschaft darf das
        // Aktienregister nicht verbergen.
        $aktionaer = $this->aktionaer();
        $user = $this->aufsichtsrat([$this->fremd]);

        $this->assertSame(1, Shareholder::query()->visibleTo($user)->count());

        $this->actingAs($user)->get(route('shareholders.index'))
            ->assertOk()
            ->assertSee($aktionaer->shareholder_number);
    }

    #[Test]
    public function aktienbewegungen_folgen_derselben_regel(): void
    {
        $aktionaer = $this->aktionaer();
        $bewegung = ShareTransaction::create([
            'transaction_number' => 'AB-2026-00001',
            'type' => 'purchase',
            'buyer_shareholder_id' => $aktionaer->id,
            'share_count' => 100,
            'economic_transfer_date' => '2026-01-01',
            'status' => 'effective',
        ]);

        $mitAusschluss = $this->aufsichtsrat([$this->holding]);
        $ohneAusschluss = $this->aufsichtsrat([$this->fremd]);

        $this->assertSame(0, ShareTransaction::query()->visibleTo($mitAusschluss)->count());
        $this->assertSame(1, ShareTransaction::query()->visibleTo($ohneAusschluss)->count());

        $this->actingAs($mitAusschluss)->get(route('share-transactions.index'))
            ->assertOk()
            ->assertDontSee($bewegung->transaction_number);
    }

    #[Test]
    public function beschluesse_haengen_an_der_beschliessenden_gesellschaft(): void
    {
        $eigener = $this->beschluss($this->fremd, 'BS-2026-00001');
        $fremder = $this->beschluss($this->holding, 'BS-2026-00002');

        $user = $this->aufsichtsrat([$this->holding]);

        $this->actingAs($user)->get(route('resolutions.index'))
            ->assertOk()
            ->assertSee($eigener->resolution_number)
            ->assertDontSee($fremder->resolution_number);
    }

    #[Test]
    public function ein_ausgeschlossener_beschluss_ist_auch_direkt_nicht_abrufbar(): void
    {
        // Das Route-Binding laedt ohne Pruefung. Ohne Wache war jeder
        // Beschluss ueber die Adresszeile abrufbar.
        $fremder = $this->beschluss($this->holding, 'BS-2026-00003');
        $user = $this->aufsichtsrat([$this->holding]);

        $this->actingAs($user)->get(route('resolutions.show', $fremder))->assertNotFound();
    }

    #[Test]
    public function abstimmen_zu_einem_ausgeschlossenen_beschluss_wird_abgewiesen(): void
    {
        // Schreibender Weg.
        $fremder = $this->beschluss($this->holding, 'BS-2026-00004');
        $user = $this->aufsichtsrat([$this->holding]);

        $this->actingAs($user)
            ->post(route('resolutions.vote', $fremder), ['votes' => [1 => 'yes']])
            ->assertForbidden();
    }

    #[Test]
    public function organe_haengen_an_ihrer_gesellschaft(): void
    {
        CorporateBody::create([
            'company_entity_id' => $this->holding->id,
            'type' => 'board',
            'name' => 'Vorstand der Holding',
        ]);
        CorporateBody::create([
            'company_entity_id' => $this->fremd->id,
            'type' => 'board',
            'name' => 'Geschäftsführung Beispiel',
        ]);

        $user = $this->aufsichtsrat([$this->holding]);

        $this->assertSame(1, CorporateBody::query()->visibleTo($user)->count());
        $this->assertSame('Geschäftsführung Beispiel',
            CorporateBody::query()->visibleTo($user)->value('name'));
    }

    #[Test]
    public function beteiligungen_haengen_an_der_gehaltenen_gesellschaft(): void
    {
        Investment::create(['company_entity_id' => $this->fremd->id, 'status' => 'active']);
        Investment::create(['company_entity_id' => $this->holding->id, 'status' => 'active']);

        $user = $this->aufsichtsrat([$this->holding]);

        $this->assertSame(1, Investment::query()->visibleTo($user)->count());
        $this->assertSame($this->fremd->id, Investment::query()->visibleTo($user)->value('company_entity_id'));
    }

    #[Test]
    public function interne_rollen_sehen_unveraendert_den_gesamtbestand(): void
    {
        $this->aktionaer();
        $this->beschluss($this->holding, 'BS-2026-00005');
        CorporateBody::create(['company_entity_id' => $this->holding->id, 'type' => 'board', 'name' => 'Vorstand']);

        $intern = $this->intern();

        $this->assertSame(1, Shareholder::query()->visibleTo($intern)->count());
        $this->assertSame(1, Resolution::query()->visibleTo($intern)->count());
        $this->assertSame(1, CorporateBody::query()->visibleTo($intern)->count());
    }

    #[Test]
    public function ohne_hinterlegte_holding_gesellschaft_bleibt_der_bereich_verschlossen(): void
    {
        /*
         * Eine fehlende Einstellung darf eine Schranke nicht stillschweigend
         * aufheben. Interne Rollen sind davon nicht betroffen.
         */
        $this->aktionaer();
        Setting::set('holding', 'company_entity_id', null);

        $extern = $this->aufsichtsrat([$this->fremd]);

        $this->assertSame(0, Shareholder::query()->visibleTo($extern)->count());
        $this->assertSame(1, Shareholder::query()->visibleTo($this->intern())->count());
    }
}
