<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\SftpStatusService;
use Illuminate\Http\Request;

/**
 * SFTP-Status im Admin-Bereich (Abschnitt 65 Masterprompt).
 * Zeigt die Konfiguration OHNE Geheimnisse (keine Passwörter, Schlüssel
 * oder Passphrasen) sowie das Ergebnis des letzten Verbindungstests.
 */
class SftpController extends Controller
{
    public function index()
    {
        $config = config('filesystems.disks.sftp');

        return view('admin.sftp.index', [
            'activeDisk' => config('documents.disk'),
            'configuration' => [
                'Host' => $config['host'] ?: null,
                'Port' => $config['port'] ?? 22,
                'Benutzername' => $config['username'] ?: null,
                'Basisverzeichnis' => $config['root'] ?? null,
                'Timeout (Sekunden)' => $config['timeout'] ?? null,
                'Authentifizierung' => ! empty($config['privateKey'])
                    ? 'SSH-Schlüssel (empfohlen)'
                    : (! empty($config['password']) ? 'Passwort' : 'Nicht konfiguriert'),
                'Host-Key-Prüfung' => ! empty($config['hostFingerprint']) ? 'Fingerprint hinterlegt' : 'Kein Fingerprint hinterlegt',
            ],
            'lastTest' => Setting::get('sftp', 'last_test'),
            'lastSuccess' => Setting::get('sftp', 'last_success'),
            'lastError' => Setting::get('sftp', 'last_error'),
        ]);
    }

    public function test(Request $request, SftpStatusService $service)
    {
        $result = $service->test();

        AuditService::log('admin.sftp_tested', null, [], $result);

        if (! $result['configured']) {
            return back()->with('info', 'SFTP ist nicht konfiguriert (SFTP_HOST leer). Die Dokumentenablage nutzt derzeit die lokale Disk.');
        }

        if ($result['error'] === null && $result['read'] && $result['write'] && $result['rename']) {
            return back()->with('success', 'SFTP-Verbindungstest erfolgreich: Lesen, Schreiben und Umbenennen funktionieren.');
        }

        return back()->with('error', 'SFTP-Verbindungstest fehlgeschlagen: '.($result['error'] ?? 'Teilprüfung fehlgeschlagen.'));
    }
}
