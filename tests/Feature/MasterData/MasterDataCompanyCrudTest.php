<?php

namespace Tests\Feature\MasterData;

use App\Http\Controllers\CompanyController;
use App\Models\Entity;

class MasterDataCompanyCrudTest extends MasterDataTestCase
{
    public function test_unternehmen_anlegen(): void
    {
        $response = $this->actingAs($this->admin())->post(route('companies.store'), [
            'name' => 'Testwerk Verwaltungs GmbH',
            'short_name' => 'Testwerk',
            'legal_form' => 'GmbH',
            'founded_on' => '2015-01-15',
            'commercial_register' => 'HRB',
            'register_number' => 'HRB 12345',
            'register_court' => 'Amtsgericht Düsseldorf',
            'tax_number' => '133/5808/1929',
            'vat_id' => 'DE123456789',
            'email' => 'info@testwerk.example',
            'phone' => '+49 2173 000000',
            'industry' => 'Verwaltung',
            'tags' => 'Gruppe',
        ]);

        $entity = Entity::where('type', 'company')->latest('id')->first();
        $this->assertNotNull($entity);
        $response->assertRedirect(route('companies.show', $entity));

        $this->assertSame('Testwerk Verwaltungs GmbH', $entity->display_name);
        $this->assertStringStartsWith('UNT-', (string) $entity->internal_number);

        $this->assertDatabaseHas('companies', [
            'entity_id' => $entity->id,
            'name' => 'Testwerk Verwaltungs GmbH',
            'register_number' => 'HRB 12345',
            'vat_id' => 'DE123456789',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'companies.created',
            'auditable_id' => $entity->id,
        ]);
    }

    public function test_unternehmen_bearbeiten_und_archivieren(): void
    {
        $entity = $this->createCompanyEntity('Alte Firma GmbH');

        $this->actingAs($this->admin())->put(route('companies.update', $entity), [
            'name' => 'Neue Firma GmbH',
            'legal_form' => 'GmbH',
        ])->assertRedirect(route('companies.show', $entity));

        $this->assertSame('Neue Firma GmbH', $entity->refresh()->display_name);

        $this->actingAs($this->admin())
            ->post(route('companies.archive', $entity))
            ->assertRedirect(route('companies.index'));

        $entity->refresh();
        $this->assertSame('archived', $entity->status);
        $this->assertNull($entity->deleted_at);
    }

    public function test_adresse_und_bankkonto_anlegen(): void
    {
        $entity = $this->createCompanyEntity();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('companies.addresses.store', $entity), [
            'type' => 'business',
            'street' => 'Rheinpromenade',
            'house_number' => '13',
            'postal_code' => '40789',
            'city' => 'Monheim am Rhein',
            'is_primary' => '1',
        ])->assertRedirect(route('companies.show', [$entity, 'tab' => 'adressen']));

        $this->assertDatabaseHas('addresses', [
            'entity_id' => $entity->id,
            'city' => 'Monheim am Rhein',
            'is_primary' => true,
        ]);

        // IBAN wird normalisiert (Großschreibung, ohne Leerzeichen) und geprüft
        $this->actingAs($admin)->post(route('companies.bank-accounts.store', $entity), [
            'account_holder' => 'Beispiel GmbH',
            'iban' => 'de89 3704 0044 0532 0130 00',
            'bic' => 'COBADEFFXXX',
            'bank_name' => 'Commerzbank',
            'currency' => 'EUR',
            'is_active' => '1',
        ])->assertRedirect(route('companies.show', [$entity, 'tab' => 'bankkonten']));

        $this->assertDatabaseHas('bank_accounts', [
            'entity_id' => $entity->id,
            'iban' => 'DE89370400440532013000',
        ]);
    }

    public function test_ungueltige_iban_wird_abgelehnt(): void
    {
        $entity = $this->createCompanyEntity();

        $this->actingAs($this->admin())->post(route('companies.bank-accounts.store', $entity), [
            'account_holder' => 'Beispiel GmbH',
            'iban' => 'DE89370400440532013001', // Prüfsumme falsch
            'currency' => 'EUR',
        ])->assertSessionHasErrors(['iban']);

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_alle_tabs_der_unternehmensakte_rendern(): void
    {
        $entity = $this->createCompanyEntity();
        $admin = $this->admin();

        foreach (array_keys(CompanyController::TABS) as $tab) {
            $this->actingAs($admin)
                ->get(route('companies.show', [$entity, 'tab' => $tab]))
                ->assertOk();
        }
    }

    public function test_unternehmensbeziehung_anlegen(): void
    {
        $a = $this->createCompanyEntity('Mutter GmbH');
        $b = $this->createCompanyEntity('Tochter GmbH');

        $this->actingAs($this->admin())->post(route('companies.relationships.store', $a), [
            'entity_b_id' => $b->id,
            'relationship_type' => 'subsidiary',
            'share_percentage' => '25,5',
            'share_count' => 2550,
            'valid_from' => '2020-01-01',
        ])->assertRedirect(route('companies.show', [$a, 'tab' => 'beteiligungen']));

        $this->assertDatabaseHas('entity_relationships', [
            'entity_a_id' => $a->id,
            'entity_b_id' => $b->id,
            'relationship_type' => 'subsidiary',
        ]);

        $relationship = \App\Models\EntityRelationship::first();
        $this->assertSame('25.500000', (string) $relationship->share_percentage);
    }
}
