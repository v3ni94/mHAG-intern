<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wacht ueber Einstellungen, die nur im Zusammenspiel richtig sind. Sie
 * lassen sich einzeln aendern, ohne dass sofort etwas auffaellt, und fallen
 * dann erst im Betrieb auf.
 */
class BetriebskonfigurationTest extends TestCase
{
    private function datei(string $pfad): string
    {
        $voll = base_path($pfad);
        $this->assertFileExists($voll, "Die Datei {$pfad} fehlt.");

        return (string) file_get_contents($voll);
    }

    private function inBytes(string $wert): int
    {
        $wert = trim($wert);
        $zahl = (int) $wert;

        return match (strtoupper(substr($wert, -1))) {
            'G' => $zahl * 1024 * 1024 * 1024,
            'M' => $zahl * 1024 * 1024,
            'K' => $zahl * 1024,
            default => $zahl,
        };
    }

    public function test_vorgaengerschluessel_werden_ohne_leerzeichen_gelesen(): void
    {
        $vorher = $_SERVER['APP_PREVIOUS_KEYS'] ?? null;
        $_SERVER['APP_PREVIOUS_KEYS'] = 'base64:AAAA, base64:BBBB ,,  ';

        try {
            $konfiguration = require config_path('app.php');

            $this->assertSame(
                ['base64:AAAA', 'base64:BBBB'],
                $konfiguration['previous_keys'],
                'Ein Leerzeichen hinter dem Komma darf keinen unbrauchbaren Schluessel erzeugen.',
            );
        } finally {
            if ($vorher === null) {
                unset($_SERVER['APP_PREVIOUS_KEYS']);
            } else {
                $_SERVER['APP_PREVIOUS_KEYS'] = $vorher;
            }
        }
    }

    public function test_ohne_vorgaengerschluessel_bleibt_die_liste_leer(): void
    {
        $vorher = $_SERVER['APP_PREVIOUS_KEYS'] ?? null;
        $_SERVER['APP_PREVIOUS_KEYS'] = '';

        try {
            $konfiguration = require config_path('app.php');

            $this->assertSame([], $konfiguration['previous_keys']);
        } finally {
            if ($vorher === null) {
                unset($_SERVER['APP_PREVIOUS_KEYS']);
            } else {
                $_SERVER['APP_PREVIOUS_KEYS'] = $vorher;
            }
        }
    }

    public function test_die_upload_grenzen_des_containers_liegen_ueber_der_grenze_der_anwendung(): void
    {
        $anwendung = (int) config('documents.max_size_kb') * 1024;

        preg_match('/^upload_max_filesize\s*=\s*(\S+)/m', $this->datei('infra/php/intranet.ini'), $upload);
        preg_match('/^post_max_size\s*=\s*(\S+)/m', $this->datei('infra/php/intranet.ini'), $post);
        preg_match('/client_max_body_size\s+(\S+?);/', $this->datei('infra/nginx/intranet.conf'), $nginx);

        $this->assertNotEmpty($upload, 'upload_max_filesize fehlt.');
        $this->assertNotEmpty($post, 'post_max_size fehlt.');
        $this->assertNotEmpty($nginx, 'client_max_body_size fehlt.');

        $this->assertGreaterThan(
            $anwendung,
            $this->inBytes($upload[1]),
            'PHP wuerde Dateien verwerfen, die die Anwendung noch zulaesst.',
        );
        $this->assertGreaterThanOrEqual(
            $this->inBytes($upload[1]),
            $this->inBytes($post[1]),
            'post_max_size muss mindestens so gross sein wie upload_max_filesize.',
        );
        $this->assertGreaterThanOrEqual(
            $this->inBytes($post[1]),
            $this->inBytes($nginx[1]),
            'Nginx wuerde die Anfrage vor PHP abweisen.',
        );
    }

    public function test_sicherungen_gelangen_nicht_in_das_abbild(): void
    {
        $inhalt = $this->datei('.dockerignore');

        foreach (['backups', '.env', 'storage/backups/*', 'vendor'] as $eintrag) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote($eintrag, '/').'$/m',
                $inhalt,
                "In .dockerignore fehlt der Eintrag {$eintrag}.",
            );
        }
    }

    public function test_nginx_ersetzt_die_absenderadresse_nicht(): void
    {
        $inhalt = $this->datei('infra/nginx/intranet.conf');

        // Ersetzt Nginx REMOTE_ADDR, erkennt Laravel die Gegenstelle nicht
        // mehr als anerkannten Proxy und verwirft die Weiterleitungskopfzeilen.
        $this->assertStringNotContainsString('real_ip_header', $inhalt);
        $this->assertStringNotContainsString('set_real_ip_from', $inhalt);
    }

    public function test_statische_dateien_werden_nicht_unveraenderlich_zwischengespeichert(): void
    {
        $inhalt = $this->datei('infra/nginx/intranet.conf');

        // Stilvorlagen tragen keinen Inhaltsstempel im Namen. "immutable"
        // wuerde nach einer Lieferung den alten Stand festhalten.
        $this->assertStringNotContainsString('immutable', $inhalt);
    }

    public function test_der_zeitplan_liegt_nicht_im_gemeinsamen_volume(): void
    {
        $inhalt = $this->datei('infra/compose.yaml');

        // Ein Volume ueber das gesamte storage-Verzeichnis wuerde den
        // uebersetzten Blade-Zwischenspeicher zwischen den Containern teilen.
        $this->assertStringNotContainsString(':/var/www/html/storage'."\n", $inhalt);
        $this->assertStringContainsString('dokumente:/var/www/html/storage/app', $inhalt);
        $this->assertStringContainsString('sicherungen:/var/www/html/storage/backups', $inhalt);
    }
}
