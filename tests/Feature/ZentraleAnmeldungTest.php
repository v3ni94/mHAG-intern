<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\Setting;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Zentrale Anmeldung über das CRM (OpenID Connect, Authorization Code mit
 * PKCE).
 *
 * Geprüft wird beides: dass der gute Fall funktioniert, und dass jeder
 * einzelne Prüfschritt tatsächlich greift. Ein Anmeldeweg, bei dem eine
 * Prüfung fehlt, ist schlimmer als keiner, weil er Sicherheit vortäuscht.
 */
class ZentraleAnmeldungTest extends TestCase
{
    use RefreshDatabase;

    private const AUSSTELLER = 'https://api.mueller-holding.ag';

    private const CLIENT_ID = 'mhag-intranet';

    private const RUECKKEHR = 'https://intern.mueller-holding.ag/anmeldung/crm/rueckkehr';

    private string $privaterSchluessel;

    private array $jwk;

    private ?string $tokenAntwort = null;

    private int $tokenStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);

        [$this->privaterSchluessel, $this->jwk] = $this->schluesselpaar();

        config()->set('sso.enabled', true);
        config()->set('sso.issuer', self::AUSSTELLER);
        config()->set('sso.client_id', self::CLIENT_ID);
        config()->set('sso.client_secret', 'geheim');
        config()->set('sso.redirect_uri', self::RUECKKEHR);
        config()->set('sso.login_url', null);
        config()->set('sso.trust_mfa', true);
        config()->set('sso.allow_local_login', true);
        config()->set('sso.cache_seconds', 0);
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function schluesselpaar(): array
    {
        $ressource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($ressource, 'EC-Schluesselpaar konnte nicht erzeugt werden.');

        openssl_pkey_export($ressource, $pem);
        $einzelheiten = openssl_pkey_get_details($ressource);

        $jwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => rtrim(strtr(base64_encode(str_pad($einzelheiten['ec']['x'], 32, "\0", STR_PAD_LEFT)), '+/', '-_'), '='),
            'y' => rtrim(strtr(base64_encode(str_pad($einzelheiten['ec']['y'], 32, "\0", STR_PAD_LEFT)), '+/', '-_'), '='),
            'use' => 'sig',
            'alg' => 'ES256',
            'kid' => 'pruefschluessel',
        ];

        return [$pem, $jwk];
    }

    private function idToken(array $abweichungen = [], ?string $schluessel = null): string
    {
        $angaben = array_merge([
            'iss' => self::AUSSTELLER,
            'aud' => self::CLIENT_ID,
            'sub' => 'b7d2c1f0-0000-4000-8000-000000000001',
            'iat' => time(),
            'exp' => time() + 900,
            'email' => 'pruefer@muellerhv.de',
            'name' => 'Pruefer',
            'tenant_id' => null,
        ], $abweichungen);

        return JWT::encode(
            array_filter($angaben, static fn ($wert) => $wert !== null),
            $schluessel ?? $this->privaterSchluessel,
            'ES256',
            'pruefschluessel',
        );
    }

    /**
     * Antworten des CRM stellen.
     *
     * Wichtig: Http::fake() ergaenzt die Liste der Attrappen, es ersetzt sie
     * nicht. Bei mehrfachem Aufruf gewinnt die zuerst eingetragene Antwort.
     * Deshalb wird hier genau einmal eingerichtet; die Antwort des
     * Tokenendpunkts wird ueber $this->tokenAntwort gesteuert und erst beim
     * Aufruf ausgewertet.
     */
    private function crmAntworten(): void
    {
        Http::fake([
            self::AUSSTELLER.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::AUSSTELLER,
                'authorization_endpoint' => self::AUSSTELLER.'/api/v1/oidc/authorize',
                'token_endpoint' => self::AUSSTELLER.'/api/v1/oidc/token',
                'jwks_uri' => self::AUSSTELLER.'/api/v1/oidc/jwks',
            ]),
            self::AUSSTELLER.'/api/v1/oidc/jwks' => Http::response(['keys' => [$this->jwk]]),
            self::AUSSTELLER.'/api/v1/oidc/token' => function () {
                if ($this->tokenStatus !== 200) {
                    return Http::response(['detail' => 'invalid code'], $this->tokenStatus);
                }

                return Http::response([
                    'access_token' => 'nicht-verwendet',
                    'id_token' => $this->tokenAntwort ?? '',
                    'token_type' => 'Bearer',
                    'expires_in' => 900,
                ]);
            },
        ]);
    }

    /** Das Identitaetstoken festlegen, das der Tokenendpunkt liefern soll. */
    private function tokenLiefern(array $abweichungen = [], ?string $schluessel = null): void
    {
        $this->tokenAntwort = $this->idToken($abweichungen, $schluessel);
    }

    private function benutzer(array $werte = []): User
    {
        $benutzer = User::factory()->create(array_merge([
            'email' => 'pruefer@muellerhv.de',
            'name' => 'Pruefer',
            'is_active' => true,
        ], $werte));
        $benutzer->assignRole('Sachbearbeiter');

        return $benutzer;
    }

    /** Sitzung mit den Werten aus dem ersten Schritt vorbereiten. */
    private function starten(): array
    {
        $antwort = $this->get(route('sso.start'));
        $antwort->assertRedirect();

        $ziel = $antwort->headers->get('Location');
        parse_str((string) parse_url($ziel, PHP_URL_QUERY), $parameter);

        return ['ziel' => $ziel, 'parameter' => $parameter];
    }

    // -----------------------------------------------------------------
    // Erster Schritt
    // -----------------------------------------------------------------

    public function test_der_start_leitet_mit_allen_pflichtangaben_zum_crm(): void
    {
        $this->crmAntworten();

        ['ziel' => $ziel, 'parameter' => $parameter] = $this->starten();

        $this->assertStringStartsWith(self::AUSSTELLER.'/api/v1/oidc/authorize', $ziel);
        $this->assertSame('code', $parameter['response_type']);
        $this->assertSame(self::CLIENT_ID, $parameter['client_id']);
        $this->assertSame(self::RUECKKEHR, $parameter['redirect_uri']);
        $this->assertSame('S256', $parameter['code_challenge_method']);
        $this->assertSame('openid profile email', $parameter['scope']);
        $this->assertNotEmpty($parameter['state']);
        $this->assertNotEmpty($parameter['nonce']);
        $this->assertGreaterThanOrEqual(43, strlen($parameter['code_challenge']));

        // Der Verifier darf den Browser nie erreichen.
        $this->assertStringNotContainsString(session('sso:verifier'), $ziel);
    }

    public function test_die_uebermittelte_pruefsumme_passt_zum_verifier(): void
    {
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();

        $dienst = app(\App\Services\Sso\CrmSsoService::class);
        $this->assertSame(
            $parameter['code_challenge'],
            $dienst->codeChallenge((string) session('sso:verifier')),
        );
    }

    public function test_eine_eigene_anmeldeseite_des_crm_wird_verwendet(): void
    {
        config()->set('sso.login_url', 'https://crm.mueller-holding.ag/anmeldung/extern');
        $this->crmAntworten();

        ['ziel' => $ziel] = $this->starten();

        $this->assertStringStartsWith('https://crm.mueller-holding.ag/anmeldung/extern?', $ziel);
    }

    // -----------------------------------------------------------------
    // Guter Fall
    // -----------------------------------------------------------------

    public function test_die_anmeldung_gelingt_und_verknuepft_das_konto(): void
    {
        $benutzer = $this->benutzer();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $antwort = $this->get(route('sso.callback', [
            'code' => 'ein-code',
            'state' => $parameter['state'],
        ]));

        $antwort->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($benutzer->fresh());

        $benutzer->refresh();
        $this->assertSame('b7d2c1f0-0000-4000-8000-000000000001', $benutzer->crm_subject);
        $this->assertNotNull($benutzer->crm_linked_at);
        $this->assertNotNull($benutzer->last_login_at);

        $this->assertDatabaseHas('login_attempts', [
            'user_id' => $benutzer->id,
            'successful' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.sso_login']);
    }

    public function test_der_code_wird_mit_verifier_und_geheimnis_eingeloest(): void
    {
        $this->benutzer();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $verifier = (string) session('sso:verifier');
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $this->get(route('sso.callback', ['code' => 'ein-code', 'state' => $parameter['state']]));

        Http::assertSent(function ($anfrage) use ($verifier) {
            if (! str_ends_with($anfrage->url(), '/api/v1/oidc/token')) {
                return false;
            }
            $daten = $anfrage->data();

            return $daten['grant_type'] === 'authorization_code'
                && $daten['code'] === 'ein-code'
                && $daten['code_verifier'] === $verifier
                && $daten['client_id'] === self::CLIENT_ID
                && $daten['client_secret'] === 'geheim'
                && $daten['redirect_uri'] === self::RUECKKEHR;
        });
    }

    public function test_beim_zweiten_mal_wird_ueber_die_kennung_zugeordnet(): void
    {
        $benutzer = $this->benutzer([
            'email' => 'alte-adresse@muellerhv.de',
            'crm_subject' => 'b7d2c1f0-0000-4000-8000-000000000001',
            'crm_linked_at' => now(),
        ]);
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        // Im CRM wurde die Adresse geaendert; die Kennung bleibt gleich.
        $this->tokenLiefern([
            'nonce' => $parameter['nonce'],
            'email' => 'neue-adresse@muellerhv.de',
        ]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($benutzer->fresh());
    }

    // -----------------------------------------------------------------
    // Jede Pruefung muss greifen
    // -----------------------------------------------------------------

    public static function abweisungen(): array
    {
        return [
            'falscher Aussteller' => [['iss' => 'https://fremder-anbieter.example']],
            'falscher Empfaenger' => [['aud' => 'eine-andere-anwendung']],
            'abgelaufen' => [['exp' => 1_600_000_000, 'iat' => 1_599_999_000]],
            'ohne Benutzerkennung' => [['sub' => '']],
        ];
    }

    #[DataProvider('abweisungen')]
    public function test_ein_untaugliches_identitaetstoken_wird_abgewiesen(array $abweichung): void
    {
        $this->benutzer();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern($abweichung + ['nonce' => $parameter['nonce']]);

        $antwort = $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]));

        $antwort->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.sso_failed']);
    }

    public function test_eine_fremde_signatur_wird_abgewiesen(): void
    {
        $this->benutzer();
        [$fremderSchluessel] = $this->schluesselpaar();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']], $fremderSchluessel);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_eine_fehlende_oder_falsche_zufallszahl_wird_abgewiesen(): void
    {
        $this->benutzer();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => 'eine-andere-zufallszahl']);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_ein_falscher_state_wird_abgewiesen(): void
    {
        $this->benutzer();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => 'untergeschoben']))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_ohne_vorbereitete_sitzung_wird_abgewiesen(): void
    {
        $this->benutzer();
        $this->crmAntworten();

        $this->get(route('sso.callback', ['code' => 'c', 'state' => 'irgendwas']))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_der_code_kann_nicht_zweimal_verwendet_werden(): void
    {
        $benutzer = $this->benutzer();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('dashboard'));

        $this->post(route('logout'));

        // Zweiter Aufruf mit denselben Daten: die Sitzungswerte sind verbraucht.
        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_ein_fehler_des_crm_wird_verstaendlich_gemeldet(): void
    {
        $this->benutzer();
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenStatus = 400;

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // -----------------------------------------------------------------
    // Zuordnung der Benutzer
    // -----------------------------------------------------------------

    public function test_ohne_benutzer_im_intranet_gibt_es_keinen_zugang(): void
    {
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern([
            'nonce' => $parameter['nonce'],
            'email' => 'fremder@example.org',
        ]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        // Es wird ausdruecklich kein Benutzer angelegt.
        $this->assertDatabaseMissing('users', ['email' => 'fremder@example.org']);
        $this->assertDatabaseHas('login_attempts', [
            'email' => 'fremder@example.org',
            'successful' => false,
        ]);
    }

    public function test_ein_deaktivierter_benutzer_kommt_nicht_hinein(): void
    {
        $this->benutzer(['is_active' => false]);
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_eine_fremde_kennung_uebernimmt_kein_bestehendes_konto(): void
    {
        $this->benutzer(['crm_subject' => 'eine-ganz-andere-kennung', 'crm_linked_at' => now()]);
        $this->crmAntworten();

        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // -----------------------------------------------------------------
    // Zweiter Faktor
    // -----------------------------------------------------------------

    public function test_ohne_anerkennung_der_crm_pruefung_folgt_der_zweite_faktor(): void
    {
        config()->set('sso.trust_mfa', false);

        $benutzer = $this->benutzer();
        $benutzer->saveTwoFactorFields([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaa-bbbb'],
        ]);

        $this->crmAntworten();
        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->assertSame($benutzer->id, session('two_factor:user_id'));
    }

    public function test_mit_anerkennung_der_crm_pruefung_entfaellt_der_zweite_faktor(): void
    {
        config()->set('sso.trust_mfa', true);

        $benutzer = $this->benutzer();
        $benutzer->saveTwoFactorFields([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaa-bbbb'],
        ]);

        $this->crmAntworten();
        ['parameter' => $parameter] = $this->starten();
        $this->tokenLiefern(['nonce' => $parameter['nonce']]);

        $this->get(route('sso.callback', ['code' => 'c', 'state' => $parameter['state']]))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($benutzer->fresh());
    }

    // -----------------------------------------------------------------
    // Notanmeldung
    // -----------------------------------------------------------------

    public function test_die_anmeldeseite_bietet_die_zentrale_anmeldung_an(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Anmelden über das CRM')
            ->assertSee('Notanmeldung für Administratoren');
    }

    public function test_ohne_eingerichtete_zentrale_anmeldung_bleibt_alles_wie_bisher(): void
    {
        config()->set('sso.enabled', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Anmelden über das CRM');
    }

    public function test_die_notanmeldung_bleibt_administratoren_vorbehalten(): void
    {
        $benutzer = $this->benutzer();
        $benutzer->forceFill(['password' => bcrypt('Pruefkennwort-2026!')])->save();

        $this->post(route('login.store'), [
            'email' => $benutzer->email,
            'password' => 'Pruefkennwort-2026!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.notanmeldung_abgelehnt']);
    }

    public function test_ein_administrator_darf_die_notanmeldung_verwenden(): void
    {
        $benutzer = User::factory()->create([
            'email' => 'admin@muellerhv.de',
            'is_active' => true,
            'password' => bcrypt('Pruefkennwort-2026!'),
        ]);
        $benutzer->assignRole('Administrator');

        $this->post(route('login.store'), [
            'email' => 'admin@muellerhv.de',
            'password' => 'Pruefkennwort-2026!',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($benutzer->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.notanmeldung']);
    }

    public function test_ohne_notanmeldung_ist_die_kennwortanmeldung_gesperrt(): void
    {
        config()->set('sso.allow_local_login', false);

        $benutzer = User::factory()->create([
            'email' => 'admin@muellerhv.de',
            'is_active' => true,
            'password' => bcrypt('Pruefkennwort-2026!'),
        ]);
        $benutzer->assignRole('Administrator');

        $this->post(route('login.store'), [
            'email' => 'admin@muellerhv.de',
            'password' => 'Pruefkennwort-2026!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Passwort vergessen?');
    }

    public function test_ohne_einrichtung_wird_der_start_abgewiesen(): void
    {
        config()->set('sso.client_id', '');

        $this->get(route('sso.start'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }
}
