<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ein nicht entschlüsselbares Geheimnis der Zwei-Faktor-Anmeldung.
 *
 * Anlass ist ein echter Ausfall der Produktivinstanz am 30.08.2026: Nach einem
 * Wechsel des Anwendungsschlüssels APP_KEY warf der Cast 'encrypted' beim Lesen
 * von users.two_factor_secret eine DecryptException. Weil sie nirgends
 * abgefangen wurde, endete die Anmeldung mit einem Serverfehler 500, und über
 * die Middleware für die Zwei-Faktor-Pflicht auch jede weitere Seite.
 *
 * Verlangt wird zweierlei, und beides zugleich:
 *
 * 1. Kein Ausfall. Ein einzelner nicht lesbarer Datensatz darf keine Seite und
 *    keinen Ablauf zum Abbruch bringen.
 * 2. Keine Umgehung. Der zweite Faktor bleibt bestehen. Wer das Geheimnis
 *    unlesbar macht, etwa durch Austausch des Anwendungsschlüssels, darf
 *    dadurch nicht ohne zweiten Faktor hineinkommen. Der Weg zurück führt
 *    ausschließlich über die Zurücksetzung durch die Administration.
 */
class TwoFactorUnreadableSecretTest extends TestCase
{
    use RefreshDatabase;

    private const KENNWORT = 'Pruefkennwort-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);
    }

    private function benutzer(string $rolle = 'Mitarbeiter'): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make(self::KENNWORT),
        ]);
        $user->assignRole($rolle);

        return $user->fresh();
    }

    /**
     * Schreibt ein mit einem FREMDEN Anwendungsschlüssel verschlüsseltes
     * Geheimnis direkt in die Tabelle, unter Umgehung der Casts. Genau dieser
     * Zustand lag auf dem Produktivsystem vor.
     */
    private function unlesbaresGeheimnisSetzen(User $user, bool $bestaetigt = true): void
    {
        $fremd = new Encrypter(random_bytes(32), (string) config('app.cipher'));

        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => $fremd->encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => $fremd->encryptString(json_encode(['ABCDE-FGHIJ'])),
            'two_factor_confirmed_at' => $bestaetigt ? now() : null,
        ]);
    }

    #[Test]
    public function modell_meldet_das_geheimnis_als_hinterlegt_aber_nicht_lesbar(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);
        $user = $user->fresh();

        $this->assertTrue($user->hasTwoFactorSecretStored());
        $this->assertTrue($user->hasUnreadableTwoFactorSecret());
        $this->assertNull($user->twoFactorSecret());
        $this->assertSame([], $user->twoFactorRecoveryCodes());
    }

    #[Test]
    public function der_zweite_faktor_bleibt_bestehen_und_wird_nicht_umgangen(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled(),
            'Ein nicht lesbares Geheimnis darf die Zwei-Faktor-Anmeldung nicht abschalten. '
            .'Sonst käme man durch Austausch des Anwendungsschlüssels ohne zweiten Faktor hinein.');
    }

    #[Test]
    public function anmeldung_bricht_nicht_ab_sondern_fuehrt_zur_abfrage(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);

        $antwort = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => self::KENNWORT,
        ]);

        $antwort->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    #[Test]
    public function die_abfrage_laesst_niemanden_durch_und_benennt_die_ursache(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => self::KENNWORT,
        ]);

        $antwort = $this->post(route('two-factor.challenge.store'), ['code' => '123456']);

        $antwort->assertSessionHasErrors('code');
        $this->assertStringContainsString(
            'nicht lesbar',
            (string) session('errors')->first('code'),
        );
        $this->assertGuest();
    }

    #[Test]
    public function auch_ein_recovery_code_laesst_niemanden_durch(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => self::KENNWORT,
        ]);

        $this->post(route('two-factor.challenge.store'), ['recovery_code' => 'ABCDE-FGHIJ'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    #[Test]
    public function der_vorgang_wird_im_pruefpfad_festgehalten(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => self::KENNWORT,
        ]);
        $this->post(route('two-factor.challenge.store'), ['code' => '123456']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.two_factor_secret_unreadable',
            'auditable_id' => $user->id,
        ]);
    }

    #[Test]
    public function benutzerverwaltung_bleibt_bedienbar(): void
    {
        $admin = $this->benutzer('Administrator');
        $betroffen = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($betroffen);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Nicht lesbar');

        $this->actingAs($admin)->get(route('admin.users.show', $betroffen))
            ->assertOk()
            ->assertSee('Geheimnis nicht lesbar');
    }

    #[Test]
    public function administration_kann_zuruecksetzen_und_der_zustand_ist_danach_bereinigt(): void
    {
        $admin = $this->benutzer('Administrator');
        $betroffen = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($betroffen);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-two-factor', $betroffen))
            ->assertRedirect();

        $betroffen = $betroffen->fresh();
        $this->assertFalse($betroffen->hasTwoFactorSecretStored());
        $this->assertFalse($betroffen->hasUnreadableTwoFactorSecret());
        $this->assertFalse($betroffen->hasTwoFactorEnabled());
        $this->assertNull($betroffen->two_factor_confirmed_at);
    }

    #[Test]
    public function nach_dem_zuruecksetzen_ist_die_anmeldung_wieder_moeglich(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);
        $admin = $this->benutzer('Administrator');

        $this->actingAs($admin)->post(route('admin.users.reset-two-factor', $user));
        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => self::KENNWORT,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    public function einrichtungsseite_benennt_den_zustand_und_erzeugt_kein_neues_geheimnis(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);
        $vorher = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->actingAs($user->fresh())->get(route('two-factor.setup'))
            ->assertOk()
            ->assertSee('nicht lesbar');

        $nachher = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertSame($vorher, $nachher,
            'Ein bestätigtes Geheimnis darf nicht stillschweigend ersetzt werden. Das wäre ein '
            .'Austausch des zweiten Faktors ohne Nachweis.');
    }

    #[Test]
    public function unbestaetigtes_unlesbares_geheimnis_wird_neu_erzeugt(): void
    {
        // Nie in Gebrauch gewesen: hier ist ein neues Geheimnis gefahrlos.
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user, bestaetigt: false);

        $this->actingAs($user->fresh())->get(route('two-factor.setup'))->assertOk();

        $this->assertFalse($user->fresh()->hasUnreadableTwoFactorSecret());
        $this->assertNotNull($user->fresh()->twoFactorSecret());
    }

    #[Test]
    public function zwei_faktor_pflicht_fuehrt_nicht_zum_abbruch(): void
    {
        Setting::set('security', 'two_factor_required_roles', ['Administrator']);

        $admin = $this->benutzer('Administrator');
        $this->unlesbaresGeheimnisSetzen($admin);

        // Angemeldet und mit Pflichtrolle: die Middleware liest den Zustand.
        // Sie darf nicht abbrechen, und sie darf nicht in eine Weiterleitungs-
        // schleife laufen.
        $this->actingAs($admin->fresh())->get(route('dashboard'))->assertOk();
    }

    #[Test]
    public function bestaetigung_mit_unlesbarem_geheimnis_wird_abgelehnt(): void
    {
        $user = $this->benutzer();
        $this->unlesbaresGeheimnisSetzen($user);

        $this->actingAs($user->fresh())
            ->post(route('two-factor.confirm'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at,
            'Der bestätigte Zustand darf durch einen fehlgeschlagenen Versuch nicht verloren gehen.');
    }

    #[Test]
    public function mit_hinterlegtem_vorherigen_schluessel_bleibt_das_geheimnis_lesbar(): void
    {
        /*
         * Verlustfreier Weg: Laravel kennt vorherige Anwendungsschlüssel
         * (config/app.php, APP_PREVIOUS_KEYS). Ist der alte Schlüssel noch
         * bekannt, bleiben die mit ihm verschlüsselten Felder lesbar, und beim
         * nächsten Schreiben werden sie mit dem neuen Schlüssel abgelegt.
         * Ein Zurücksetzen der Zwei-Faktor-Anmeldung ist dann nicht nötig.
         */
        $alterSchluessel = random_bytes(32);
        $neuerSchluessel = random_bytes(32);
        $cipher = (string) config('app.cipher');

        $user = $this->benutzer();

        $alt = new Encrypter($alterSchluessel, $cipher);
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => $alt->encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->schluesselNeuBinden(
            'base64:'.base64_encode($neuerSchluessel),
            ['base64:'.base64_encode($alterSchluessel)],
        );

        $user = User::find($user->id);

        $this->assertFalse($user->hasUnreadableTwoFactorSecret(),
            'Mit hinterlegtem vorherigen Schlüssel muss das Geheimnis lesbar bleiben.');
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->twoFactorSecret());
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    #[Test]
    public function ohne_hinterlegten_vorherigen_schluessel_ist_es_nicht_lesbar(): void
    {
        // Gegenprobe zum vorigen Fall: dieselbe Ausgangslage, nur ohne den
        // vorherigen Schlüssel. Damit ist belegt, dass die Lesbarkeit
        // tatsächlich am hinterlegten vorherigen Schlüssel hängt.
        $alterSchluessel = random_bytes(32);
        $neuerSchluessel = random_bytes(32);
        $cipher = (string) config('app.cipher');

        $user = $this->benutzer();

        $alt = new Encrypter($alterSchluessel, $cipher);
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => $alt->encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->schluesselNeuBinden('base64:'.base64_encode($neuerSchluessel), []);

        $this->assertTrue(User::find($user->id)->hasUnreadableTwoFactorSecret());
    }

    /**
     * Anwendungsschlüssel und vorherige Schlüssel neu binden, wie es ein
     * Neustart der Anwendung mit geänderter .env täte.
     *
     * @param  array<int, string>  $vorherige
     */
    private function schluesselNeuBinden(string $schluessel, array $vorherige): void
    {
        config(['app.key' => $schluessel, 'app.previous_keys' => $vorherige]);

        Model::encryptUsing(null);
        app()->forgetInstance('encrypter');
        app()->forgetInstance('encrypter.string');
        Crypt::clearResolvedInstances();
        (new EncryptionServiceProvider(app()))->register();
    }
}
