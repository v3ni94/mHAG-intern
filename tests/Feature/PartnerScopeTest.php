<?php

namespace Tests\Feature;

use App\Enums\EntityScopeMode;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rolle "Partner" mit Ausschlussmodus (Anforderung vom 30.08.2026).
 *
 * Fachliche Vorgabe: Ein Partner soll den Bestand sehen und bearbeiten
 * können, mit Ausnahme einzelner Gesellschaften, zum Beispiel "alles außer
 * Müller Holding".
 *
 * Sicherheitsentscheidung, die hier abgesichert wird: Ein Vorgang bleibt
 * verborgen, sobald eine ausgeschlossene Gesellschaft daran beteiligt ist.
 * Andernfalls wären die Geschäfte der ausgeschlossenen Gesellschaft über die
 * Gegenseite doch einsehbar.
 */
class PartnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private Entity $holding;

    private Entity $fremdA;

    private Entity $fremdB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);

        $this->holding = Entity::create(['type' => 'company', 'display_name' => 'Müller Holding AG']);
        $this->fremdA = Entity::create(['type' => 'company', 'display_name' => 'Beispiel GmbH']);
        $this->fremdB = Entity::create(['type' => 'person', 'display_name' => 'David Enns']);
    }

    /** Partner mit Ausschluss der genannten Gesellschaften. */
    private function partner(array $ausgeschlossen = [], ?Entity $eigene = null): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'entity_id' => $eigene?->id,
            'entity_scope_mode' => EntityScopeMode::Exclude->value,
        ]);
        $user->assignRole('Partner');

        foreach ($ausgeschlossen as $entity) {
            $user->entityAssignments()->create(['entity_id' => $entity->id, 'context' => 'self']);
        }

        return $user->fresh(['roles', 'entityAssignments']);
    }

    private function makeDocument(string $name): Document
    {
        return Document::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'original_filename' => $name,
            'stored_filename' => \Illuminate\Support\Str::random(8).'.pdf',
            'doc_type' => 'other',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', $name),
            'storage_disk' => 'documents',
            'storage_path' => 'test/'.$name,
        ]);
    }

    private function makeLoan(Entity $lender, Entity $borrower, string $number): Loan
    {
        return Loan::create([
            'loan_number' => $number,
            'title' => 'Darlehen '.$number,
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'loan_type_id' => LoanType::first()?->id,
            'effective_from' => '2026-01-01',
            'principal_amount' => '100000.00',
            'currency' => 'EUR',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
            'status' => 'active',
        ]);
    }

    public function test_partner_sieht_alles_ausser_der_ausgeschlossenen_gesellschaft(): void
    {
        $mitHolding = $this->makeLoan($this->holding, $this->fremdA, 'DAR-2026-00001');
        $ohneHolding = $this->makeLoan($this->fremdA, $this->fremdB, 'DAR-2026-00002');

        $partner = $this->partner([$this->holding]);
        $sichtbar = Loan::visibleTo($partner)->pluck('loan_number')->all();

        $this->assertSame(['DAR-2026-00002'], $sichtbar);
        $this->assertNotContains($mitHolding->loan_number, $sichtbar);
        $this->assertContains($ohneHolding->loan_number, $sichtbar);
    }

    public function test_ausschluss_greift_auf_beiden_seiten(): void
    {
        // Auch wenn die ausgeschlossene Gesellschaft Darlehensnehmer ist,
        // bleibt der Vorgang verborgen.
        $this->makeLoan($this->fremdA, $this->holding, 'DAR-2026-00003');

        $partner = $this->partner([$this->holding]);

        $this->assertSame(0, Loan::visibleTo($partner)->count());
    }

    public function test_partner_sieht_alle_gesellschaften_ausser_der_ausgeschlossenen(): void
    {
        $partner = $this->partner([$this->holding]);

        $namen = Entity::visibleTo($partner)->pluck('display_name')->all();

        $this->assertContains('Beispiel GmbH', $namen);
        $this->assertContains('David Enns', $namen);
        $this->assertNotContains('Müller Holding AG', $namen);
    }

    public function test_spaeter_angelegte_gesellschaft_ist_automatisch_sichtbar(): void
    {
        $partner = $this->partner([$this->holding]);
        $this->assertSame(2, Entity::visibleTo($partner)->count());

        Entity::create(['type' => 'company', 'display_name' => 'Neue Beteiligung GmbH']);

        $this->assertSame(3, Entity::visibleTo($partner->fresh(['roles', 'entityAssignments']))->count());
    }

    public function test_ohne_ausschluss_sieht_der_partner_den_gesamtbestand(): void
    {
        $this->makeLoan($this->holding, $this->fremdA, 'DAR-2026-00004');
        $partner = $this->partner();

        $this->assertSame(3, Entity::visibleTo($partner)->count());
        $this->assertSame(1, Loan::visibleTo($partner)->count());
    }

    public function test_einschlussmodus_bleibt_unveraendert(): void
    {
        // Bestehende externe Rollen dürfen sich nicht verändern.
        $this->makeLoan($this->holding, $this->fremdA, 'DAR-2026-00005');
        $this->makeLoan($this->fremdA, $this->fremdB, 'DAR-2026-00006');

        $extern = User::factory()->create(['is_active' => true, 'entity_id' => $this->fremdA->id]);
        $extern->assignRole('Darlehensnehmer');
        $extern = $extern->fresh(['roles', 'entityAssignments']);

        $this->assertSame(EntityScopeMode::Include, $extern->entityScopeMode());
        $this->assertFalse($extern->usesEntityExclusion());
        $this->assertSame(1, Entity::visibleTo($extern)->count());
        // Beide Darlehen betreffen die eigene Gesellschaft
        $this->assertSame(2, Loan::visibleTo($extern)->count());
    }

    public function test_interne_rollen_sind_vom_modus_unberuehrt(): void
    {
        $this->makeLoan($this->holding, $this->fremdA, 'DAR-2026-00007');

        $intern = User::factory()->create([
            'is_active' => true,
            'entity_scope_mode' => EntityScopeMode::Exclude->value,
        ]);
        $intern->assignRole('Administrator');
        $intern->entityAssignments()->create(['entity_id' => $this->holding->id, 'context' => 'self']);
        $intern = $intern->fresh(['roles', 'entityAssignments']);

        $this->assertFalse($intern->usesEntityExclusion(), 'Interne Rollen kennen keinen Ausschluss.');
        $this->assertSame(1, Loan::visibleTo($intern)->count());
        $this->assertSame(3, Entity::visibleTo($intern)->count());
    }

    public function test_eigene_akte_bleibt_sichtbar(): void
    {
        $partner = $this->partner([$this->holding], $this->fremdB);

        $namen = Entity::visibleTo($partner)->pluck('display_name')->all();
        $this->assertContains('David Enns', $namen);
    }

    public function test_dokumente_der_ausgeschlossenen_gesellschaft_bleiben_verborgen(): void
    {
        $loanMitHolding = $this->makeLoan($this->holding, $this->fremdA, 'DAR-2026-00008');
        $loanOhne = $this->makeLoan($this->fremdA, $this->fremdB, 'DAR-2026-00009');

        $verborgen = $this->makeDocument('holding.pdf');
        $verborgen->links()->create(['linkable_type' => Loan::class, 'linkable_id' => $loanMitHolding->id]);

        $direktVerborgen = $this->makeDocument('akte-holding.pdf');
        $direktVerborgen->links()->create(['linkable_type' => Entity::class, 'linkable_id' => $this->holding->id]);

        $sichtbar = $this->makeDocument('fremd.pdf');
        $sichtbar->links()->create(['linkable_type' => Loan::class, 'linkable_id' => $loanOhne->id]);

        $ohneVerknuepfung = $this->makeDocument('ohne-bezug.pdf');

        $partner = $this->partner([$this->holding]);
        $namen = Document::visibleTo($partner)->pluck('original_filename')->all();

        $this->assertContains('fremd.pdf', $namen);
        $this->assertContains('ohne-bezug.pdf', $namen, 'Ein Dokument ohne Verknüpfung gehört keiner Gesellschaft.');
        $this->assertNotContains('holding.pdf', $namen);
        $this->assertNotContains('akte-holding.pdf', $namen);
        $this->assertNotNull($ohneVerknuepfung->id);
    }

    public function test_partner_darf_darlehen_anlegen(): void
    {
        $partner = $this->partner([$this->holding]);

        $response = $this->actingAs($partner)->get(route('loans.create'));
        $response->assertOk();

        /*
         * Die ausgeschlossene Gesellschaft darf nicht zur AUSWAHL stehen.
         * Geprüft wird deshalb die Option des Auswahlfeldes, nicht die Seite
         * als Ganzes: der Name der Müller Holding AG steht als Briefkopf und
         * in der Fußzeile jeder Seite.
         */
        $response->assertSee('>Beispiel GmbH</option>', false);
        $response->assertDontSee('>Müller Holding AG</option>', false);
        $response->assertDontSee('value="'.$this->holding->id.'">Müller Holding AG', false);
    }

    public function test_partner_kann_kein_darlehen_mit_ausgeschlossener_gesellschaft_anlegen(): void
    {
        $partner = $this->partner([$this->holding]);

        $response = $this->actingAs($partner)->post(route('loans.store'), [
            'title' => 'Versuch mit ausgeschlossener Gesellschaft',
            'lender_entity_id' => $this->holding->id,
            'borrower_entity_id' => $this->fremdA->id,
            'effective_from' => '2026-09-01',
            'principal_amount' => '10.000,00',
            'interest_rate' => '5',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
        ]);

        $response->assertSessionHasErrors('lender_entity_id');
        $this->assertDatabaseMissing('loans', ['title' => 'Versuch mit ausgeschlossener Gesellschaft']);
    }

    public function test_partner_kann_darlehen_zwischen_sichtbaren_gesellschaften_anlegen(): void
    {
        $partner = $this->partner([$this->holding]);

        $response = $this->actingAs($partner)->post(route('loans.store'), [
            'title' => 'Partnerdarlehen',
            'lender_entity_id' => $this->fremdA->id,
            'borrower_entity_id' => $this->fremdB->id,
            'effective_from' => '2026-09-01',
            'principal_amount' => '10.000,00',
            'interest_rate' => '5',
            'interest_method' => 'act_365',
            'interest_frequency' => 'monthly',
            'repayment_model' => 'bullet',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loans', ['title' => 'Partnerdarlehen']);
    }

    public function test_partner_hat_keinen_zugriff_auf_holding_bereich_und_administration(): void
    {
        $partner = $this->partner([$this->holding]);

        $this->actingAs($partner)->get(route('shareholders.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('resolutions.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($partner)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_kontextwechsel_ist_im_ausschlussmodus_gesperrt(): void
    {
        $partner = $this->partner([$this->holding, $this->fremdA]);
        $zuordnung = $partner->entityAssignments->first();

        $response = $this->actingAs($partner)->post(route('context.switch'), [
            'assignment_id' => $zuordnung->id,
        ]);

        $response->assertSessionHas('danger');
        $this->assertNull(session('context_assignment_id'));
    }

    public function test_darlehensliste_zeigt_nur_erlaubte_vorgaenge(): void
    {
        $this->makeLoan($this->holding, $this->fremdA, 'DAR-2026-00010');
        $this->makeLoan($this->fremdA, $this->fremdB, 'DAR-2026-00011');

        $partner = $this->partner([$this->holding]);
        $response = $this->actingAs($partner)->get(route('loans.index'));

        $response->assertOk();
        $response->assertSee('DAR-2026-00011');
        $response->assertDontSee('DAR-2026-00010');
    }

    public function test_direkter_aufruf_eines_verborgenen_darlehens_scheitert(): void
    {
        $verborgen = $this->makeLoan($this->holding, $this->fremdA, 'DAR-2026-00012');
        $partner = $this->partner([$this->holding]);

        $this->actingAs($partner)->get(route('loans.show', $verborgen))->assertNotFound();
    }

    public function test_administration_kann_den_modus_setzen(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Administrator');
        $partner = User::factory()->create(['is_active' => true]);
        $partner->assignRole('Partner');

        $response = $this->actingAs($admin)->put(route('admin.users.update', $partner), [
            'name' => 'David Enns',
            'email' => $partner->email,
            'roles' => ['Partner'],
            'is_active' => '1',
            'entity_scope_mode' => EntityScopeMode::Exclude->value,
            'assignments' => [
                ['entity_id' => $this->holding->id, 'context' => 'self', 'label' => 'Ausgeschlossen'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $partner = $partner->fresh(['roles', 'entityAssignments']);

        $this->assertSame(EntityScopeMode::Exclude, $partner->entityScopeMode());
        $this->assertTrue($partner->usesEntityExclusion());
        $this->assertSame([$this->holding->id], $partner->excludedEntityIds()->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.users.updated']);
    }

    public function test_unbekannter_modus_wird_abgewiesen(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Administrator');
        $partner = User::factory()->create(['is_active' => true]);
        $partner->assignRole('Partner');

        $response = $this->actingAs($admin)->put(route('admin.users.update', $partner), [
            'name' => 'David Enns',
            'email' => $partner->email,
            'roles' => ['Partner'],
            'is_active' => '1',
            'entity_scope_mode' => 'alles',
        ]);

        $response->assertSessionHasErrors('entity_scope_mode');
    }
}
