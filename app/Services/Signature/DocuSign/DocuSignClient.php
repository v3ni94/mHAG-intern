<?php

namespace App\Services\Signature\DocuSign;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zugriff auf die DocuSign eSignature API (Abschnitte 99 bis 102).
 *
 * Anmeldung über JWT Grant: die Anwendung handelt im Namen eines
 * API-Benutzers, ohne dass sich eine Person anmelden muss. Der private
 * Schlüssel bleibt auf dem Server, das Zugriffstoken wird bis kurz vor
 * Ablauf zwischengespeichert.
 *
 * Grundsätze:
 * - Ohne vollständige Konfiguration wird nichts gesendet und nichts
 *   abgefragt. missingRequirements() benennt die fehlenden Angaben im
 *   Klartext.
 * - Fehlermeldungen des Anbieters werden übersetzt und um den nächsten
 *   Prüfschritt ergänzt; die Originalmeldung bleibt erhalten.
 * - Geheimnisse (privater Schlüssel, Token) werden nie protokolliert und
 *   nie angezeigt.
 */
class DocuSignClient
{
    private const CACHE_KEY = 'docusign.access_token';

    /** Gültigkeit der JWT-Behauptung in Sekunden (DocuSign erlaubt maximal eine Stunde). */
    private const ASSERTION_LIFETIME = 3600;

    /** Fehlende fachliche und technische Voraussetzungen im Klartext. */
    public function missingRequirements(): array
    {
        $fehlt = [];

        if (! $this->baseUrl()) {
            $fehlt[] = 'Die Basis-URL fehlt (DOCUSIGN_BASE_URL), zum Beispiel https://demo.docusign.net/restapi.';
        }
        if (! config('docusign.account_id')) {
            $fehlt[] = 'Das API-Konto fehlt (DOCUSIGN_ACCOUNT_ID).';
        }
        if (! config('docusign.user_id')) {
            $fehlt[] = 'Der API-Benutzer fehlt (DOCUSIGN_USER_ID).';
        }
        if (! config('docusign.integration_key')) {
            $fehlt[] = 'Der Integrationsschlüssel fehlt (DOCUSIGN_INTEGRATION_KEY).';
        }
        if (! $this->privateKey()) {
            $fehlt[] = 'Der private RSA-Schlüssel fehlt oder ist nicht lesbar (DOCUSIGN_PRIVATE_KEY). '
                .'Zulässig sind ein Pfad zur Schlüsseldatei oder der Schlüssel im PEM-Format.';
        }
        if (! extension_loaded('openssl')) {
            $fehlt[] = 'Die PHP-Erweiterung openssl ist nicht verfügbar; ohne sie kann die Anmeldung nicht signiert werden.';
        }

        return $fehlt;
    }

    public function isConfigured(): bool
    {
        return $this->missingRequirements() === [];
    }

    /** Normalisierte Basis-URL einschließlich /restapi, ohne Schrägstrich am Ende. */
    public function baseUrl(): ?string
    {
        $url = trim((string) config('docusign.base_url'));
        if ($url === '') {
            return null;
        }

        $url = rtrim($url, '/');
        if (! str_ends_with($url, '/restapi')) {
            $url .= '/restapi';
        }

        return $url;
    }

    public function oauthHost(): string
    {
        return trim((string) config('docusign.oauth_host')) ?: 'account-d.docusign.com';
    }

    /**
     * Adresse für die einmalige Zustimmung des API-Benutzers. Ohne diese
     * Zustimmung verweigert DocuSign die Anmeldung mit consent_required.
     */
    public function consentUrl(?string $redirectUri = null): string
    {
        return 'https://'.$this->oauthHost().'/oauth/auth?'.http_build_query([
            'response_type' => 'code',
            'scope' => 'signature impersonation',
            'client_id' => (string) config('docusign.integration_key'),
            'redirect_uri' => $redirectUri ?: rtrim((string) config('app.url'), '/'),
        ]);
    }

    /** Privater Schlüssel als PEM, aus Datei oder direkt aus der Konfiguration. */
    private function privateKey(): ?string
    {
        $wert = (string) config('docusign.private_key');
        if (trim($wert) === '') {
            return null;
        }

        if (str_contains($wert, '-----BEGIN')) {
            return $wert;
        }

        if (is_file($wert) && is_readable($wert)) {
            $inhalt = (string) file_get_contents($wert);

            return str_contains($inhalt, '-----BEGIN') ? $inhalt : null;
        }

        return null;
    }

    /**
     * Zugriffstoken über JWT Grant. Das Token wird bis eine Minute vor
     * Ablauf zwischengespeichert, damit nicht bei jedem Aufruf eine
     * Anmeldung erfolgt.
     */
    public function accessToken(bool $frisch = false): string
    {
        $this->guardConfigured();

        if (! $frisch) {
            $vorhanden = Cache::get(self::CACHE_KEY);
            if (is_string($vorhanden) && $vorhanden !== '') {
                return $vorhanden;
            }
        }

        $assertion = $this->buildAssertion();

        $antwort = Http::asForm()
            ->timeout((int) config('docusign.timeout', 20))
            ->post('https://'.$this->oauthHost().'/oauth/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if ($antwort->failed()) {
            throw new RuntimeException($this->readableError($antwort));
        }

        $token = (string) $antwort->json('access_token');
        if ($token === '') {
            throw new RuntimeException('Die Anmeldung bei DocuSign lieferte kein Zugriffstoken.');
        }

        $gueltig = (int) ($antwort->json('expires_in') ?: 3600);
        Cache::put(self::CACHE_KEY, $token, max(60, $gueltig - 60));

        return $token;
    }

    /** JWT nach RS256 für den JWT Grant. */
    private function buildAssertion(): string
    {
        $jetzt = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => (string) config('docusign.integration_key'),
            'sub' => (string) config('docusign.user_id'),
            'aud' => $this->oauthHost(),
            'iat' => $jetzt,
            'exp' => $jetzt + self::ASSERTION_LIFETIME,
            'scope' => 'signature impersonation',
        ];

        $daten = $this->base64Url(json_encode($header, JSON_UNESCAPED_SLASHES))
            .'.'.$this->base64Url(json_encode($claims, JSON_UNESCAPED_SLASHES));

        $schluessel = openssl_pkey_get_private($this->privateKey());
        if ($schluessel === false) {
            throw new RuntimeException(
                'Der private RSA-Schlüssel konnte nicht gelesen werden. Bitte DOCUSIGN_PRIVATE_KEY prüfen: '
                .'zulässig sind ein Pfad zur Schlüsseldatei oder der Schlüssel im PEM-Format. '
                .'Die Datei muss für den Webserver-Benutzer lesbar sein.',
            );
        }

        $signatur = '';
        $erfolg = openssl_sign($daten, $signatur, $schluessel, OPENSSL_ALGO_SHA256);
        if (! $erfolg) {
            throw new RuntimeException('Die Anmeldung konnte nicht signiert werden (openssl_sign).');
        }

        return $daten.'.'.$this->base64Url($signatur);
    }

    private function base64Url(string $wert): string
    {
        return rtrim(strtr(base64_encode($wert), '+/', '-_'), '=');
    }

    /** Angemeldeter API-Benutzer, für den Verbindungstest. */
    public function userInfo(): array
    {
        $antwort = Http::withToken($this->accessToken())
            ->timeout((int) config('docusign.timeout', 20))
            ->get('https://'.$this->oauthHost().'/oauth/userinfo');

        if ($antwort->failed()) {
            throw new RuntimeException($this->readableError($antwort));
        }

        return (array) $antwort->json();
    }

    /** Umschlag anlegen. Rückgabe der Antwort von DocuSign, u. a. envelopeId. */
    public function createEnvelope(array $payload): array
    {
        $antwort = $this->request()->post($this->accountUrl().'/envelopes', $payload);

        if ($antwort->failed()) {
            throw new RuntimeException($this->readableError($antwort));
        }

        return (array) $antwort->json();
    }

    /** Umschlag samt Empfängerstatus abfragen. */
    public function envelope(string $envelopeId): array
    {
        $antwort = $this->request()->get($this->accountUrl().'/envelopes/'.$envelopeId, ['include' => 'recipients']);

        if ($antwort->failed()) {
            throw new RuntimeException($this->readableError($antwort));
        }

        return (array) $antwort->json();
    }

    /** Unterschriebene Gesamtfassung als PDF (Rohdaten). */
    public function combinedDocument(string $envelopeId): string
    {
        $antwort = $this->request()
            ->withHeaders(['Accept' => 'application/pdf'])
            ->get($this->accountUrl().'/envelopes/'.$envelopeId.'/documents/combined');

        if ($antwort->failed()) {
            throw new RuntimeException($this->readableError($antwort));
        }

        return $antwort->body();
    }

    private function request()
    {
        $this->guardConfigured();

        return Http::withToken($this->accessToken())
            ->timeout((int) config('docusign.timeout', 20))
            ->acceptJson();
    }

    private function accountUrl(): string
    {
        return $this->baseUrl().'/v2.1/accounts/'.config('docusign.account_id');
    }

    private function guardConfigured(): void
    {
        $fehlt = $this->missingRequirements();
        if ($fehlt !== []) {
            throw new RuntimeException('DocuSign ist nicht vollständig konfiguriert. '.implode(' ', $fehlt));
        }
    }

    /**
     * Antwort des Anbieters in eine verwertbare Auskunft übersetzen. Die
     * Originalmeldung bleibt erhalten, damit nichts verloren geht.
     */
    private function readableError(Response $antwort): string
    {
        $roh = trim((string) $antwort->body());
        $kurz = mb_substr($roh, 0, 500);
        $code = $antwort->status();

        $fehler = (string) ($antwort->json('error') ?? '');
        $meldung = (string) ($antwort->json('message') ?? $antwort->json('errorCode') ?? '');

        $hinweise = [
            'consent_required' => 'Der API-Benutzer hat der Anwendung noch nicht zugestimmt. Die Zustimmung ist '
                .'einmalig über die Zustimmungsadresse zu erteilen; sie steht auf der Seite Administration, DocuSign.',
            'invalid_grant' => 'Die Anmeldung wurde abgelehnt. Bitte API-Benutzer (DOCUSIGN_USER_ID), '
                .'Integrationsschlüssel (DOCUSIGN_INTEGRATION_KEY), Anmeldeserver (DOCUSIGN_OAUTH_HOST) und den '
                .'privaten Schlüssel prüfen. Test- und Live-Konten haben getrennte Schlüssel.',
            'PARTNER_AUTHENTICATION_FAILED' => 'Das Konto hat die Anmeldung abgelehnt. Bitte prüfen, ob '
                .'Basis-URL und Anmeldeserver zum Konto passen (Test gegen Test, Live gegen Live).',
            'ACCOUNT_NOT_FOUND' => 'Das angegebene API-Konto ist auf diesem Server nicht vorhanden. Bitte '
                .'DOCUSIGN_ACCOUNT_ID und DOCUSIGN_BASE_URL prüfen.',
            'USER_LACKS_PERMISSIONS' => 'Der API-Benutzer hat im Konto nicht die erforderlichen Rechte zum Versenden.',
            'ENVELOPE_DOES_NOT_EXIST' => 'Der Umschlag ist im Konto nicht vorhanden. Möglicherweise wurde er in '
                .'einem anderen Konto oder in der Testumgebung erzeugt.',
        ];

        foreach ($hinweise as $muster => $hinweis) {
            if (stripos($roh, $muster) !== false) {
                return $hinweis.' (Antwort '.$code.': '.$kurz.')';
            }
        }

        if ($code === 401) {
            return 'DocuSign hat den Zugriff verweigert (401). Das Zugriffstoken ist ungültig oder abgelaufen. '
                .'Bitte den Verbindungstest ausführen. (Antwort: '.$kurz.')';
        }

        return 'DocuSign hat mit Fehler '.$code.' geantwortet'
            .($fehler !== '' ? ' ('.$fehler.')' : '')
            .($meldung !== '' ? ': '.$meldung : '')
            .'. Antwort: '.$kurz;
    }

    /** Zwischengespeichertes Token verwerfen, z. B. nach Konfigurationsänderung. */
    public function forgetToken(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
