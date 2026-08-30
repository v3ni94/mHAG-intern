<?php

namespace Tests\Feature\Organisation;

use App\Http\Controllers\OnboardingController;
use App\Models\Setting;
use Database\Seeders\InitialDataSeeder;

/**
 * Erste-Schritte-Assistent (Abschnitt 111): zehn Schritte mit Erledigungsstand
 * aus den echten Daten, überspringbar und später erneut aufrufbar.
 */
class OnboardingWizardTest extends OrganisationTestCase
{
    public function test_assistent_zeigt_alle_zehn_schritte(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('onboarding.index'));

        $response->assertOk();
        foreach ([
            'Unternehmensdaten prüfen',
            'Vorstand anlegen',
            'Aufsichtsrat anlegen',
            'Aktionäre prüfen',
            'Erste Personen anlegen',
            'Erste Unternehmen anlegen',
            'Erstes Darlehen anlegen',
            'SFTP testen',
            'Zwei-Faktor-Authentifizierung prüfen',
            'Benutzer einladen',
        ] as $title) {
            $response->assertSee($title);
        }
    }

    public function test_erledigungsstand_ohne_daten_ist_offen(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('onboarding.index'));

        $response->assertOk();
        $response->assertSee('0 von 10 Schritten erledigt');
        $response->assertSee('Gremium noch nicht angelegt.');
        $response->assertSee('Noch kein Darlehen erfasst.');
        $response->assertSee('Noch kein Verbindungstest durchgeführt.');
        $response->assertSee('Noch keine Person erfasst.');
    }

    public function test_erledigungsstand_wird_aus_echten_daten_ermittelt(): void
    {
        $this->seed(InitialDataSeeder::class);
        // Der Seeder setzt die 2FA-Pflicht; für den Seitenaufruf abschalten.
        Setting::set('security', 'two_factor_required_roles', []);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('onboarding.index'));

        $response->assertOk();
        $response->assertSee('Vorstand: 1 aktives Mitglied');
        $response->assertSee('Aufsichtsrat: 3 aktive Mitglieder');
        $response->assertSee('Müller Holding AG, Amtsgericht Düsseldorf, HRB 104291');
        $response->assertSee('4 Personen erfasst');
        $response->assertSee('1 aktiver Aktionär');
    }

    public function test_darlehen_und_benutzerschritt_werden_erledigt(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLoan($this->makeEntity('Geber AG', 'company'), $this->makeEntity('Nehmer GmbH', 'company'));
        $this->makeUserWithRole('Sachbearbeiter');

        $response = $this->actingAs($admin)->get(route('onboarding.index'));

        $response->assertOk();
        $response->assertSee('1 Darlehen erfasst');
        $response->assertSee('2 Benutzerkonten');
    }

    public function test_assistent_ist_ueberspringbar_und_erneut_aufrufbar(): void
    {
        $admin = $this->makeAdmin();

        // Der Hinweisstreifen erscheint, solange der Assistent offen ist.
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Assistent öffnen');

        $this->actingAs($admin)->post(route('onboarding.skip'))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(
            OnboardingController::STATUS_SKIPPED,
            Setting::get('onboarding', 'user_'.$admin->id)['status'],
        );
        $this->actingAs($admin)->get(route('dashboard'))->assertDontSee('Assistent öffnen');

        // Später erneut aufrufbar: Seite bleibt erreichbar, Zustand zurücksetzbar.
        $this->actingAs($admin)->get(route('onboarding.index'))
            ->assertOk()
            ->assertSee('Übersprungen');

        $this->actingAs($admin)->post(route('onboarding.restart'))
            ->assertRedirect(route('onboarding.index'));

        $this->assertSame(
            OnboardingController::STATUS_OPEN,
            Setting::get('onboarding', 'user_'.$admin->id)['status'],
        );
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Assistent öffnen');
    }

    public function test_zustand_wird_je_benutzer_gefuehrt(): void
    {
        $first = $this->makeAdmin();
        $second = $this->makeAdmin();

        $this->actingAs($first)->post(route('onboarding.skip'))->assertRedirect(route('dashboard'));

        $this->assertSame(OnboardingController::STATUS_SKIPPED, OnboardingController::status($first));
        $this->assertSame(OnboardingController::STATUS_OPEN, OnboardingController::status($second));
    }

    public function test_assistent_ist_fuer_externe_gesperrt(): void
    {
        $external = $this->makeUserWithRole('Darlehensgeber');

        $this->actingAs($external)->get(route('onboarding.index'))->assertForbidden();
        $this->actingAs($external)->post(route('onboarding.skip'))->assertForbidden();
        $this->actingAs($external)->get(route('dashboard'))->assertDontSee('Assistent öffnen');
    }
}
