<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Täglicher Scan über Fälligkeiten, Abläufe und Wiedervorlagen
 * (Abschnitt 127 Masterprompt). Geplant in routes/console.php (05:30 Uhr).
 */
class ScanDueItems extends Command
{
    protected $signature = 'app:scan-due-items';

    protected $description = 'Prüft Fälligkeiten, Abläufe (Dokumente, Sicherheiten, Mandate) und Wiedervorlagen und erzeugt Benachrichtigungen.';

    public function handle(NotificationService $notifications): int
    {
        $created = $notifications->scanDueItems();

        $this->info(sprintf('%d Benachrichtigung(en) erzeugt.', $created));

        return self::SUCCESS;
    }
}
