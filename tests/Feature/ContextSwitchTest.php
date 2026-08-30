<?php

namespace Tests\Feature;

use App\Enums\AssignmentContext;
use App\Models\CorporateBody;
use App\Models\CorporateBodyMember;
use App\Models\Entity;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ansichtswechsel zwischen Gesellschaften (Abschnitt 13, erweitert am
 * 30.08.2026).
 *
 * Fachliche Vorgabe: Wer für mehrere Gesellschaften handelt, zum Beispiel als
 * Vorstand der Müller Holding AG und als Geschäftsführer der Firma A, soll die
 * Ansicht umschalten können.
 *
 * Zwei Festlegungen, die hier abgesichert werden:
 * 1. Die Ansicht ist ein FILTER, keine Berechtigung. Sie verkleinert den
 *    Datenumfang, vergrößert ihn nie. Der direkte Aufruf eines Vorgangs
 *    bleibt zulässig, solange die Berechtigung besteht.
 * 2. Ohne ausdrückliche Wahl gilt die Gesamtansicht. Eine stillschweigende
 *    Einschränkung würde wie Datenverlust wirken.
 */
class ContextSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Entity $holding;

    private Entity $firmaA;

    private Entity $timo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);

        $this->holding = Entity::create(['type' => 'company', 'display_name' => 'Müller Holding AG']);
        $this->firmaA = Entity::create(['type' => 'company', 'display_name' => 'Firma A GmbH']);
        $this->timo = Entity::create(['type' => 'person', 'display_name' => 'Timo Müller']);
    }

    /** Vorstand mit Zuordnung zu beiden Gesellschaften. */
    private function vorstand(bool $startansichtFirmaA = false): User
    {
        $user = User::factory()->create(['is_active' => true, 'entity_id' => $this->timo->id]);
        $user->assignRole('Vorstand');

        $user->entityAssignments()->create([
            'entity_id' => $this->holding->id,
            'context' => AssignmentContext::Company->value,
        ]);
        $user->entityAssignments()->create([
            'entity_id' => $this->firmaA->id,
            'context' => AssignmentContext::Company->value,
            'is_default' => $startansichtFirmaA,
        ]);

        return $user->fresh(['roles', 'entityAssignments.entity']);
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

    public function test_umschalter_zeigt_beide_gesellschaften_und_die_gesamtansicht(): void
    {
        $user = $this->vorstand();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Ansicht: Gesamtansicht');
        $response->assertSee('Gesamtansicht');
        $response->assertSee('Müller Holding AG (Vorstand)');
        $response->assertSee('Firma A GmbH (Vorstand)');
    }

    public function test_ohne_wahl_gilt_die_gesamtansicht(): void
    {
        $user = $this->vorstand();

        $this->assertNull($user->currentContext());
        $this->assertNull($user->viewEntityId());
    }

    public function test_startansicht_wird_beachtet(): void
    {
        $user = $this->vorstand(startansichtFirmaA: true);

        $this->assertSame($this->firmaA->id, $user->viewEntityId());
        $this->assertSame('Firma A GmbH (Vorstand)', $user->currentContext()->viewLabel());
    }

    public function test_wechsel_schraenkt_die_darlehensliste_ein(): void
    {
        $this->makeLoan($this->holding, $this->timo, 'DAR-2026-09001');
        $this->makeLoan($this->firmaA, $this->timo, 'DAR-2026-09002');

        $user = $this->vorstand();

        // Gesamtansicht: beide Vorgaenge
        $response = $this->actingAs($user)->get(route('loans.index'));
        $response->assertSee('DAR-2026-09001');
        $response->assertSee('DAR-2026-09002');

        // Wechsel auf Firma A
        $firmaAZuordnung = $user->entityAssignments->firstWhere('entity_id', $this->firmaA->id);
        $this->actingAs($user)->post(route('context.switch'), ['assignment_id' => (string) $firmaAZuordnung->id])
            ->assertSessionHas('success');

        $response = $this->actingAs($user)->get(route('loans.index'));
        $response->assertOk();
        $response->assertSee('DAR-2026-09002');
        $response->assertDontSee('DAR-2026-09001');
        $response->assertSee('Ansicht eingeschränkt auf');
    }

    public function test_zurueck_auf_gesamtansicht(): void
    {
        $this->makeLoan($this->holding, $this->timo, 'DAR-2026-09003');
        $this->makeLoan($this->firmaA, $this->timo, 'DAR-2026-09004');
        $user = $this->vorstand();

        $firmaAZuordnung = $user->entityAssignments->firstWhere('entity_id', $this->firmaA->id);
        $this->actingAs($user)->post(route('context.switch'), ['assignment_id' => (string) $firmaAZuordnung->id]);

        $this->actingAs($user)->post(route('context.switch'), ['assignment_id' => User::CONTEXT_ALL])
            ->assertSessionHas('success');

        $response = $this->actingAs($user)->get(route('loans.index'));
        $response->assertSee('DAR-2026-09003');
        $response->assertSee('DAR-2026-09004');
        $response->assertDontSee('Ansicht eingeschränkt auf');
    }

    public function test_ansicht_ist_filter_und_keine_berechtigung(): void
    {
        // Der direkte Aufruf eines Vorgangs der anderen Gesellschaft bleibt
        // zulaessig: sonst wuerde ein Lesezeichen unerklaerlich ins Leere fuehren.
        $holdingDarlehen = $this->makeLoan($this->holding, $this->timo, 'DAR-2026-09005');
        $user = $this->vorstand();

        $firmaAZuordnung = $user->entityAssignments->firstWhere('entity_id', $this->firmaA->id);
        $this->actingAs($user)->post(route('context.switch'), ['assignment_id' => (string) $firmaAZuordnung->id]);

        $this->actingAs($user)->get(route('loans.show', $holdingDarlehen))->assertOk();
    }

    public function test_ansicht_erweitert_die_berechtigung_nicht(): void
    {
        // Externer Benutzer, zugeordnet nur zu Firma A: der Wechsel auf eine
        // fremde Gesellschaft ist nicht moeglich, weil sie nicht zur Auswahl steht.
        $fremd = Entity::create(['type' => 'company', 'display_name' => 'Fremde GmbH']);
        $this->makeLoan($fremd, $this->timo, 'DAR-2026-09006');

        $extern = User::factory()->create(['is_active' => true, 'entity_id' => $this->firmaA->id]);
        $extern->assignRole('Darlehensgeber');
        $extern = $extern->fresh(['roles', 'entityAssignments']);

        $this->assertSame(0, Loan::visibleTo($extern)->count());
        $this->assertTrue($extern->availableContexts()->isEmpty());
    }

    public function test_fremde_ansicht_wird_abgewiesen(): void
    {
        $user = $this->vorstand();
        $anderer = User::factory()->create(['is_active' => true]);
        $anderer->assignRole('Vorstand');
        $fremdeZuordnung = $anderer->entityAssignments()->create([
            'entity_id' => $this->holding->id,
            'context' => AssignmentContext::Company->value,
        ]);

        $response = $this->actingAs($user)->post(route('context.switch'), [
            'assignment_id' => (string) $fremdeZuordnung->id,
        ]);

        $response->assertSessionHas('danger');
        $this->assertNull($user->fresh(['roles', 'entityAssignments'])->viewEntityId());
    }

    public function test_wechsel_wirkt_auf_dashboard_und_zahlungen(): void
    {
        $holdingDarlehen = $this->makeLoan($this->holding, $this->timo, 'DAR-2026-09007');
        $firmaDarlehen = $this->makeLoan($this->firmaA, $this->timo, 'DAR-2026-09008');

        foreach ([$holdingDarlehen, $firmaDarlehen] as $darlehen) {
            \App\Models\Payment::create([
                'loan_id' => $darlehen->id,
                'payer_entity_id' => $this->timo->id,
                'payee_entity_id' => $darlehen->lender_entity_id,
                'payment_date' => '2026-02-01',
                'amount' => '500.00',
                'direction' => 'incoming',
                'origin' => 'manual_entered',
                'status' => 'recorded',
                'reference' => 'ZAHLUNG-'.$darlehen->loan_number,
            ]);
        }

        $user = $this->vorstand();
        $firmaAZuordnung = $user->entityAssignments->firstWhere('entity_id', $this->firmaA->id);
        $this->actingAs($user)->post(route('context.switch'), ['assignment_id' => (string) $firmaAZuordnung->id]);

        // Die Zahlungsliste weist das Darlehen aus; gepruefft wird darueber.
        $zahlungen = $this->actingAs($user)->get(route('payments.index'));
        $zahlungen->assertOk();
        $zahlungen->assertSee('DAR-2026-09008');
        $zahlungen->assertDontSee('DAR-2026-09007');

        // Dashboard: nur ein aktives Darlehen in der Ansicht
        $kennzahlen = app(\App\Services\DashboardService::class);
        $this->actingAs($user);
        $this->assertSame(1, Loan::visibleTo($user->fresh(['roles', 'entityAssignments']))
            ->inCurrentView($user->fresh(['roles', 'entityAssignments']))->count());
        $this->assertNotNull($kennzahlen);
    }

    public function test_ausschlussmodus_kennt_keinen_wechsel(): void
    {
        $partner = User::factory()->create([
            'is_active' => true,
            'entity_scope_mode' => \App\Enums\EntityScopeMode::Exclude->value,
        ]);
        $partner->assignRole('Partner');
        $partner->entityAssignments()->create([
            'entity_id' => $this->holding->id,
            'context' => AssignmentContext::Self->value,
        ]);
        $partner = $partner->fresh(['roles', 'entityAssignments']);

        $this->assertTrue($partner->availableContexts()->isEmpty());
        $this->assertNull($partner->viewEntityId());

        $response = $this->actingAs($partner)->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee('Ansicht: Gesamtansicht');
    }

    public function test_organmandate_werden_der_administration_vorgeschlagen(): void
    {
        // Timo ist Vorstand der Firma A. Das Mandat allein gewaehrt keinen
        // Zugriff, es wird der Administration nur vorgeschlagen.
        $organ = CorporateBody::create([
            'company_entity_id' => $this->firmaA->id,
            'type' => 'board',
            'name' => 'Geschäftsführung Firma A GmbH',
        ]);
        CorporateBodyMember::create([
            'corporate_body_id' => $organ->id,
            'person_entity_id' => $this->timo->id,
            'role' => 'Geschäftsführer',
            'status' => 'active',
            'started_on' => '2026-01-01',
        ]);

        $user = User::factory()->create(['is_active' => true, 'entity_id' => $this->timo->id]);
        $user->assignRole('Vorstand');
        $user = $user->fresh(['roles', 'entityAssignments']);

        $mandate = $user->organMandates();
        $this->assertCount(1, $mandate);
        $this->assertSame($this->firmaA->id, $mandate->first()->body->company_entity_id);

        // Ohne Zuordnung steht keine Ansicht zur Verfuegung
        $this->assertTrue($user->availableContexts()->isEmpty());

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $user));
        $response->assertOk();
        $response->assertSee('Organmandate dieser Person');
        $response->assertSee('Geschäftsführung Firma A GmbH');
        $response->assertSee('Geschäftsführer');
        $response->assertSee('nicht freigegeben');
    }

    public function test_freigegebenes_mandat_wird_als_freigegeben_ausgewiesen(): void
    {
        $organ = CorporateBody::create([
            'company_entity_id' => $this->firmaA->id,
            'type' => 'board',
            'name' => 'Vorstand Firma A GmbH',
        ]);
        CorporateBodyMember::create([
            'corporate_body_id' => $organ->id,
            'person_entity_id' => $this->timo->id,
            'role' => 'Vorstand',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['is_active' => true, 'entity_id' => $this->timo->id]);
        $user->assignRole('Vorstand');
        $user->entityAssignments()->create([
            'entity_id' => $this->firmaA->id,
            'context' => AssignmentContext::Company->value,
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $user->fresh(['roles', 'entityAssignments'])));

        $response->assertOk();
        $response->assertSee('freigegeben');
        $response->assertSee('Vorstand Firma A GmbH');
    }

    public function test_beschriftung_nutzt_die_erfasste_bezeichnung(): void
    {
        $user = User::factory()->create(['is_active' => true, 'entity_id' => $this->timo->id]);
        $user->assignRole('Vorstand');
        $user->entityAssignments()->create([
            'entity_id' => $this->firmaA->id,
            'context' => AssignmentContext::Company->value,
            'label' => 'Firma A, operatives Geschäft',
        ]);
        $user = $user->fresh(['roles', 'entityAssignments.entity']);

        $this->assertSame('Firma A, operatives Geschäft', $user->availableContexts()->first()->viewLabel());
    }
}
