<?php

namespace Tests\Feature\MasterData;

class MasterDataSearchTest extends MasterDataTestCase
{
    public function test_suche_findet_person_ueber_iban(): void
    {
        $entity = $this->createPersonEntity('Karla', 'Kontoinhaberin');
        $entity->bankAccounts()->create([
            'account_holder' => 'Karla Kontoinhaberin',
            'iban' => 'DE89370400440532013000',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('search.index', ['q' => 'DE89 3704 0044']))
            ->assertOk()
            ->assertSee('Bankkonten')
            ->assertSee('DE89370400440532013000')
            ->assertSee('Karla Kontoinhaberin');
    }

    public function test_suche_findet_person_und_email(): void
    {
        $entity = $this->createPersonEntity('Susanne', 'Suchbar');
        $entity->contactDetails()->create([
            'type' => 'email',
            'value' => 'susanne@suchbar.example',
            'is_primary' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('search.index', ['q' => 'Suchbar']))
            ->assertOk()
            ->assertSee('Susanne Suchbar');

        $this->actingAs($this->admin())
            ->get(route('search.index', ['q' => 'susanne@suchbar.example']))
            ->assertOk()
            ->assertSee('susanne@suchbar.example');
    }

    public function test_suche_findet_unternehmen_ueber_registernummer(): void
    {
        $entity = $this->createCompanyEntity('Registrierte GmbH');
        $entity->company->update(['register_number' => 'HRB 104291']);

        $this->actingAs($this->admin())
            ->get(route('search.index', ['q' => 'HRB 104291']))
            ->assertOk()
            ->assertSee('Registrierte GmbH');
    }

    public function test_suche_findet_steuernummer(): void
    {
        $entity = $this->createPersonEntity('Toni', 'Steuerzahler');
        $entity->taxDetail()->create([
            'tax_number' => '135/8011/0011',
            'tax_office' => 'Finanzamt Hilden',
        ]);

        $this->actingAs($this->admin())
            ->get(route('search.index', ['q' => '135/8011']))
            ->assertOk()
            ->assertSee('Toni Steuerzahler');
    }

    public function test_externe_rolle_findet_fremde_iban_nicht(): void
    {
        $own = $this->createPersonEntity('Eigene', 'Person');
        $foreign = $this->createPersonEntity('Fremde', 'Person');
        $foreign->bankAccounts()->create([
            'account_holder' => 'Fremde Person',
            'iban' => 'DE89370400440532013000',
            'currency' => 'EUR',
        ]);

        $external = $this->externalUser($own, 'Darlehensgeber', ['persons.view']);

        $this->actingAs($external)
            ->get(route('search.index', ['q' => 'DE893704']))
            ->assertOk()
            ->assertDontSee('DE89370400440532013000');
    }

    public function test_zu_kurzer_suchbegriff_liefert_hinweis(): void
    {
        $this->actingAs($this->admin())
            ->get(route('search.index', ['q' => 'a']))
            ->assertOk()
            ->assertSee('mindestens 2 Zeichen');
    }
}
