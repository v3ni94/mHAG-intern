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
            $result['error'] = $e->getMessage();
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
}
