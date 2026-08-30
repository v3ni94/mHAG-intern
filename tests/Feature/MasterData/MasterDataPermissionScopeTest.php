<?php

namespace Tests\Feature\MasterData;

class MasterDataPermissionScopeTest extends MasterDataTestCase
{
    public function test_gast_wird_zum_login_umgeleitet(): void
    {
        $this->get(route('persons.index'))->assertRedirect(route('login'));
    }

    public function test_externe_rolle_ohne_permission_erhaelt_403(): void
    {
        $own = $this->createPersonEntity('Eigene', 'Person');
        $external = $this->externalUser($own, 'Darlehensgeber');

        $this->actingAs($external)->get(route('persons.index'))->assertForbidden();
        $this->actingAs($external)->get(route('persons.show', $own))->assertForbidden();
    }

    public function test_externe_rolle_sieht_fremde_entity_nicht(): void
    {
        $own = $this->createPersonEntity('Eigene', 'Person');
        $foreign = $this->createPersonEntity('Fremde', 'Person');
        $external = $this->externalUser($own, 'Darlehensgeber', ['persons.view']);

        // Eigene Akte sichtbar
        $this->actingAs($external)->get(route('persons.show', $own))->assertOk();

        // Fremde Akte: 404 (Existenz wird nicht offengelegt)
        $this->actingAs($external)->get(route('persons.show', $foreign))->assertNotFound();

        // Liste enthält nur die eigene Entity
        $this->actingAs($external)->get(route('persons.index'))
            ->assertOk()
            ->assertSee('Eigene Person')
            ->assertDontSee('Fremde Person');
    }

    public function test_externe_rolle_sieht_keine_internen_notizen(): void
    {
        $own = $this->createPersonEntity('Eigene', 'Person', ['notes' => 'STRENG-INTERN-XYZ']);
        $external = $this->externalUser($own, 'Darlehensgeber', ['persons.view']);

        $this->actingAs($external)
            ->get(route('persons.show', [$own, 'tab' => 'notizen']))
            ->assertOk()
            ->assertDontSee('STRENG-INTERN-XYZ');

        // Interner Benutzer sieht die Notizen
        $this->actingAs($this->admin())
            ->get(route('persons.show', [$own, 'tab' => 'notizen']))
            ->assertOk()
            ->assertSee('STRENG-INTERN-XYZ');
    }

    public function test_externe_rolle_kann_fremde_entity_nicht_bearbeiten(): void
    {
        $own = $this->createPersonEntity('Eigene', 'Person');
        $foreign = $this->createPersonEntity('Fremde', 'Person');
        $external = $this->externalUser($own, 'Darlehensgeber', ['persons.view', 'persons.update']);

        $this->actingAs($external)->put(route('persons.update', $foreign), [
            'first_name' => 'Gehackt',
            'last_name' => 'Person',
        ])->assertForbidden();

        $this->assertSame('Fremde Person', $foreign->refresh()->display_name);

        // Unterressourcen fremder Akten ebenfalls gesperrt
        $this->actingAs($external)->post(route('persons.addresses.store', $foreign), [
            'type' => 'main',
            'city' => 'Testort',
        ])->assertForbidden();
    }

    public function test_sachbearbeiter_darf_nicht_archivieren(): void
    {
        $entity = $this->createPersonEntity();

        $this->actingAs($this->internalUser('Sachbearbeiter'))
            ->post(route('persons.archive', $entity))
            ->assertForbidden();

        $this->assertSame('active', $entity->refresh()->status);
    }

    public function test_nur_lesen_rolle_kann_nicht_anlegen(): void
    {
        $this->actingAs($this->internalUser('Nur Lesen'))
            ->post(route('persons.store'), ['first_name' => 'A', 'last_name' => 'B'])
            ->assertForbidden();

        $this->assertDatabaseCount('persons', 0);
    }
}
