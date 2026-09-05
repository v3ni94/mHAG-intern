<?php

/**
 * ===========================================================================
 * Einstiegspunkt für den täglichen Lauf
 * ===========================================================================
 *
 * Zweck: Auf Webspace ohne Kommandozeile gibt es keinen Weg,
 * "php artisan schedule:run" von Hand einzurichten. Diese Datei übernimmt das.
 * Der Cronjob im Hosting-Panel ruft sie einmal täglich auf, sie stößt den
 * hinterlegten Zeitplan an (routes/console.php).
 *
 * Damit laufen:
 *   02:00  Datenbanksicherung
 *   04:30  Fortschreibung fälliger Zahlungsplan-Positionen
 *   05:30  Prüfung von Fälligkeiten, Abläufen und Wiedervorlagen
 *
 * Ohne diesen Aufruf lief keine dieser Aufgaben, auch die Sicherung nicht.
 *
 * WICHTIG: Diese Datei liegt bewusst im Wurzelverzeichnis der Anwendung und
 * NICHT in public/. Sie ist damit über das Internet nicht erreichbar.
 * Zusätzlich verweigert sie den Dienst, wenn sie nicht über die
 * Kommandozeile aufgerufen wird.
 *
 * EINRICHTUNG IM IONOS-PANEL
 *   Cron-Jobs, neuen Auftrag anlegen
 *   Skript:    /homepages/.../Intranet/cron.php
 *   PHP-Fassung: 8.4
 *   Zeitplan:  täglich, etwa 01:30 Uhr
 *
 * Der Zeitpunkt muss vor der frühesten geplanten Aufgabe liegen, damit der
 * Zeitplan an diesem Tag noch alles abarbeitet. Ein häufigerer Aufruf, etwa
 * stündlich, ist unschädlich: der Zeitplan führt jede Aufgabe nur zu ihrer
 * eigenen Uhrzeit aus.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit(1);
}

$basis = __DIR__;

if (! is_readable($basis.'/vendor/autoload.php')) {
    fwrite(STDERR, "Abbruch: vendor/autoload.php nicht gefunden. Liegt cron.php im Wurzelverzeichnis der Anwendung?\n");
    exit(1);
}

require $basis.'/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require_once $basis.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$eingabe = new Symfony\Component\Console\Input\ArrayInput(['command' => 'schedule:run']);
$ausgabe = new Symfony\Component\Console\Output\ConsoleOutput;

$status = $kernel->handle($eingabe, $ausgabe);

// Nachweis, dass der Lauf stattgefunden hat. Ohne diesen Eintrag liesse sich
// nicht unterscheiden, ob nichts zu tun war oder der Cronjob gar nicht lief.
try {
    Illuminate\Support\Facades\Log::info('Täglicher Lauf ausgeführt (cron.php).', [
        'rueckgabe' => $status,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Protokolleintrag fehlgeschlagen: '.$e->getMessage()."\n");
}

$kernel->terminate($eingabe, $status);

exit($status);
