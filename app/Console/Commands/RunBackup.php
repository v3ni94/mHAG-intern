<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Tägliches Datenbank-Backup (Abschnitt 129 Masterprompt).
 * Geplant in routes/console.php (02:00 Uhr).
 */
class RunBackup extends Command
{
    protected $signature = 'app:backup-run';

    protected $description = 'Erstellt ein Datenbank-Backup nach BACKUP_PATH (Standard: storage/backups).';

    public function handle(BackupService $backups): int
    {
        $result = $backups->run();

        if ($result['success']) {
            $this->info(sprintf('Backup erstellt: %s (%s Bytes).', $result['file'], number_format((float) $result['size'], 0, ',', '.')));

            return self::SUCCESS;
        }

        $this->error('Backup fehlgeschlagen: '.($result['error'] ?? 'unbekannter Fehler'));

        return self::FAILURE;
    }
}
