<?php

namespace Tests\Feature\Organisation;

use App\Services\BackupService;
use Illuminate\Support\Facades\File;

/**
 * Der taegliche Lauf legt ohne Grenze immer neue Sicherungen ab. Auf einem
 * Server, den sich mehrere Anwendungen teilen, fuellt das mit der Zeit das
 * Dateisystem und trifft damit auch die anderen Anwendungen.
 */
class BackupAufbewahrungTest extends OrganisationTestCase
{
    private string $basis;

    private string $backupDir;

    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basis = storage_path('framework/testing/backup-aufbewahrung-'.uniqid());
        $this->backupDir = $this->basis.'/backups';
        $this->dbFile = $this->basis.'/test-datenbank.sqlite';

        File::ensureDirectoryExists($this->basis);
        File::ensureDirectoryExists($this->backupDir);
        file_put_contents($this->dbFile, str_repeat('sqlite-testinhalt ', 500));

        config()->set('backup.path', $this->backupDir);
        config()->set('database.connections.sqlite.database', $this->dbFile);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->basis);

        parent::tearDown();
    }

    private function altesBackup(string $name, int $tageAlt): string
    {
        $pfad = $this->backupDir.DIRECTORY_SEPARATOR.$name;
        file_put_contents($pfad, 'inhalt');
        touch($pfad, now()->subDays($tageAlt)->getTimestamp());

        return $pfad;
    }

    public function test_sicherung_wird_komprimiert(): void
    {
        config()->set('backup.compress', true);

        $ergebnis = app(BackupService::class)->run();

        $this->assertTrue($ergebnis['success'], (string) ($ergebnis['error'] ?? ''));
        $this->assertStringEndsWith('.gz', (string) $ergebnis['file']);

        $datei = $this->backupDir.DIRECTORY_SEPARATOR.$ergebnis['file'];
        $this->assertFileExists($datei);

        // Der unkomprimierte Zwischenstand darf nicht liegen bleiben.
        $this->assertFileDoesNotExist(substr($datei, 0, -3));

        // Der Inhalt muss sich wieder herstellen lassen.
        $this->assertSame(file_get_contents($this->dbFile), gzdecode(file_get_contents($datei)));
        $this->assertLessThan(filesize($this->dbFile), filesize($datei));
    }

    public function test_ohne_komprimierung_bleibt_die_datei_unveraendert(): void
    {
        config()->set('backup.compress', false);

        $ergebnis = app(BackupService::class)->run();

        $this->assertTrue($ergebnis['success'], (string) ($ergebnis['error'] ?? ''));
        $this->assertStringEndsWith('.sqlite', (string) $ergebnis['file']);
    }

    public function test_sicherungen_ausserhalb_der_aufbewahrungsdauer_werden_entfernt(): void
    {
        config()->set('backup.retention_days', 30);

        $alt = $this->altesBackup('backup-2026-01-01_020000.sql.gz', 45);
        $aeltererSqlite = $this->altesBackup('backup-2026-01-02_020000.sqlite', 31);
        $neu = $this->altesBackup('backup-2026-09-01_020000.sql.gz', 10);

        $ergebnis = app(BackupService::class)->run();

        $this->assertTrue($ergebnis['success'], (string) ($ergebnis['error'] ?? ''));
        $this->assertSame(2, $ergebnis['removed']);
        $this->assertFileDoesNotExist($alt);
        $this->assertFileDoesNotExist($aeltererSqlite);
        $this->assertFileExists($neu);
    }

    public function test_fremde_dateien_werden_nie_entfernt(): void
    {
        config()->set('backup.retention_days', 1);

        $fremd = $this->backupDir.DIRECTORY_SEPARATOR.'uebergabe-steuerberater.sql';
        file_put_contents($fremd, 'wichtig');
        touch($fremd, now()->subDays(400)->getTimestamp());

        $handbuch = $this->backupDir.DIRECTORY_SEPARATOR.'README.md';
        file_put_contents($handbuch, 'Hinweise');
        touch($handbuch, now()->subDays(400)->getTimestamp());

        $ergebnis = app(BackupService::class)->run();

        $this->assertTrue($ergebnis['success'], (string) ($ergebnis['error'] ?? ''));
        $this->assertSame(0, $ergebnis['removed']);
        $this->assertFileExists($fremd);
        $this->assertFileExists($handbuch);
    }

    public function test_aufbewahrung_null_schaltet_das_aufraeumen_ab(): void
    {
        config()->set('backup.retention_days', 0);

        $alt = $this->altesBackup('backup-2020-01-01_020000.sql.gz', 2000);

        $ergebnis = app(BackupService::class)->run();

        $this->assertTrue($ergebnis['success'], (string) ($ergebnis['error'] ?? ''));
        $this->assertSame(0, $ergebnis['removed']);
        $this->assertFileExists($alt);
    }

    public function test_die_sicherung_des_laufenden_tages_wird_nicht_entfernt(): void
    {
        config()->set('backup.retention_days', 1);

        $ergebnis = app(BackupService::class)->run();

        $this->assertTrue($ergebnis['success'], (string) ($ergebnis['error'] ?? ''));
        $this->assertFileExists($this->backupDir.DIRECTORY_SEPARATOR.$ergebnis['file']);
    }
}
