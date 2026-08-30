<?php

namespace Tests\Feature;

use App\Enums\SignatureParticipantStatus;
use App\Enums\SignatureRequestStatus;
use App\Models\Document;
use App\Models\Entity;
use App\Models\Resolution;
use App\Models\Setting;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\Signature\DocuSign\DocuSignClient;
use App\Services\Signature\DocuSignAdapter;
use App\Services\Signature\ManualSignatureAdapter;
use App\Services\Signature\SignatureServiceInterface;
use App\Services\Storage\DocumentStorageInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Anbindung DocuSign eSignature (Abschnitte 99 bis 102).
 *
 * Geprüft wird gegen einen nachgebildeten Anbieter (Http::fake), damit der
 * Ablauf ohne echten Zugang belegbar ist: Anmeldung per JWT, Umschlag
 * erzeugen, Status abfragen, unterschriebene Fassung übernehmen, Rückkanal
 * mit HMAC-Prüfung. Kein Test versendet etwas nach außen.
 */
class DocuSignIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** Testschlüssel: nur für diese Tests erzeugt, nie in Betrieb verwendet. */
    private static ?string $privateKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Setting::set('security', 'two_factor_required_roles', []);
        Storage::fake(config('documents.disk'));
        Cache::flush();

        config([
            'signatures.provider' => 'docusign',
            'docusign.base_url' => 'https://demo.docusign.net/restapi',
            'docusign.account_id' => 'acct-4711',
            'docusign.user_id' => 'user-0815',
            'docusign.integration_key' => 'key-1234',
            'docusign.oauth_host' => 'account-d.docusign.com',
            'docusign.private_key' => $this->testKey(),
            'docusign.webhook_secret' => 'geheim',
            'docusign.timeout' => 5,
        ]);
    }

    private function testKey(): string
    {
        if (self::$privateKey === null) {
            $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($res, $pem);
            self::$privateKey = $pem;
        }

        return self::$privateKey;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Administrator');

        return $user;
    }

    private function pdf(): Document
    {
        return app(DocumentStorageInterface::class)->store(
            "%PDF-1.4\nUnterschrift\n%%EOF",
            'signaturen/test',
            'beschluss.pdf',
            ['doc_type' => 'other'],
        );
    }

    /** Beschluss mit zwei Unterzeichnern und angelegter Anfrage. */
    private function anfrage(): SignatureRequest
    {
        $company = Entity::create(['type' => 'company', 'display_name' => 'Müller Holding AG']);
        $person = Entity::create(['type' => 'person', 'display_name' => 'Timo Müller']);

        $resolution = Resolution::create([
            'resolution_number' => 'VOR-2026-001',
            'title' => 'Testbeschluss',
            'company_entity_id' => $company->id,
            'type' => 'board',
            'motion' => 'Antrag',
            'status' => 'draft',
            'recorded_at' => now(),
        ]);

        return app(DocuSignAdapter::class)->create($resolution, $this->pdf(), [
            ['entity_id' => $person->id, 'role' => 'Vorstand', 'email' => 'timo@example.test'],
            ['entity_id' => $company->id, 'role' => 'Aufsichtsratsvorsitzender', 'email' => 'ar@example.test'],
        ]);
    }

    private function fakeToken(): array
    {
        return [
            'account-d.docusign.com/oauth/token' => Http::response([
                'access_token' => 'tok-abc', 'token_type' => 'Bearer', 'expires_in' => 3600,
            ]),
        ];
    }

    // ------------------------------------------------------------------
    // Konfiguration und Auswahl des Signaturwegs
    // ------------------------------------------------------------------

    public function test_konfiguration_waehlt_den_adapter(): void
    {
        $this->assertInstanceOf(DocuSignAdapter::class, app(SignatureServiceInterface::class));

        config(['signatures.provider' => 'manual']);
        app()->forgetInstance(SignatureServiceInterface::class);
        $this->assertInstanceOf(ManualSignatureAdapter::class, app(SignatureServiceInterface::class));

        // Unbekannter Wert fällt bewusst auf den manuellen Weg zurück
        config(['signatures.provider' => 'irgendwas']);
        app()->forgetInstance(SignatureServiceInterface::class);
        $this->assertInstanceOf(ManualSignatureAdapter::class, app(SignatureServiceInterface::class));
    }

    public function test_fehlende_angaben_werden_im_klartext_benannt(): void
    {
        config([
            'docusign.base_url' => null,
            'docusign.account_id' => null,
            'docusign.user_id' => null,
            'docusign.integration_key' => null,
            'docusign.private_key' => null,
        ]);

        $fehlend = app(DocuSignClient::class)->missingRequirements();

        $this->assertFalse(app(DocuSignClient::class)->isConfigured());
        $this->assertCount(5, $fehlend);
        $this->assertStringContainsString('DOCUSIGN_BASE_URL', implode(' ', $fehlend));
        $this->assertStringContainsString('DOCUSIGN_ACCOUNT_ID', implode(' ', $fehlend));
        $this->assertStringContainsString('DOCUSIGN_USER_ID', implode(' ', $fehlend));
    }

    public function test_basis_url_wird_normalisiert(): void
    {
        config(['docusign.base_url' => 'https://demo.docusign.net']);
        $this->assertSame('https://demo.docusign.net/restapi', app(DocuSignClient::class)->baseUrl());

        config(['docusign.base_url' => 'https://demo.docusign.net/restapi/']);
        $this->assertSame('https://demo.docusign.net/restapi', app(DocuSignClient::class)->baseUrl());
    }

    public function test_ohne_konfiguration_wird_nichts_versendet(): void
    {
        Http::fake();
        $anfrage = $this->anfrage();
        config(['docusign.account_id' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nicht vollständig konfiguriert');

        app(DocuSignAdapter::class)->send($anfrage);
    }

    // ------------------------------------------------------------------
    // Anmeldung
    // ------------------------------------------------------------------

    public function test_anmeldung_sendet_eine_signierte_jwt_behauptung(): void
    {
        Http::fake($this->fakeToken());

        $token = app(DocuSignClient::class)->accessToken();

        $this->assertSame('tok-abc', $token);
        Http::assertSent(function (ClientRequest $request) {
            if (! str_contains($request->url(), '/oauth/token')) {
                return false;
            }
            $daten = $request->data();
            if (($daten['grant_type'] ?? '') !== 'urn:ietf:params:oauth:grant-type:jwt-bearer') {
                return false;
            }

            // Die Behauptung muss drei Teile haben und die Ansprüche enthalten.
            $teile = explode('.', (string) ($daten['assertion'] ?? ''));
            if (count($teile) !== 3) {
                return false;
            }
            $claims = json_decode(base64_decode(strtr($teile[1], '-_', '+/')), true);

            return $claims['iss'] === 'key-1234'
                && $claims['sub'] === 'user-0815'
                && $claims['aud'] === 'account-d.docusign.com'
                && $claims['scope'] === 'signature impersonation';
        });
    }

    public function test_token_wird_zwischengespeichert(): void
    {
        Http::fake($this->fakeToken());
        $client = app(DocuSignClient::class);

        $client->accessToken();
        $client->accessToken();
        $client->accessToken();

        Http::assertSentCount(1);
    }

    public function test_fehlende_zustimmung_wird_erklaert(): void
    {
        Http::fake([
            'account-d.docusign.com/oauth/token' => Http::response(['error' => 'consent_required'], 400),
        ]);

        try {
            app(DocuSignClient::class)->accessToken();
            $this->fail('Es wurde keine Ausnahme geworfen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('noch nicht zugestimmt', $e->getMessage());
            $this->assertStringContainsString('consent_required', $e->getMessage());
        }
    }

    public function test_unlesbarer_schluessel_wird_erklaert(): void
    {
        config(['docusign.private_key' => '-----BEGIN PRIVATE KEY-----unsinn-----END PRIVATE KEY-----']);
        Http::fake();

        try {
            app(DocuSignClient::class)->accessToken();
            $this->fail('Es wurde keine Ausnahme geworfen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('private RSA-Schlüssel konnte nicht gelesen werden', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Umschlag erzeugen und versenden
    // ------------------------------------------------------------------

    public function test_versand_erzeugt_umschlag_mit_pdf_und_unterzeichnern(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes' => Http::response([
                'envelopeId' => 'env-9999', 'status' => 'sent',
            ], 201),
        ]));

        $anfrage = $this->anfrage();
        $this->assertSame('docusign', $anfrage->provider);

        app(DocuSignAdapter::class)->send($anfrage);
        $anfrage->refresh();

        $this->assertSame('env-9999', $anfrage->external_id);
        $this->assertSame(SignatureRequestStatus::Sent, $anfrage->status);
        $this->assertTrue(
            $anfrage->participants->every(fn ($p) => $p->status === SignatureParticipantStatus::Sent),
        );

        Http::assertSent(function (ClientRequest $request) {
            if (! str_contains($request->url(), '/envelopes')) {
                return false;
            }
            $daten = $request->data();

            $signer = $daten['recipients']['signers'] ?? [];
            $adressen = array_column($signer, 'email');

            return ($daten['status'] ?? '') === 'sent'
                && ($daten['documents'][0]['fileExtension'] ?? '') === 'pdf'
                && str_contains(base64_decode($daten['documents'][0]['documentBase64'] ?? ''), '%PDF')
                && count($signer) === 2
                && in_array('timo@example.test', $adressen, true)
                && in_array('ar@example.test', $adressen, true)
                && $request->hasHeader('Authorization', 'Bearer tok-abc');
        });

        $this->assertDatabaseHas('audit_logs', ['action' => 'signatures.sent']);
    }

    public function test_versand_ohne_email_wird_abgelehnt(): void
    {
        Http::fake($this->fakeToken());
        $anfrage = $this->anfrage();
        $anfrage->participants()->first()->update(['email' => null]);

        try {
            app(DocuSignAdapter::class)->send($anfrage->fresh('participants'));
            $this->fail('Es wurde keine Ausnahme geworfen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('keine E-Mail-Adresse hinterlegt', $e->getMessage());
        }

        $this->assertNull($anfrage->fresh()->external_id);
    }

    public function test_fehler_des_anbieters_wird_gemeldet_und_nicht_als_versand_gewertet(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes' => Http::response([
                'errorCode' => 'USER_LACKS_PERMISSIONS', 'message' => 'Keine Rechte',
            ], 403),
        ]));

        $anfrage = $this->anfrage();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('signatures.send', $anfrage));

        $response->assertRedirect(route('signatures.show', $anfrage));
        $response->assertSessionHas('danger');
        $anfrage->refresh();
        $this->assertNull($anfrage->external_id);
        $this->assertSame(SignatureRequestStatus::Draft, $anfrage->status, 'Ohne Umschlag darf nichts als versendet gelten.');
    }

    // ------------------------------------------------------------------
    // Statusabfrage
    // ------------------------------------------------------------------

    public function test_statusabfrage_uebernimmt_teilnehmerstatus(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes' => Http::response([
                'envelopeId' => 'env-1', 'status' => 'sent',
            ], 201),
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes/env-1*' => Http::response([
                'status' => 'delivered',
                'recipients' => ['signers' => [
                    ['email' => 'timo@example.test', 'status' => 'signed'],
                    ['email' => 'ar@example.test', 'status' => 'delivered'],
                ]],
            ]),
        ]));

        $anfrage = $this->anfrage();
        app(DocuSignAdapter::class)->send($anfrage);
        app(DocuSignAdapter::class)->syncStatus($anfrage->fresh('participants'));

        $anfrage->refresh()->load('participants');
        $this->assertSame(SignatureRequestStatus::InProgress, $anfrage->status);

        $timo = $anfrage->participants->firstWhere('email', 'timo@example.test');
        $ar = $anfrage->participants->firstWhere('email', 'ar@example.test');
        $this->assertSame(SignatureParticipantStatus::Signed, $timo->status);
        $this->assertSame(SignatureParticipantStatus::Opened, $ar->status);
        $this->assertNotNull($timo->status_changed_at);
    }

    public function test_abgelehnter_umschlag_wird_uebernommen(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes' => Http::response([
                'envelopeId' => 'env-2', 'status' => 'sent',
            ], 201),
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes/env-2*' => Http::response([
                'status' => 'declined',
                'recipients' => ['signers' => [
                    ['email' => 'timo@example.test', 'status' => 'declined'],
                ]],
            ]),
        ]));

        $anfrage = $this->anfrage();
        app(DocuSignAdapter::class)->send($anfrage);
        app(DocuSignAdapter::class)->syncStatus($anfrage->fresh('participants'));

        $this->assertSame(SignatureRequestStatus::Declined, $anfrage->fresh()->status);
    }

    public function test_abgeschlossener_umschlag_uebernimmt_die_unterschriebene_fassung(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes' => Http::response([
                'envelopeId' => 'env-3', 'status' => 'sent',
            ], 201),
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes/env-3/documents/combined' => Http::response(
                "%PDF-1.4 unterschrieben\n%%EOF",
                200,
                ['Content-Type' => 'application/pdf'],
            ),
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes/env-3*' => Http::response([
                'status' => 'completed',
                'recipients' => ['signers' => [
                    ['email' => 'timo@example.test', 'status' => 'completed'],
                    ['email' => 'ar@example.test', 'status' => 'completed'],
                ]],
            ]),
        ]));

        $anfrage = $this->anfrage();
        $ausgangsDokument = $anfrage->document_id;
        app(DocuSignAdapter::class)->send($anfrage);
        app(DocuSignAdapter::class)->syncStatus($anfrage->fresh('participants'));

        $anfrage->refresh();
        $this->assertSame(SignatureRequestStatus::Completed, $anfrage->status);
        $this->assertNotSame($ausgangsDokument, $anfrage->document_id, 'Die signierte Fassung ist ein eigenes Dokument.');

        $signiert = Document::find($anfrage->document_id);
        $this->assertStringStartsWith('unterschrieben-', $signiert->original_filename);
        $this->assertStringContainsString('signaturen/docusign', $signiert->storage_path);

        // Der Vorgang selbst wird fortgeschrieben (geerbte Logik)
        $this->assertSame('signed', $anfrage->subject()->first()->status?->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'signatures.completed']);
    }

    public function test_ohne_umschlag_wird_der_status_abgeleitet(): void
    {
        Http::fake($this->fakeToken());
        $anfrage = $this->anfrage();

        // Kein external_id: es darf keine Abfrage beim Anbieter erfolgen.
        app(DocuSignAdapter::class)->syncStatus($anfrage);

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Rückkanal (Connect)
    // ------------------------------------------------------------------

    private function webhookPayload(string $envelopeId = 'env-4'): string
    {
        return json_encode([
            'event' => 'envelope-completed',
            'data' => ['envelopeId' => $envelopeId, 'envelopeSummary' => ['status' => 'completed']],
        ]);
    }

    private function signature(string $body, string $secret = 'geheim'): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    public function test_rueckkanal_ohne_signatur_wird_abgewiesen(): void
    {
        $body = $this->webhookPayload();

        $response = $this->call('POST', route('webhooks.docusign'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401);
    }

    public function test_rueckkanal_mit_falscher_signatur_wird_abgewiesen(): void
    {
        $body = $this->webhookPayload();

        $response = $this->call('POST', route('webhooks.docusign'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DOCUSIGN_SIGNATURE_1' => $this->signature($body, 'falsch'),
        ], $body);

        $response->assertStatus(401);
    }

    public function test_rueckkanal_ohne_hinterlegtes_geheimnis_nimmt_nichts_an(): void
    {
        config(['docusign.webhook_secret' => '']);
        $body = $this->webhookPayload();

        $response = $this->call('POST', route('webhooks.docusign'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DOCUSIGN_SIGNATURE_1' => $this->signature($body),
        ], $body);

        $response->assertStatus(503);
    }

    public function test_rueckkanal_fragt_den_status_beim_anbieter_ab(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes' => Http::response([
                'envelopeId' => 'env-4', 'status' => 'sent',
            ], 201),
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes/env-4/documents/combined' => Http::response(
                "%PDF-1.4 unterschrieben\n%%EOF", 200, ['Content-Type' => 'application/pdf'],
            ),
            'demo.docusign.net/restapi/v2.1/accounts/acct-4711/envelopes/env-4*' => Http::response([
                'status' => 'completed',
                'recipients' => ['signers' => [['email' => 'timo@example.test', 'status' => 'completed']]],
            ]),
        ]));

        $anfrage = $this->anfrage();
        app(DocuSignAdapter::class)->send($anfrage);

        $body = $this->webhookPayload('env-4');
        $response = $this->call('POST', route('webhooks.docusign'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DOCUSIGN_SIGNATURE_1' => $this->signature($body),
        ], $body);

        $response->assertOk();
        $this->assertSame(SignatureRequestStatus::Completed, $anfrage->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'signatures.webhook-received']);

        // Der Status kam aus der API, nicht aus der Nachricht
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/envelopes/env-4'));
    }

    public function test_rueckkanal_mit_unbekanntem_umschlag_bleibt_ohne_wirkung(): void
    {
        Http::fake($this->fakeToken());
        $body = $this->webhookPayload('env-unbekannt');

        $response = $this->call('POST', route('webhooks.docusign'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DOCUSIGN_SIGNATURE_1' => $this->signature($body),
        ], $body);

        $response->assertStatus(202);
    }

    // ------------------------------------------------------------------
    // Administration
    // ------------------------------------------------------------------

    public function test_verwaltungsseite_zeigt_konfiguration_ohne_geheimnisse(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.docusign.index'));

        $response->assertOk();
        $response->assertSee('API-Konto (Account-ID)');
        $response->assertSee('acct-4711');
        $response->assertSee('user-0815');
        $response->assertSee('https://demo.docusign.net/restapi');
        $response->assertSee('Als Text in der Konfiguration hinterlegt');
        // Der Schlüssel selbst darf nirgends erscheinen
        $response->assertDontSee('BEGIN PRIVATE KEY');
        $response->assertDontSee('geheim');
    }

    public function test_verbindungstest_meldet_erfolg_und_versendet_nichts(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'account-d.docusign.com/oauth/userinfo' => Http::response([
                'name' => 'API Benutzer', 'email' => 'api@example.test',
                'accounts' => [[
                    'account_id' => 'acct-4711', 'account_name' => 'Müller Holding AG',
                    'base_uri' => 'https://demo.docusign.net', 'is_default' => true,
                ]],
            ]),
        ]));

        $response = $this->actingAs($this->admin())->post(route('admin.docusign.test'));

        $response->assertSessionHas('success');
        $this->assertTrue(Setting::get('docusign', 'last_test')['ok']);
        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), '/envelopes'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.docusign_tested']);
    }

    public function test_verbindungstest_erkennt_falsches_konto(): void
    {
        Http::fake(array_merge($this->fakeToken(), [
            'account-d.docusign.com/oauth/userinfo' => Http::response([
                'name' => 'API Benutzer',
                'accounts' => [['account_id' => 'ein-anderes-konto', 'account_name' => 'Fremd']],
            ]),
        ]));

        $response = $this->actingAs($this->admin())->post(route('admin.docusign.test'));

        $response->assertSessionHas('error');
        $this->assertFalse(Setting::get('docusign', 'last_test')['ok']);
    }

    public function test_verbindungstest_ohne_konfiguration_meldet_die_luecken(): void
    {
        config(['docusign.user_id' => null]);

        $response = $this->actingAs($this->admin())->post(route('admin.docusign.test'));

        $response->assertSessionHas('info');
        $this->assertStringContainsString('DOCUSIGN_USER_ID', Setting::get('docusign', 'last_test')['error']);
    }

    public function test_verwaltungsseite_ist_fuer_externe_gesperrt(): void
    {
        $extern = User::factory()->create(['is_active' => true]);
        $extern->assignRole('Partner');

        $this->actingAs($extern)->get(route('admin.docusign.index'))->assertForbidden();
        $this->actingAs($extern)->post(route('admin.docusign.test'))->assertForbidden();
    }
}
