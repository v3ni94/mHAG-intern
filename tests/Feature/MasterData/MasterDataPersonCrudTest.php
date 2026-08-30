<?php

namespace Tests\Feature\MasterData;

use App\Http\Controllers\PersonController;
use App\Models\AuditLog;
use App\Models\Entity;

class MasterDataPersonCrudTest extends MasterDataTestCase
{
    public function test_index_zeigt_personen(): void
    {
        $this->createPersonEntity('Erika', 'Musterfrau');

        $this->actingAs($this->admin())
            ->get(route('persons.index'))
            ->assertOk()
            ->assertSee('Personen')
            ->assertSee('Erika Musterfrau');
    }

    public function test_person_anlegen_erzeugt_entity_person_und_auditlog(): void
    {
        $response = $this->actingAs($this->admin())->post(route('persons.store'), [
            'salutation' => 'Herr',
            'title' => 'Dr.',
            'first_name' => 'Max',
            'middle_names' => 'Emil',
            'last_name' => 'Mustermann',
            'birth_name' => 'Beispiel',
            'date_of_birth' => '1980-05-04',
            'place_of_birth' => 'Monheim am Rhein',
            'nationality' => 'deutsch',
            'gender' => 'männlich',
            'marital_status' => 'verheiratet',
            'tags' => 'Darlehensgeber, Familie',
            'notes' => 'Interne Anmerkung',
        ]);

        $entity = Entity::where('type', 'person')->latest('id')->first();

        $this->assertNotNull($entity);
        $response->assertRedirect(route('persons.show', $entity));

        $this->assertSame('Dr. Max Mustermann', $entity->display_name);
        $this->assertStringStartsWith('PER-', (string) $entity->internal_number);
        $this->assertSame(['Darlehensgeber', 'Familie'], $entity->tags);

        $this->assertDatabaseHas('persons', [
            'entity_id' => $entity->id,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'birth_name' => 'Beispiel',
            'place_of_birth' => 'Monheim am Rhein',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'persons.created',
            'auditable_type' => Entity::class,
            'auditable_id' => $entity->id,
        ]);
    }

    public function test_validierung_verlangt_nachnamen(): void
    {
        $this->actingAs($this->admin())
            ->from(route('persons.create'))
            ->post(route('persons.store'), ['first_name' => 'Max'])
            ->assertRedirect(route('persons.create'))
            ->assertSessionHasErrors(['last_name']);

        $this->assertDatabaseCount('persons', 0);
    }

    public function test_person_bearbeiten_aktualisiert_anzeigenamen(): void
    {
        $entity = $this->createPersonEntity('Max', 'Mustermann');

        $this->actingAs($this->admin())->put(route('persons.update', $entity), [
            'first_name' => 'Maximilian',
            'last_name' => 'Mustermann-Schmidt',
        ])->assertRedirect(route('persons.show', $entity));

        $entity->refresh();
        $this->assertSame('Maximilian Mustermann-Schmidt', $entity->display_name);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'persons.updated',
            'auditable_id' => $entity->id,
        ]);
    }

    public function test_archivieren_setzt_status_ohne_zu_loeschen(): void
    {
        $entity = $this->createPersonEntity();

        $this->actingAs($this->admin())
            ->post(route('persons.archive', $entity))
            ->assertRedirect(route('persons.index'));

        $entity->refresh();
        $this->assertSame('archived', $entity->status);
        $this->assertNull($entity->deleted_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'persons.archived',
            'auditable_id' => $entity->id,
        ]);

        // Reaktivieren über dieselbe Aktion
        $this->actingAs($this->admin())->post(route('persons.archive', $entity));
        $this->assertSame('active', $entity->refresh()->status);
    }

    public function test_alle_tabs_der_personenakte_rendern(): void
    {
        $entity = $this->createPersonEntity();
        $admin = $this->admin();

        foreach (array_keys(PersonController::TABS) as $tab) {
            $this->actingAs($admin)
                ->get(route('persons.show', [$entity, 'tab' => $tab]))
                ->assertOk();
        }
    }
}
