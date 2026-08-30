<?php

namespace Tests\Unit;

use App\Support\EnvFileInspector;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Der Inspektor muss dasselbe Urteil treffen wie der Parser, der die .env
 * beim Start tatsächlich liest. Deshalb wird jeder Fall doppelt geprüft:
 * einmal durch den Inspektor, einmal durch vlucas/phpdotenv selbst.
 */
class EnvFileInspectorTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool}> */
    public static function faelle(): array
    {
        return [
            'einfache Zeile' => ["APP_ENV=production\n", false],
            'Name mit Punkt' => ["A.B=1\n", false],
            'Name mit Unterstrich am Anfang' => ["_A=1\n", false],
            'Name klein geschrieben' => ["abc=1\n", false],
            'export vorangestellt' => ["export APP_ENV=production\n", false],
            'leerer Wert' => ["MAIL_PASSWORD=\n", false],
            'Kommentarzeile' => ["# Hinweis\nAPP_ENV=x\n", false],
            'nachgestellter Kommentar' => ["APP_ENV=x # Hinweis\n", false],
            'Wert in Anfuehrungszeichen mit Leerzeichen' => ["APP_NAME=\"Müller Holding AG\"\n", false],
            'Wert in einfachen Anfuehrungszeichen' => ["APP_NAME='Müller Holding AG'\n", false],
            'mehrzeiliger Wert korrekt geschlossen' => ["KEY=\"zeile1\nzeile2\"\nAPP_ENV=x\n", false],
            'Base64-Zeile mit Gleichheitszeichen' => ["b3BlbnNzaC1rZXk=\n", false],
            'Leerzeile und Einrueckung' => ["\n\tAPP_ENV=x\n   \n", false],

            'Zeile ohne Gleichheitszeichen' => ["APP_ENV=x\n-----END OPENSSH PRIVATE KEY-----\n", true],
            'Name mit Bindestrich' => ["APP-ENV=x\n", true],
            'kein Name vor dem Gleichheitszeichen' => ["=x\n", true],
            'Leerzeichen im Wert ohne Anfuehrungszeichen' => ["APP_NAME=Müller Holding AG\n", true],
            'Text nach schliessendem Anfuehrungszeichen' => ["APP_NAME=\"Müller\" AG\n", true],
        ];
    }

    #[Test]
    #[DataProvider('faelle')]
    public function inspektor_urteilt_wie_der_parser(string $inhalt, bool $erwarteFehler): void
    {
        $befunde = EnvFileInspector::inspect($inhalt);

        $this->assertSame(
            $erwarteFehler,
            EnvFileInspector::hatFehler($befunde),
            'Urteil des Inspektors weicht ab. Befunde: '
            .json_encode($befunde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        // Gegenprobe am echten Parser: was er ablehnt, muss der Inspektor als
        // Fehler melden, und was er annimmt, darf er nicht als Fehler melden.
        $verzeichnis = sys_get_temp_dir().'/envpruefung-'.bin2hex(random_bytes(6));
        mkdir($verzeichnis, 0700, true);
        file_put_contents($verzeichnis.'/.env', $inhalt);

        $parserFehler = false;
        try {
            Dotenv::createArrayBacked($verzeichnis)->load();
        } catch (Throwable) {
            $parserFehler = true;
        } finally {
            @unlink($verzeichnis.'/.env');
            @rmdir($verzeichnis);
        }

        $this->assertSame($parserFehler, EnvFileInspector::hatFehler($befunde),
            'Inspektor und Parser sind sich nicht einig.');
    }

    #[Test]
    public function nennt_die_zeilennummer_der_stoerenden_zeile(): void
    {
        $inhalt = "APP_ENV=production\nAPP_DEBUG=false\n-----END OPENSSH PRIVATE KEY-----\nAPP_URL=https://beispiel.de\n";

        $fehler = EnvFileInspector::fehler(EnvFileInspector::inspect($inhalt));

        $this->assertCount(1, $fehler);
        $this->assertSame(3, $fehler[0]['line']);
        $this->assertStringContainsString('kein Gleichheitszeichen', $fehler[0]['message']);
    }

    #[Test]
    public function nicht_geschlossenes_anfuehrungszeichen_ist_eine_warnung_mit_folgehinweis(): void
    {
        $inhalt = "SFTP_PRIVATE_KEY=\"-----BEGIN OPENSSH PRIVATE KEY-----\nAPP_ENV=production\n";

        $befunde = EnvFileInspector::inspect($inhalt);

        $this->assertFalse(EnvFileInspector::hatFehler($befunde));
        $this->assertCount(1, $befunde);
        $this->assertSame(EnvFileInspector::WARNUNG, $befunde[0]['severity']);
        $this->assertSame(1, $befunde[0]['line']);
        $this->assertStringContainsString('nicht geschlossen', $befunde[0]['message']);
    }

    #[Test]
    public function nicht_ersetzter_platzhalter_wird_als_warnung_gemeldet(): void
    {
        $befunde = EnvFileInspector::inspect("DB_PASSWORD=<hier-eintragen>\n");

        $this->assertFalse(EnvFileInspector::hatFehler($befunde));
        $this->assertSame(EnvFileInspector::WARNUNG, $befunde[0]['severity']);
        $this->assertStringContainsString('spitzen Klammern', $befunde[0]['message']);
    }

    #[Test]
    public function befunde_geben_niemals_werte_aus_der_env_wieder(): void
    {
        $geheim = 'b3BlbnNzaC1rZXktdjEAAAAABG5vbmVGEHEIM';
        $inhalt = "APP_ENV=production\n"
            ."$geheim\n"
            ."-----END OPENSSH PRIVATE KEY-----\n"
            ."APP_NAME=\"Müller\" GEHEIMERZUSATZ\n"
            ."=SEHRGEHEIM\n";

        $befunde = EnvFileInspector::inspect($inhalt);
        $text = json_encode($befunde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertTrue(EnvFileInspector::hatFehler($befunde));
        foreach ([$geheim, 'GEHEIMERZUSATZ', 'SEHRGEHEIM', 'OPENSSH'] as $verboten) {
            $this->assertStringNotContainsString($verboten, (string) $text,
                'Ein Befund gibt Inhalt aus der .env wieder. Das darf nicht passieren, weil eine '
                .'gestörte Zeile Teil eines Schlüssels sein kann.');
        }
    }

    #[Test]
    public function fehlende_datei_wird_als_fehler_gemeldet(): void
    {
        $befunde = EnvFileInspector::inspectFile(sys_get_temp_dir().'/gibt-es-nicht-'.bin2hex(random_bytes(4)));

        $this->assertTrue(EnvFileInspector::hatFehler($befunde));
        $this->assertStringContainsString('nicht gefunden', $befunde[0]['message']);
    }

    #[Test]
    public function echte_env_beispieldatei_ist_fehlerfrei(): void
    {
        $befunde = EnvFileInspector::inspectFile(base_path('.env.example'));

        $this->assertSame([], EnvFileInspector::fehler($befunde),
            'Die ausgelieferte .env.example darf keine Zeile enthalten, die den Start verhindert.');
    }
}
