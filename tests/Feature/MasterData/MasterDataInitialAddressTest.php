<?php

namespace Tests\Feature\MasterData;

use App\Models\Entity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anschrift beim Anlegen von Personen und Unternehmen
 * (Masterprompt 6 und 7: Adressen bzw. Geschäftsanschrift sind Stammdaten).
 */
class MasterDataInitialAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $sachbearbeiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);

        $this->sachbearbeiter = User::factory()->create(['is_active' => true]);
        $this->sachbearbeiter->assignRole('Sachbearbeiter');
    }

    public function test_unternehmen_wird_mit_geschaeftsanschrift_angelegt(): void
    {
        $this->actingAs($this->sachbearbeiter)->post(route('companies.store'), [
            'name' => 'Rheinblick Projekt GmbH',
            'legal_form' => 'GmbH',
            'address_type' => 'business',
            'address_street' => 'Rheinpromenade',
            'address_house_number' => '13',
            'address_postal_code' => '40789',
            'address_city' => 'Monheim am Rhein',
            'address_country' => 'Deutschland',
        ])->assertRedirect();

        $entity = Entity::where('display_name', 'Rheinblick Projekt GmbH')->firstOrFail();
        $adresse = $entity->addresses()->firstOrFail();

        $this->assertSame('business', $adresse->type);
        $this->assertSame('Rheinpromenade', $adresse->street);
        $this->assertSame('13', $adresse->house_number);
        $this->assertSame('40789', $adresse->postal_code);
        $this->assertSame('Monheim am Rhein', $adresse->city);
        $this->assertTrue($adresse->is_primary, 'Die erste Anschrift muss Hauptadresse sein.');
        $this->assertSame('Rheinpromenade 13, 40789 Monheim am Rhein', $adresse->oneLine());
    }

    public function test_person_wird_mit_hauptwohnsitz_angelegt(): void
    {
        $this->actingAs($this->sachbearbeiter)->post(route('persons.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Muster',
            'address_street' => 'Musterweg',
            'address_house_number' => '7a',
            'address_postal_code' => '40789',
            'address_city' => 'Monheim am Rhein',
        ])->assertRedirect();

        $entity = Entity::where('display_name', 'Maria Muster')->firstOrFail();
        $adresse = $entity->addresses()->firstOrFail();

        $this->assertSame('main', $adresse->type);
        $this->assertSame('Musterweg', $adresse->street);
        $this->assertSame('Deutschland', $adresse->country, 'Das Land muss vorbelegt sein.');
        $this->assertTrue($adresse->is_primary);
    }

    public function test_ohne_angaben_wird_keine_leere_adresse_angelegt(): void
    {
        $this->actingAs($this->sachbearbeiter)->post(route('companies.store'), [
            'name' => 'Ohne Anschrift GmbH',
        ])->assertRedirect();

        $entity = Entity::where('display_name', 'Ohne Anschrift GmbH')->firstOrFail();

        $this->assertSame(0, $entity->addresses()->count(), 'Ein leerer Adressdatensatz darf nicht entstehen.');
    }

    public function test_nur_ort_genuegt_fuer_die_anlage(): void
    {
        $this->actingAs($this->sachbearbeiter)->post(route('persons.store'), [
            'first_name' => 'Paul',
            'last_name' => 'Ohnestrasse',
            'address_city' => 'Düsseldorf',
        ])->assertRedirect();

        $entity = Entity::where('display_name', 'Paul Ohnestrasse')->firstOrFail();
        $adresse = $entity->addresses()->firstOrFail();

        $this->assertSame('Düsseldorf', $adresse->city);
        $this->assertNull($adresse->street);
    }

    public function test_anlegen_formulare_zeigen_die_adressfelder(): void
    {
        $this->actingAs($this->sachbearbeiter)
            ->get(route('companies.create'))
            ->assertOk()
            ->assertSee('address_street', false)
            ->assertSee('Anschrift');

        $this->actingAs($this->sachbearbeiter)
            ->get(route('persons.create'))
            ->assertOk()
            ->assertSee('address_city', false);
    }

    public function test_bearbeiten_zeigt_die_adressfelder_nicht(): void
    {
        // Beim Bearbeiten werden Adressen im eigenen Tab gepflegt, weil dort
        // mehrere Adressen mit Gültigkeitszeiträumen möglich sind.
        $this->actingAs($this->sachbearbeiter)->post(route('companies.store'), [
            'name' => 'Bearbeiten GmbH',
        ]);
        $entity = Entity::where('display_name', 'Bearbeiten GmbH')->firstOrFail();

        $this->actingAs($this->sachbearbeiter)
            ->get(route('companies.edit', $entity))
            ->assertOk()
            ->assertDontSee('address_street', false);
    }

    public function test_ungueltige_adressart_wird_abgelehnt(): void
    {
        $this->actingAs($this->sachbearbeiter)->post(route('companies.store'), [
            'name' => 'Falsche Adressart GmbH',
            'address_type' => 'erfunden',
            'address_city' => 'Monheim am Rhein',
        ])->assertSessionHasErrors('address_type');

        $this->assertSame(0, Entity::where('display_name', 'Falsche Adressart GmbH')->count());
    }
}
