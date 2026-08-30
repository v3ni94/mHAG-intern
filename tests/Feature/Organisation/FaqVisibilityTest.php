<?php

namespace Tests\Feature\Organisation;

use App\Models\FaqEntry;

class FaqVisibilityTest extends OrganisationTestCase
{
    private function seedFaq(): void
    {
        $rows = [
            ['Frage für alle Benutzer?', 'all'],
            ['Frage nur für interne Rollen?', 'internal'],
            ['Frage nur für Administratoren?', 'admin'],
            ['Frage nur für den Aufsichtsrat?', 'supervisory_board'],
            ['Frage nur für Darlehensgeber?', 'lender'],
            ['Frage nur für Darlehensnehmer?', 'borrower'],
        ];
        foreach ($rows as $i => [$question, $visibility]) {
            FaqEntry::create([
                'category' => 'Test',
                'question' => $question,
                'answer' => 'Antwort '.$i,
                'sort' => $i,
                'visibility' => $visibility,
                'is_active' => true,
            ]);
        }
    }

    public function test_administrator_sieht_alle_relevanten_eintraege(): void
    {
        $this->seedFaq();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('faq.index'));

        $response->assertOk();
        $response->assertSee('Frage für alle Benutzer?');
        $response->assertSee('Frage nur für interne Rollen?');
        $response->assertSee('Frage nur für Administratoren?');
        // Rollenspezifische Sichtbarkeiten anderer Rollen bleiben verborgen
        $response->assertDontSee('Frage nur für den Aufsichtsrat?');
        $response->assertDontSee('Frage nur für Darlehensgeber?');
    }

    public function test_darlehensgeber_sieht_nur_all_und_lender(): void
    {
        $this->seedFaq();
        $lender = $this->makeUserWithRole('Darlehensgeber');

        $response = $this->actingAs($lender)->get(route('faq.index'));

        $response->assertOk();
        $response->assertSee('Frage für alle Benutzer?');
        $response->assertSee('Frage nur für Darlehensgeber?');
        $response->assertDontSee('Frage nur für interne Rollen?');
        $response->assertDontSee('Frage nur für Administratoren?');
        $response->assertDontSee('Frage nur für Darlehensnehmer?');
    }

    public function test_aufsichtsrat_sieht_supervisory_board_eintraege(): void
    {
        $this->seedFaq();
        $member = $this->makeUserWithRole('Aufsichtsratsmitglied');

        $response = $this->actingAs($member)->get(route('faq.index'));

        $response->assertOk();
        $response->assertSee('Frage nur für den Aufsichtsrat?');
        $response->assertDontSee('Frage nur für interne Rollen?');
    }

    public function test_inaktive_eintraege_werden_nicht_angezeigt(): void
    {
        FaqEntry::create([
            'question' => 'Deaktivierte Frage?',
            'answer' => 'x',
            'visibility' => 'all',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->makeAdmin())->get(route('faq.index'));

        $response->assertOk();
        $response->assertDontSee('Deaktivierte Frage?');
    }

    public function test_hilfeseiten_whitelist(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('help.page', 'zinsausfaelle-erfassen'))->assertOk()
            ->assertSee('Nicht bezahlt');
        $this->actingAs($admin)->get(route('help.page', 'gibt-es-nicht'))->assertNotFound();
    }
}
