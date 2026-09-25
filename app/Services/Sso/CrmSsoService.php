<?php

namespace App\Services\Sso;

use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Zentrale Anmeldung über das CRM (OpenID Connect, Authorization Code mit
 * PKCE).
 *
 * Aufgabenteilung:
 *   CRM       prüft die Identität einschließlich zweitem Faktor und stellt
 *             ein signiertes Identitätstoken aus.
 *   Intranet  prüft dieses Token und ordnet es einem bereits vorhandenen
 *             Benutzer zu. Es legt keine Benutzer an: wer Zugang zu den Daten
 *             der Holding erhält, entscheidet die Benutzerverwaltung des
 *             Intranets.
 *
 * Geprüft werden Signatur, Aussteller, Empfänger, Laufzeit und die einmalige
 * Zufallszahl. Der Austausch des Codes läuft über eine eigene Verbindung zum
 * CRM, nicht über den Browser des Benutzers.
 */
class CrmSsoService
{
    public function istEingerichtet(): bool
    {
        return (bool) config('sso.enabled')
            && $this->text('sso.issuer') !== ''
            && $this->text('sso.client_id') !== ''
            && $this->text('sso.redirect_uri') !== '';
    }

    /**
     * Beginn der Anmeldung: Zufallswerte erzeugen und die Adresse liefern,
     * an die der Browser geschickt wird.
     *
     * @return array{url: string, state: string, nonce: string, verifier: string}
     */
    public function anmeldungVorbereiten(): array
    {
        if (! $this->istEingerichtet()) {
            throw new SsoException(
                'Die zentrale Anmeldung ist nicht vollständig eingerichtet.',
                'nicht_eingerichtet',
            );
        }

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = $this->codeVerifier();

        $parameter = [
            'response_type' => 'code',
            'client_id' => $this->text('sso.client_id'),
            'redirect_uri' => $this->text('sso.redirect_uri'),
            'scope' => implode(' ', (array) config('sso.scopes', ['openid'])),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->codeChallenge($verifier),
            'code_challenge_method' => 'S256',
        ];

        $ziel = $this->text('sso.login_url') !== ''
            ? $this->text('sso.login_url')
            : $this->endpunkt('authorization_endpoint');

        return [
            'url' => $ziel.(str_contains($ziel, '?') ? '&' : '?').http_build_query($parameter),
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
        ];
    }

    /**
     * Rückkehr aus dem CRM: Code gegen Token tauschen und das
     * Identitätstoken prüfen.
     *
     * @return array<string, mixed> geprüfte Angaben aus dem Identitätstoken
     */
    public function codeEinloesen(string $code, string $verifier, string $nonce): array
    {
        $antwort = Http::asForm()
            ->timeout((int) config('sso.timeout', 10))
            ->post($this->endpunkt('token_endpoint'), array_filter([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->text('sso.redirect_uri'),
                'client_id' => $this->text('sso.client_id'),
                'client_secret' => $this->text('sso.client_secret') ?: null,
                'code_verifier' => $verifier,
            ], static fn ($wert) => $wert !== null));

        if ($antwort->failed()) {
            throw new SsoException(
                'Das CRM hat die Anmeldung nicht bestätigt.',
                'token_endpunkt',
                ['status' => $antwort->status()],
            );
        }

        $idToken = (string) $antwort->json('id_token');

        if ($idToken === '') {
            throw new SsoException('Das CRM hat kein Identitätstoken geliefert.', 'kein_id_token');
        }

        return $this->identitaetstokenPruefen($idToken, $nonce);
    }

    /**
     * Identitätstoken prüfen: Signatur, Aussteller, Empfänger, Laufzeit und
     * einmalige Zufallszahl.
     *
     * @return array<string, mixed>
     */
    public function identitaetstokenPruefen(string $idToken, string $nonce): array
    {
        JWT::$leeway = max(0, (int) config('sso.leeway', 60));

        try {
            $angaben = (array) JWT::decode($idToken, JWK::parseKeySet($this->signaturschluessel()));
        } catch (\Throwable $e) {
            // Ein unbekannter Schlüssel kann daher rühren, dass das CRM
            // gewechselt hat. Einmal ohne Zwischenspeicher nachsehen.
            $this->zwischenspeicherLeeren();

            try {
                $angaben = (array) JWT::decode($idToken, JWK::parseKeySet($this->signaturschluessel()));
            } catch (\Throwable $zweiter) {
                throw new SsoException(
                    'Das Identitätstoken des CRM ist nicht gültig.',
                    'signatur',
                    ['meldung' => $zweiter->getMessage()],
                );
            }
        }

        $aussteller = (string) ($angaben['iss'] ?? '');
        if (rtrim($aussteller, '/') !== $this->text('sso.issuer')) {
            throw new SsoException('Das Identitätstoken stammt nicht vom erwarteten Aussteller.', 'aussteller');
        }

        $empfaenger = $angaben['aud'] ?? null;
        $empfaengerListe = is_array($empfaenger) ? $empfaenger : [$empfaenger];
        if (! in_array($this->text('sso.client_id'), array_map('strval', $empfaengerListe), true)) {
            throw new SsoException('Das Identitätstoken ist nicht für diese Anwendung bestimmt.', 'empfaenger');
        }

        if (! isset($angaben['nonce']) || ! hash_equals($nonce, (string) $angaben['nonce'])) {
            throw new SsoException('Die Anmeldung konnte nicht eindeutig zugeordnet werden.', 'nonce');
        }

        if (($angaben['sub'] ?? '') === '') {
            throw new SsoException('Das Identitätstoken nennt keinen Benutzer.', 'sub');
        }

        return $angaben;
    }

    /**
     * Den Benutzer des Intranets zu den Angaben des CRM finden.
     *
     * Zuerst über die dauerhafte Kennung, danach einmalig über die
     * E-Mail-Adresse. Es wird nie ein Benutzer angelegt.
     */
    public function benutzerFinden(array $angaben): User
    {
        $kennung = (string) ($angaben['sub'] ?? '');
        $email = strtolower(trim((string) ($angaben['email'] ?? '')));

        $benutzer = User::where('crm_subject', $kennung)->first();

        if ($benutzer === null && $email !== '') {
            $benutzer = User::whereRaw('lower(email) = ?', [$email])->first();

            if ($benutzer !== null && $benutzer->crm_subject !== null && $benutzer->crm_subject !== $kennung) {
                throw new SsoException(
                    'Dieses Benutzerkonto ist bereits mit einem anderen Konto im CRM verbunden.',
                    'bereits_verbunden',
                );
            }
        }

        if ($benutzer === null) {
            throw new SsoException(
                'Für dieses Konto besteht kein Zugang zum Intranet. Bitte wenden Sie sich an die Administration.',
                'kein_benutzer',
                ['email' => $email],
            );
        }

        if (! $benutzer->is_active) {
            throw new SsoException(
                'Dieses Benutzerkonto ist im Intranet deaktiviert.',
                'deaktiviert',
            );
        }

        if ($benutzer->crm_subject === null) {
            $benutzer->forceFill([
                'crm_subject' => $kennung,
                'crm_linked_at' => now(),
            ])->save();
        }

        return $benutzer;
    }

    /** Ein Endpunkt aus dem Discovery-Dokument des CRM. */
    public function endpunkt(string $name): string
    {
        $wert = (string) ($this->discovery()[$name] ?? '');

        if ($wert === '') {
            throw new SsoException(
                'Das CRM meldet keinen Endpunkt "'.$name.'".',
                'discovery',
            );
        }

        return $wert;
    }

    /** @return array<string, mixed> */
    public function discovery(): array
    {
        return Cache::remember(
            'sso:discovery:'.md5($this->discoveryUrl()),
            (int) config('sso.cache_seconds', 600),
            function (): array {
                $antwort = Http::timeout((int) config('sso.timeout', 10))->get($this->discoveryUrl());

                if ($antwort->failed()) {
                    throw new SsoException(
                        'Das CRM ist zurzeit nicht erreichbar.',
                        'discovery',
                        ['status' => $antwort->status()],
                    );
                }

                return (array) $antwort->json();
            },
        );
    }

    /** @return array{keys: array<int, array<string, mixed>>} */
    public function signaturschluessel(): array
    {
        return Cache::remember(
            'sso:jwks:'.md5($this->discoveryUrl()),
            (int) config('sso.cache_seconds', 600),
            function (): array {
                $antwort = Http::timeout((int) config('sso.timeout', 10))->get($this->endpunkt('jwks_uri'));

                if ($antwort->failed()) {
                    throw new SsoException(
                        'Die Signaturschlüssel des CRM sind nicht abrufbar.',
                        'jwks',
                        ['status' => $antwort->status()],
                    );
                }

                return (array) $antwort->json();
            },
        );
    }

    public function zwischenspeicherLeeren(): void
    {
        Cache::forget('sso:discovery:'.md5($this->discoveryUrl()));
        Cache::forget('sso:jwks:'.md5($this->discoveryUrl()));
    }

    private function discoveryUrl(): string
    {
        $eigen = $this->text('sso.discovery_url');

        return $eigen !== ''
            ? $eigen
            : $this->text('sso.issuer').'/.well-known/openid-configuration';
    }

    private function codeVerifier(): string
    {
        // 43 bis 128 Zeichen aus dem zulässigen Vorrat (RFC 7636).
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    public function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function text(string $schluessel): string
    {
        return trim((string) config($schluessel, ''));
    }
}
