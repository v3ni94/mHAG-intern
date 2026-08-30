<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Geplante Aufgaben (Abschnitte 127, 129, 141 Masterprompt)
|--------------------------------------------------------------------------
| Voraussetzung in Produktion: Cron-Eintrag für `php artisan schedule:run`
| (siehe docs/DEPLOYMENT.md).
*/

// Fälligkeiten, Abläufe (Dokumente, Ausweise, Sicherheiten, Mandate) und
// Wiedervorlagen prüfen; erzeugt In-App-Benachrichtigungen.
Schedule::command('app:scan-due-items')->dailyAt('05:30');

// Tägliches Datenbank-Backup nach BACKUP_PATH.
Schedule::command('app:backup-run')->dailyAt('02:00');
