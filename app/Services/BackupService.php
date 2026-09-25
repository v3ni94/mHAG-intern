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
     * @return array{success: bool, file: ?string, size: ?int, removed?: int, error: ?string, finished_at: string}
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

            $file = $this->compressIfConfigured($file);

            $result['success'] = true;
            $result['file'] = basename($file);
            $result['size'] = filesize($file) ?: null;
            $result['removed'] = $this->pruneOldBackups($path);
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
        $path = (string) config('backup.path', 'storage/backups');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
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

    /**
     * Sicherung komprimieren, sofern eingestellt. Schlaegt das Komprimieren
     * fehl, bleibt die unkomprimierte Datei bestehen: eine vorhandene
     * Sicherung ist wichtiger als eine kleine.
     */
    private function compressIfConfigured(string $file): string
    {
        if (! config('backup.compress', true) || str_ends_with($file, '.gz')) {
            return $file;
        }

        $ziel = $file.'.gz';
        $quelle = @fopen($file, 'rb');

        if ($quelle === false) {
            return $file;
        }

        $senke = @gzopen($ziel, 'wb6');

        if ($senke === false) {
            fclose($quelle);

            return $file;
        }

        $vollstaendig = true;

        while (! feof($quelle)) {
            $block = fread($quelle, 1024 * 512);
            if ($block === false || ($block !== '' && gzwrite($senke, $block) === false)) {
                $vollstaendig = false;
                break;
            }
        }

        fclose($quelle);
        gzclose($senke);

        if (! $vollstaendig || ! is_file($ziel)) {
            @unlink($ziel);

            return $file;
        }

        @unlink($file);

        return $ziel;
    }

    /**
     * Sicherungen entfernen, die aelter als die Aufbewahrungsdauer sind.
     * Es werden ausschliesslich Dateien mit dem eigenen Namensmuster
     * beruecksichtigt, nie fremde Dateien im selben Verzeichnis.
     *
     * @return int Zahl der entfernten Dateien
     */
    private function pruneOldBackups(string $path): int
    {
        $tage = (int) config('backup.retention_days', 30);

        if ($tage <= 0 || ! is_dir($path)) {
            return 0;
        }

        $grenze = now()->subDays($tage)->getTimestamp();
        $entfernt = 0;

        foreach (scandir($path) ?: [] as $name) {
            if (! preg_match('/^backup-\\d{4}-\\d{2}-\\d{2}_\\d{6}\\.(sql|sqlite)(\\.gz)?$/', $name)) {
                continue;
            }

            $voll = $path.DIRECTORY_SEPARATOR.$name;
            $zeit = @filemtime($voll);

            if ($zeit !== false && $zeit < $grenze && @unlink($voll)) {
                $entfernt++;
            }
        }

        return $entfernt;
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
