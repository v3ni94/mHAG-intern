<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profilbild und Benutzermenü (Anforderung vom 30.08.2026).
 *
 * Grundsätze: Bilder liegen außerhalb des öffentlichen Verzeichnisses, werden
 * nur nach Anmeldung ausgeliefert, und ohne hinterlegtes Bild erscheint das
 * Firmenzeichen der Müller Holding AG.
 */
class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);
        Storage::fake('avatars');
    }

    private function makeUser(string $role = 'Sachbearbeiter'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function bild(string $name = 'portrait.jpg', int $width = 200, int $height = 200): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    public function test_benutzermenue_zeigt_ohne_bild_das_firmenzeichen(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('logo-mhag-transparent.png');
        $response->assertSee('avatar-circle', false);
        $response->assertSee('Benutzermenü '.$user->name, false);
    }

    public function test_benutzermenue_zeigt_hinterlegtes_bild(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => $this->bild()]);

        $response = $this->actingAs($user->fresh())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('profile.avatar.show', $user->id), false);
    }

    public function test_benutzermenue_hat_abmelden_als_letzten_punkt(): void
    {
        $admin = $this->makeUser('Administrator');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        // Reihenfolge: Standardpunkte, dann Einstellungen, Abmelden zuletzt
        $response->assertSeeInOrder([
            'Mein Profil',
            'Zwei-Faktor-Authentifizierung',
            'Benachrichtigungen',
            'Hilfe und FAQ',
            'Einstellungen',
            'Benutzerverwaltung',
            'Abmelden',
        ], false);
    }

    public function test_einstellungen_erscheinen_nur_mit_berechtigung(): void
    {
        $user = $this->makeUser('Darlehensnehmer');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Benutzerverwaltung');
        $response->assertSee('Abmelden');
    }

    public function test_bild_wird_gespeichert_und_dateiname_vom_system_vergeben(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => $this->bild('Mein Foto.jpg'),
        ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        $this->assertStringStartsWith('benutzer-'.$user->id.'-', $user->avatar_path);
        $this->assertStringNotContainsString('Mein Foto', $user->avatar_path, 'Der Originalname darf nicht übernommen werden.');
        Storage::disk('avatars')->assertExists($user->avatar_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.avatar_changed']);
    }

    public function test_austausch_loescht_die_alte_datei(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => $this->bild('eins.jpg')]);
        $erstes = $user->fresh()->avatar_path;

        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => $this->bild('zwei.png')]);
        $zweites = $user->fresh()->avatar_path;

        $this->assertNotSame($erstes, $zweites);
        Storage::disk('avatars')->assertMissing($erstes);
        Storage::disk('avatars')->assertExists($zweites);
    }

    public function test_entfernen_loescht_datei_und_verweis(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => $this->bild()]);
        $pfad = $user->fresh()->avatar_path;

        $response = $this->actingAs($user->fresh())->delete(route('profile.avatar.destroy'));

        $response->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('avatars')->assertMissing($pfad);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.avatar_removed']);
    }

    public function test_svg_wird_abgewiesen(): void
    {
        // Eine SVG-Datei kann ausführbaren Code enthalten (Abschnitt 131).
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_zu_grosses_bild_wird_abgewiesen(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('gross.jpg', 500, 500)->size(3000),
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_zu_kleines_bild_wird_abgewiesen(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => $this->bild('winzig.jpg', 20, 20),
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_eigenes_bild_ist_abrufbar(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => $this->bild()]);

        $response = $this->actingAs($user->fresh())->get(route('profile.avatar.show', $user->id));

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('content-type'));
    }

    public function test_fremdes_bild_ist_ohne_benutzerverwaltung_gesperrt(): void
    {
        $fremd = $this->makeUser();
        $this->actingAs($fremd)->post(route('profile.avatar.store'), ['avatar' => $this->bild()]);

        $andere = $this->makeUser();
        $response = $this->actingAs($andere)->get(route('profile.avatar.show', $fremd->id));

        $response->assertForbidden();
    }

    public function test_benutzerverwaltung_darf_fremdes_bild_sehen(): void
    {
        $fremd = $this->makeUser();
        $this->actingAs($fremd)->post(route('profile.avatar.store'), ['avatar' => $this->bild()]);

        $admin = $this->makeUser('Administrator');
        $response = $this->actingAs($admin)->get(route('profile.avatar.show', $fremd->id));

        $response->assertOk();
    }

    public function test_ohne_hinterlegtes_bild_gibt_es_nichts_auszuliefern(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('profile.avatar.show', $user->id));

        $response->assertNotFound();
    }

    public function test_bildabruf_ohne_anmeldung_wird_abgewiesen(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => $this->bild()]);
        $this->post(route('logout'));

        $response = $this->get(route('profile.avatar.show', $user->id));

        $response->assertRedirect(route('login'));
    }

    public function test_initialen_als_schriftlicher_rueckfall(): void
    {
        $user = User::factory()->create(['name' => 'Timo Müller', 'is_active' => true]);
        $this->assertSame('TM', $user->initials());

        $einzel = User::factory()->create(['name' => 'Müller', 'is_active' => true]);
        $this->assertSame('M', $einzel->initials());
    }

    public function test_profilseite_zeigt_den_bereich_profilbild(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Profilbild');
        $response->assertSee('Zulässig sind JPG, PNG und WebP bis 2 MB.', false);
    }
}
