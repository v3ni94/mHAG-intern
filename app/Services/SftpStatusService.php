<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SFTP-Verbindungstest für den Admin-Bereich (Abschnitt 65 Masterprompt):
 * Lese-, Schreib- und Umbenennungstest mit einer Testdatei. Das Ergebnis
 * wird in Setting('sftp', 'last_test') abgelegt.
 */
class SftpStatusService
{
    public function test(): array
    {
        $result = [
            'configured' => false,
            'online' => false,
            'read' => false,
            'write' => false,
            'rename' => false,
            'error' => null,
            'tested_at' => now()->toDateTimeString(),
        ];

        $host = (string) config('filesystems.disks.sftp.host');
        if (trim($host) === '') {
            // Nicht konfiguriert: neutraler Status, kein Fehler.
            Setting::set('sftp', 'last_test', $result);

            return $result;
        }

        $result['configured'] = true;

        $disk = Storage::disk('sftp');
        $probe = 'statuscheck/verbindungstest-'.Str::lower(Str::random(12)).'.txt';
        $renamed = $probe.'.umbenannt';
        $payload = 'Müller Holding AG Intranet Verbindungstest '.now()->toDateTimeString();

        try {
            // Schreibtest
            $disk->put($probe, $payload);
            $result['write'] = true;
            $result['online'] = true;

            // Lesetest
            $readBack = $disk->get($probe);
            $result['read'] = $readBack === $payload;
            if (! $result['read']) {
                $result['error'] = 'Lesetest fehlgeschlagen: Inhalt der Testdatei weicht ab.';
            }

            // Umbenennungstest
            $disk->move($probe, $renamed);
            $result['rename'] = $disk->exists($renamed);
            if (! $result['rename'] && $result['error'] === null) {
                $result['error'] = 'Umbenennungstest fehlgeschlagen.';
            }
        } catch (\Throwable $e) {
            $result['error'] = $this->readableError($e->getMessage());
        } finally {
            // Testdateien aufräumen (Fehler hier nicht als Testergebnis werten).
            foreach ([$probe, $renamed] as $path) {
                try {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                } catch (\Throwable) {
                    // bewusst ignoriert
                }
            }
        }

        Setting::set('sftp', 'last_test', $result);

        if ($result['error'] === null && $result['read'] && $result['write'] && $result['rename']) {
            Setting::set('sftp', 'last_success', $result['tested_at']);
        } else {
            Setting::set('sftp', 'last_error', [
                'at' => $result['tested_at'],
                'message' => $result['error'] ?? 'Teilprüfung fehlgeschlagen.',
            ]);
        }

        return $result;
    }

    /**
     * Meldungen der SFTP-Bibliothek in eine verwertbare Auskunft uebersetzen.
     * Die Originalmeldung bleibt erhalten, damit nichts verloren geht.
     */
    protected function readableError(string $message): string
    {
        $hinweise = [
            'Unable to load private key' => 'Der SSH-Schlüssel konnte nicht gelesen werden. '
                .'Wenn die Anmeldung per Passwort erfolgen soll, müssen SFTP_PRIVATE_KEY und SFTP_PASSPHRASE '
                .'in der .env vollständig entfernt werden; ein leerer Eintrag genügt nicht. '
                .'Bei Schlüsselanmeldung muss SFTP_PRIVATE_KEY den vollständigen Pfad zur Schlüsseldatei enthalten, '
                .'die für den Webserver-Benutzer lesbar sein muss.',
            'Could not login with username' => 'Anmeldung abgelehnt. Bitte SFTP_USERNAME und SFTP_PASSWORD prüfen. '
                .'Enthält das Passwort Leerzeichen oder Sonderzeichen, muss es in der .env in Anführungszeichen stehen.',
            'Cannot connect to' => 'Der Server ist nicht erreichbar. Bitte SFTP_HOST und SFTP_PORT prüfen '
                .'(Standard 22) sowie ob ausgehende Verbindungen zugelassen sind.',
            'Connection closed prematurely' => 'Die Verbindung wurde vom Server getrennt. Häufige Ursache ist ein '
                .'falscher Port oder ein Zugang, der nur FTP und nicht SFTP erlaubt.',
            'No such file or directory' => 'Das Basisverzeichnis existiert nicht. Bitte SFTP_ROOT_PATH prüfen; '
                .'der Pfad muss auf dem Zielsystem vorhanden und beschreibbar sein.',
            'Permission denied' => 'Keine Schreibrechte im Basisverzeichnis. Bitte SFTP_ROOT_PATH und die Rechte prüfen.',
            'fingerprint' => 'Der Host-Key des Servers weicht vom hinterlegten Fingerprint ab. '
                .'Bitte SFTP_HOST_FINGERPRINT prüfen; bei ungeklärter Abweichung nicht verbinden.',
        ];

        foreach ($hinweise as $muster => $hinweis) {
            if (stripos($message, $muster) !== false) {
                return $hinweis.' (Meldung des Servers: '.$message.')';
            }
        }

        return $message;
    }
}
