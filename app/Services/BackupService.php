<?php

namespace App\Services;

use App\Models\Setting;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Datenbank-Backup (Abschnitt 129 Masterprompt).
 *
 * SQLite: Kopie der Datenbankdatei. MariaDB/MySQL: mysqldump, sofern auf dem
 * Server verfügbar; andernfalls verständliche deutsche Fehlermeldung.
 * Status wird in Setting('backup','last_run') protokolliert.
 */
class BackupService
{
    /**
     * Backup ausführen.
     *
     * @return array{success: bool, file: ?string, size: ?int, error: ?string, finished_at: string}
     */
    public function run(): array
    {
        $startedAt = now();
        $path = $this->backupPath();

        if (! is_dir($path)) {
            @mkdir($path, 0770, true);
        }

        $result = [
            'success' => false,
            'file' => null,
            'size' => null,
            'error' => null,
            'finished_at' => $startedAt->toDateTimeString(),
        ];

        try {
            if (! is_dir($path) || ! is_writable($path)) {
                throw new \RuntimeException('Backup-Verzeichnis ist nicht beschreibbar: '.$path);
            }

            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");

            $file = match ($driver) {
                'sqlite' => $this->backupSqlite($connection, $path),
                'mysql', 'mariadb' => $this->backupMysql($connection, $path),
                default => throw new \RuntimeException('Für den Datenbanktreiber "'.$driver.'" ist kein Backup-Verfahren hinterlegt.'),
            };

            $result['success'] = true;
            $result['file'] = basename($file);
            $result['size'] = filesize($file) ?: null;
            $result['finished_at'] = now()->toDateTimeString();
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            $result['finished_at'] = now()->toDateTimeString();
        }

        Setting::set('backup', 'last_run', $result);
        AuditService::log($result['success'] ? 'backup.completed' : 'backup.failed', null, [], $result);

        return $result;
    }

    /**
     * Statusübersicht: letzter Lauf und vorhandene Backup-Dateien.
     *
     * @return array{last_run: ?array, path: string, files: array<int, array{name: string, size: int, modified_at: string}>}
     */
    public function status(): array
    {
        $path = $this->backupPath();
        $files = [];

        if (is_dir($path)) {
            foreach (scandir($path, SCANDIR_SORT_DESCENDING) ?: [] as $name) {
                $full = $path.DIRECTORY_SEPARATOR.$name;
                if (! is_file($full) || str_starts_with($name, '.')) {
                    continue;
                }
                $files[] = [
                    'name' => $name,
                    'size' => (int) filesize($full),
                    'modified_at' => date('Y-m-d H:i:s', (int) filemtime($full)),
                ];
            }
        }

        usort($files, fn ($a, $b) => strcmp($b['modified_at'], $a['modified_at']));
        $lastRun = Setting::get('backup', 'last_run');

        return [
            'last_run' => is_array($lastRun) ? $lastRun : null,
            'path' => $path,
            'files' => $files,
        ];
    }

    public function backupPath(): string
    {
        return rtrim((string) (env('BACKUP_PATH') ?: storage_path('backups')), DIRECTORY_SEPARATOR);
    }

    /** Vollständigen Pfad einer vorhandenen Backup-Datei liefern (nur Dateiname, kein Traversal). */
    public function filePath(string $name): ?string
    {
        if ($name !== basename($name) || str_starts_with($name, '.')) {
            return null;
        }
        $full = $this->backupPath().DIRECTORY_SEPARATOR.$name;

        return is_file($full) ? $full : null;
    }

    private function backupSqlite(string $connection, string $path): string
    {
        $database = (string) config("database.connections.{$connection}.database");

        if ($database === ':memory:' || $database === '') {
            throw new \RuntimeException('Die SQLite-Datenbank liegt im Arbeitsspeicher und kann nicht als Datei gesichert werden.');
        }
        if (! is_file($database)) {
            throw new \RuntimeException('SQLite-Datenbankdatei nicht gefunden: '.$database);
        }

        $target = $path.DIRECTORY_SEPARATOR.'backup-'.now()->format('Y-m-d_His').'.sqlite';

        if (! @copy($database, $target)) {
            throw new \RuntimeException('Die SQLite-Datei konnte nicht kopiert werden.');
        }

        return $target;
    }

    private function backupMysql(string $connection, string $path): string
    {
        $binary = (new ExecutableFinder)->find('mariadb-dump') ?? (new ExecutableFinder)->find('mysqldump');

        if ($binary === null) {
            throw new \RuntimeException('mysqldump bzw. mariadb-dump ist auf diesem Server nicht verfügbar. Bitte das Paket installieren oder das Backup serverseitig einrichten (siehe docs/RESTORE.md).');
        }

        $config = config("database.connections.{$connection}");
        $target = $path.DIRECTORY_SEPARATOR.'backup-'.now()->format('Y-m-d_His').'.sql';

        $command = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? ''),
            '--single-transaction',
            '--routines',
            '--result-file='.$target,
            $config['database'] ?? '',
        ];

        $process = new Process($command, null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($target)) {
            @unlink($target);
            throw new \RuntimeException('mysqldump ist fehlgeschlagen: '.trim($process->getErrorOutput() ?: 'unbekannter Fehler'));
        }

        return $target;
    }
}
