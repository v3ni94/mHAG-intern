<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Geplante Aufgaben (Abschnitte 127, 129, 141 Masterprompt)
|--------------------------------------------------------------------------
| Voraussetzung in Produktion: ein Dienst oder Cron-Eintrag, der
| `php artisan schedule:run` jede Minute aufruft. Im Containerbetrieb
| uebernimmt das der Dienst "scheduler" (siehe docs/DEPLOYMENT-SERVER.md),
| auf dem Webspace der Cron-Eintrag (siehe docs/DEPLOYMENT.md).
|
| Die Uhrzeiten gelten in der Zeitzone des Geschaeftsbetriebs
| (config/app.php, display_timezone). Ohne diese Angabe liefe der Zeitplan
| in UTC und damit im Sommer eine Stunde, im Winter zwei Stunden versetzt.
*/

$zeitzone = (string) config('app.display_timezone', 'Europe/Berlin');

// Faelligkeiten, Ablaeufe (Dokumente, Ausweise, Sicherheiten, Mandate) und
// Wiedervorlagen pruefen; erzeugt In-App-Benachrichtigungen.
Schedule::command('app:scan-due-items')->dailyAt('05:30')->timezone($zeitzone);

// Taegliches Datenbank-Backup nach BACKUP_PATH.
Schedule::command('app:backup-run')->dailyAt('02:00')->timezone($zeitzone);

// Faellige Zahlungsplan-Positionen fortschreiben (Abschnitt 24). Vor dem Scan
// der Faelligkeiten, damit dieser den fortgeschriebenen Stand sieht.
Schedule::command('app:roll-forward-schedules')->dailyAt('04:30')->timezone($zeitzone);
