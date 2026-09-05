<?php

namespace Tests\Feature\Holding;

use App\Enums\EntityType;
use App\Models\CorporateBody;
use App\Models\CorporateBodyMember;
use App\Models\Entity;

/**
 * Organe (Abschnitte 85 bis 87): aktive Mitglieder, Mandatsende ohne Löschen,
 * stichtagsfähige Organhistorie.
 */
class CorporateBodyTest extends HoldingTestCase
{
    public function test_uebersicht_zeigt_vorstand_und_aufsichtsrat_mit_vorsitz(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('corporate-bodies.index'))
            ->assertOk()
            ->assertSee('Vorstand')
            ->assertSee('Aufsichtsrat')
            ->assertSee('Timo Müller')
            ->assertSee('Jan Walprecht')
            ->assertSee('Vorsitz');
    }

    public function test_mitglied_aufnehmen_und_mandat_beenden_ohne_loeschen(): void
    {
        $this->actingAs($this->admin());

        $board = CorporateBody::query()->where('type', 'board')->firstOrFail();

        $person = Entity::create([
            'type' => EntityType::Person,
            'display_name' => 'Neues Vorstandsmitglied',
            'status' => 'active',
            'internal_number' => 'ENT-TEST-NVM',
        ]);

        $this->post(route('corporate-bodies.members.store', $board), [
            'person_entity_id' => $person->id,
            'role' => 'Vorstandsmitglied',
            'started_on' => '2026-01-01',
            'is_chair' => '0',
        ])->assertRedirect(route('corporate-bodies.show', $board));

        $member = CorporateBodyMember::query()
            ->where('person_entity_id', $person->id)
            ->firstOrFail();
        $this->assertSame('active', $member->status);

        // Mandat beenden: ended_on + Status, Datensatz bleibt erhalten
        $this->post(route('corporate-bodies.members.end', [$board, $member]), [
            'ended_on' => '2026-06-30',
        ])->assertRedirect(route('corporate-bodies.show', $board));

        $member->refresh();
        $this->assertSame('ended', $member->status);
        $this->assertSame('2026-06-30', $member->ended_on->toDateString());
        $this->assertDatabaseHas('corporate_body_members', ['id' => $member->id]);
    }

    public function test_stichtagsansicht_wer_war_am_x_im_amt(): void
    {
        $this->actingAs($this->admin());

        $board = CorporateBody::query()->where('type', 'board')->firstOrFail();

        $person = Entity::create([
            'type' => EntityType::Person,
            'display_name' => 'Ehemaliges Mitglied',
            'status' => 'active',
            'internal_number' => 'ENT-TEST-ALT',
        ]);

        CorporateBodyMember::create([
            'corporate_body_id' => $board->id,
            'person_entity_id' => $person->id,
            'role' => 'Vorstandsmitglied',
            'is_chair' => false,
            'started_on' => '2020-01-01',
            'ended_on' => '2023-12-31',
            'status' => 'ended',
        ]);

        // Am 01.06.2022 war die Person im Amt
        $this->get(route('corporate-bodies.show', ['corporate_body' => $board, 'as_of' => '2022-06-01']))
            ->assertOk()
            ->assertSee('Ehemaliges Mitglied');

        // Aktuell (ohne Stichtag) erscheint sie nicht mehr unter den aktiven Mitgliedern
        $response = $this->get(route('corporate-bodies.index'));
        $response->assertOk()->assertDontSee('Ehemaliges Mitglied');

        // Zum Stichtag 2024 ist sie ebenfalls nicht mehr im Amt
        $this->get(route('corporate-bodies.show', ['corporate_body' => $board, 'as_of' => '2024-06-01']))
            ->assertOk();
        $membersAt2024 = CorporateBodyMember::query()
            ->where('corporate_body_id', $board->id)
            ->where(function ($q) {
                $q->whereNull('started_on')->orWhereDate('started_on', '<=', '2024-06-01');
            })
            ->where(function ($q) {
                $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', '2024-06-01');
            })
            ->pluck('person_entity_id');
        $this->assertNotContains($person->id, $membersAt2024->all());
    }

    public function test_mitgliedspflege_erfordert_shares_prepare(): void
    {
        $this->actingAs($this->readOnlyUser());

        $board = CorporateBody::query()->where('type', 'board')->firstOrFail();

        $this->post(route('corporate-bodies.members.store', $board), [
            'person_entity_id' => 1,
            'role' => 'Test',
        ])->assertForbidden();
    }
}
