<?php

namespace Tests\Feature\Organisation;

use App\Models\Setting;
use App\Services\BackupService;
use Illuminate\Support\Facades\File;

class BackupTest extends OrganisationTestCase
{
    private string $backupDir;

    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $base = storage_path('framework/testing/backup-test-'.uniqid());
        $this->backupDir = $base.'/backups';
        $this->dbFile = $base.'/test-datenbank.sqlite';

        File::ensureDirectoryExists($base);
        file_put_contents($this->dbFile, 'sqlite-testinhalt');

        // BackupService liest BACKUP_PATH aus der Umgebung
        putenv('BACKUP_PATH='.$this->backupDir);
        $_ENV['BACKUP_PATH'] = $this->backupDir;
        $_SERVER['BACKUP_PATH'] = $this->backupDir;

        config()->set('database.connections.sqlite.database', $this->dbFile);
    }

    protected function tearDown(): void
    {
        putenv('BACKUP_PATH');
        unset($_ENV['BACKUP_PATH'], $_SERVER['BACKUP_PATH']);
        File::deleteDirectory(dirname($this->backupDir));

        parent::tearDown();
    }

    public function test_backup_run_erzeugt_datei_und_status(): void
    {
        $result = app(BackupService::class)->run();

        $this->assertTrue($result['success'], 'Backup muss erfolgreich sein: '.($result['error'] ?? ''));
        $this->assertNotNull($result['file']);
        $this->assertFileExists($this->backupDir.DIRECTORY_SEPARATOR.$result['file']);
        $this->assertGreaterThan(0, $result['size']);

        // Status wird in den Einstellungen protokolliert (Abschnitt 129)
        $lastRun = Setting::get('backup', 'last_run');
        $this->assertIsArray($lastRun);
        $this->assertTrue($lastRun['success']);
        $this->assertSame($result['file'], $lastRun['file']);

        $status = app(BackupService::class)->status();
        $this->assertCount(1, $status['files']);
        $this->assertSame($result['file'], $status['files'][0]['name']);
    }

    public function test_backup_command_und_admin_oberflaeche(): void
    {
        $this->artisan('app:backup-run')->assertSuccessful();

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertSee('Backups');
        $response->assertSee('Erfolgreich');
    }

    public function test_backup_run_ueber_admin_route_mit_audit(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.backups.run'));
        $response->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.completed']);
        $this->assertNotEmpty(File::files($this->backupDir));
    }

    public function test_in_memory_datenbank_liefert_verstaendlichen_fehler(): void
    {
        config()->set('database.connections.sqlite.database', ':memory:');

        $result = app(BackupService::class)->run();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Arbeitsspeicher', (string) $result['error']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.failed']);
    }
}
