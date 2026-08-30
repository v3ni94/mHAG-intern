<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\Signature\DocuSign\DocuSignClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DocuSign im Admin-Bereich (Abschnitte 99 bis 102).
 *
 * Zeigt die Konfiguration OHNE Geheimnisse (kein privater Schlüssel, kein
 * Token, kein Webhook-Geheimnis), benennt fehlende Angaben im Klartext und
 * bietet einen echten Verbindungstest: Anmeldung per JWT und Abruf des
 * angemeldeten API-Benutzers. Es wird nichts versendet.
 */
class DocuSignController extends Controller
{
    public function index(DocuSignClient $client): View
    {
        $privatKey = (string) config('docusign.private_key');

        return view('admin.docusign.index', [
            'aktiverAnbieter' => (string) config('signatures.provider'),
            'istKonfiguriert' => $client->isConfigured(),
            'fehlend' => $client->missingRequirements(),
            'konfiguration' => [
                'Aktiver Signaturweg' => (string) config('signatures.provider'),
                'Basis-URL' => $client->baseUrl(),
                'API-Konto (Account-ID)' => config('docusign.account_id'),
                'API-Benutzer (User-ID)' => config('docusign.user_id'),
                'Integrationsschlüssel' => config('docusign.integration_key'),
                'Anmeldeserver' => $client->oauthHost(),
                'Privater Schlüssel' => $this->keyHinweis($privatKey),
                'Rückkanal (Connect)' => trim((string) config('docusign.webhook_secret')) !== ''
                    ? 'Geheimnis hinterlegt'
                    : 'Kein Geheimnis hinterlegt, Benachrichtigungen werden abgewiesen',
                'Adresse für Connect' => route('webhooks.docusign'),
                'Zeitlimit (Sekunden)' => config('docusign.timeout'),
                'Ankertext Signaturfeld' => config('docusign.anchor_string'),
            ],
            'zustimmungsadresse' => config('docusign.integration_key') ? $client->consentUrl() : null,
            'letzterTest' => Setting::get('docusign', 'last_test'),
        ]);
    }

    /**
     * Verbindungstest: Anmeldung per JWT und Abruf des API-Benutzers. Es wird
     * kein Umschlag erzeugt und nichts versendet.
     */
    public function test(Request $request, DocuSignClient $client): RedirectResponse
    {
        $fehlend = $client->missingRequirements();
        if ($fehlend !== []) {
            Setting::set('docusign', 'last_test', [
                'ok' => false,
                'error' => 'Nicht vollständig konfiguriert: '.implode(' ', $fehlend),
                'tested_at' => now()->toDateTimeString(),
            ]);

            return back()->with('info', 'DocuSign ist nicht vollständig konfiguriert. '.implode(' ', $fehlend));
        }

        try {
            $client->forgetToken();
            $info = $client->userInfo();

            $konten = collect($info['accounts'] ?? [])->map(fn ($a) => [
                'account_id' => $a['account_id'] ?? null,
                'account_name' => $a['account_name'] ?? null,
                'base_uri' => $a['base_uri'] ?? null,
                'is_default' => (bool) ($a['is_default'] ?? false),
            ])->all();

            $gewaehlt = (string) config('docusign.account_id');
            $passt = collect($konten)->contains(fn ($k) => (string) $k['account_id'] === $gewaehlt);

            $ergebnis = [
                'ok' => $passt,
                'user_name' => $info['name'] ?? null,
                'user_email' => $info['email'] ?? null,
                'accounts' => $konten,
                'error' => $passt ? null : 'Die Anmeldung war erfolgreich, aber das eingetragene API-Konto '
                    .'gehört nicht zu diesem Benutzer. Bitte DOCUSIGN_ACCOUNT_ID prüfen.',
                'tested_at' => now()->toDateTimeString(),
            ];

            Setting::set('docusign', 'last_test', $ergebnis);
            AuditService::log('admin.docusign_tested', null, [], [
                'ok' => $ergebnis['ok'],
                'accounts' => count($konten),
            ]);

            return $passt
                ? back()->with('success', 'Verbindung zu DocuSign erfolgreich. Angemeldet als '
                    .($info['name'] ?? 'unbekannt').'. Es wurde nichts versendet.')
                : back()->with('error', $ergebnis['error']);
        } catch (\Throwable $e) {
            Setting::set('docusign', 'last_test', [
                'ok' => false,
                'error' => $e->getMessage(),
                'tested_at' => now()->toDateTimeString(),
            ]);
            AuditService::log('admin.docusign_tested', null, [], ['ok' => false]);

            return back()->with('error', 'Verbindung zu DocuSign fehlgeschlagen: '.$e->getMessage());
        }
    }

    /** Auskunft über den Schlüssel, ohne ihn preiszugeben. */
    private function keyHinweis(string $wert): string
    {
        if (trim($wert) === '') {
            return 'Nicht hinterlegt';
        }
        if (str_contains($wert, '-----BEGIN')) {
            return 'Als Text in der Konfiguration hinterlegt';
        }
        if (is_file($wert) && is_readable($wert)) {
            return 'Datei vorhanden und lesbar';
        }
        if (is_file($wert)) {
            return 'Datei vorhanden, aber für den Webserver nicht lesbar';
        }

        return 'Datei nicht gefunden';
    }
}
