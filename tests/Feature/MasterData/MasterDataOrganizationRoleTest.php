<?php

namespace Tests\Feature\MasterData;

use App\Models\OrganizationRole;

class MasterDataOrganizationRoleTest extends MasterDataTestCase
{
    public function test_organstellung_anlegen_und_beenden_erhaelt_historie(): void
    {
        $company = $this->createCompanyEntity('Organ GmbH');
        $person = $this->createPersonEntity('Gesa', 'Geschäftsführerin');
        $admin = $this->admin();

        // Anlegen aus Sicht des Unternehmens
        $this->actingAs($admin)->post(route('companies.organization-roles.store', $company), [
            'person_entity_id' => $person->id,
            'role' => 'managing_director',
            'started_on' => '2020-01-01',
            'sole_representation' => '1',
        ])->assertRedirect(route('companies.show', [$company, 'tab' => 'organe']));

        $role = OrganizationRole::first();
        $this->assertNotNull($role);
        $this->assertTrue($role->is_active);
        $this->assertNull($role->ended_on);

        // Beenden: ended_on + is_active=false, KEIN Löschen
        $this->actingAs($admin)->post(route('companies.organization-roles.end', [$company, $role]), [
            'ended_on' => '2024-06-30',
            'note' => 'Amtsniederlegung',
        ])->assertRedirect(route('companies.show', [$company, 'tab' => 'organe']));

        $role->refresh();
        $this->assertFalse($role->is_active);
        $this->assertSame('2024-06-30', $role->ended_on->toDateString());
        $this->assertDatabaseCount('organization_roles', 1);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'entities.organization_role_ended',
            'auditable_type' => OrganizationRole::class,
            'auditable_id' => $role->id,
        ]);

        // Nach dem Beenden kann dieselbe Rolle erneut vergeben werden (neue Zeile, Historie bleibt)
        $this->actingAs($admin)->post(route('companies.organization-roles.store', $company), [
            'person_entity_id' => $person->id,
            'role' => 'managing_director',
            'started_on' => '2025-01-01',
        ]);

        $this->assertDatabaseCount('organization_roles', 2);
        $this->assertFalse($role->refresh()->is_active, 'Der historische Eintrag darf nicht verändert werden.');
    }

    public function test_doppelte_aktive_organstellung_wird_abgelehnt(): void
    {
        $company = $this->createCompanyEntity('Organ GmbH');
        $person = $this->createPersonEntity('Gesa', 'Geschäftsführerin');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('companies.organization-roles.store', $company), [
            'person_entity_id' => $person->id,
            'role' => 'managing_director',
        ]);

        $this->actingAs($admin)->post(route('companies.organization-roles.store', $company), [
            'person_entity_id' => $person->id,
            'role' => 'managing_director',
        ])->assertSessionHasErrors(['role']);

        $this->assertDatabaseCount('organization_roles', 1);
    }

    public function test_organstellung_aus_personensicht_anlegen(): void
    {
        $company = $this->createCompanyEntity('Beirat AG');
        $person = $this->createPersonEntity('Paul', 'Beirat');

        $this->actingAs($this->admin())->post(route('persons.organization-roles.store', $person), [
            'company_entity_id' => $company->id,
            'role' => 'advisory_board',
        ])->assertRedirect(route('persons.show', [$person, 'tab' => 'rollen']));

        $this->assertDatabaseHas('organization_roles', [
            'company_entity_id' => $company->id,
            'person_entity_id' => $person->id,
            'role' => 'advisory_board',
            'is_active' => true,
        ]);
    }

    public function test_person_als_unternehmensorgan_wird_abgelehnt(): void
    {
        $company = $this->createCompanyEntity('Organ GmbH');
        $otherCompany = $this->createCompanyEntity('Keine Person GmbH');

        $this->actingAs($this->admin())->post(route('companies.organization-roles.store', $company), [
            'person_entity_id' => $otherCompany->id,
            'role' => 'managing_director',
        ])->assertSessionHasErrors(['person_entity_id']);

        $this->assertDatabaseCount('organization_roles', 0);
    }
}
