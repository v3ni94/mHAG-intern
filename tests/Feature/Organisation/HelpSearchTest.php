<?php

namespace Tests\Feature\Organisation;

use Database\Seeders\ContentSeeder;

/**
 * Hilfe-Suche (Abschnitt 115): Volltextsuche über Fragen und Antworten der
 * FAQ sowie über Titel UND Inhalt der Anleitungsseiten, mit einzelnen
 * Suchbegriffen statt exakter Phrase und Kontextauszug je Treffer.
 */
class HelpSearchTest extends OrganisationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_beispiel_des_masterprompts_findet_die_genannten_themen(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.search', ['q' => 'Zinsen nicht bezahlt']));

        $response->assertOk();

        // Anleitungen: nicht gezahlte Zinsen erfassen, Teilzahlungen
        $response->assertSee('Zinsausfälle erfassen');
        $response->assertSee('Teilzahlungen erfassen');

        // FAQ: die Frage lautet "gezahlt", gesucht wurde "bezahlt"
        $response->assertSee('Was mache ich, wenn Zinsen nicht gezahlt wurden?');
        $response->assertSee('Kann ich Teilzahlungen erfassen?');

        // Keine Leermeldung mehr
        $response->assertDontSee('Keine passenden Anleitungen gefunden.');
        $response->assertDontSee('Keine passenden FAQ-Einträge gefunden.');
    }

    public function test_treffer_werden_mit_kontextauszug_ausgegeben(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.search', ['q' => 'Zinsen nicht bezahlt']));

        $response->assertOk();
        // Die Auszüge stammen aus dem Seiteninhalt, nicht nur aus dem Titel.
        $response->assertSee('Forderungsstand');
        $response->assertSee('…', false);
    }

    public function test_suche_findet_inhalte_der_anleitungsseiten_ohne_titeltreffer(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.search', ['q' => 'Forderungsaufstellung']));

        $response->assertOk();
        $response->assertSee('Zinsausfälle erfassen');
        $response->assertDontSee('Keine passenden Anleitungen gefunden.');
    }

    public function test_verzugszinsen_werden_ueber_den_seiteninhalt_gefunden(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.search', ['q' => 'Verzugszinsen']));

        $response->assertOk();
        $response->assertDontSee('Keine passenden Anleitungen gefunden.');
    }

    public function test_einzelne_suchbegriffe_werden_ausgewertet(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.search', ['q' => 'Teilzahlung Rest getrennt']));

        $response->assertOk();
        $response->assertSee('Kann ich Teilzahlungen erfassen?');
    }

    public function test_ohne_suchbegriff_wird_der_hinweis_angezeigt(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.search'));

        $response->assertOk();
        $response->assertSee('Bitte geben Sie einen Suchbegriff');
    }

    public function test_suche_ohne_treffer_meldet_leeres_ergebnis(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.search', ['q' => 'Grundstueckskaufvertrag Notartermin']));

        $response->assertOk();
        $response->assertSee('Keine passenden Anleitungen gefunden.');
        $response->assertSee('Keine passenden FAQ-Einträge gefunden.');
    }

    public function test_nicht_implementierter_datenimport_ist_gekennzeichnet(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('help.page', 'datenimport'));

        $response->assertOk();
        $response->assertSee('Nicht implementiert');

        // Auch aus der Administration heraus auffindbar.
        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertSee('nicht verfügbar');
    }
}
