<?php

/*
 * Wartet, bis die Datenbank Verbindungen annimmt.
 *
 * Wird vom Startskript der Container aufgerufen, bevor die Anwendung startet.
 * Nach einem Neustart des Servers startet Docker die Container nach ihrer
 * Neustartregel, nicht nach der Reihenfolge aus depends_on. Ohne dieses Warten
 * beantwortet die Anwendung die ersten Anfragen mit einem Fehler, bis die
 * Datenbank bereit ist.
 *
 * Bewusst ohne Laravel: es laeuft vor dem Aufbau des Zwischenspeichers der
 * Konfiguration. Das Kennwort wird aus der Umgebung gelesen und nie ausgegeben.
 */

$treiber = getenv('DB_CONNECTION') ?: 'mariadb';

if (! in_array($treiber, ['mysql', 'mariadb'], true)) {
    exit(0);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_DATABASE') ?: '';
$benutzer = getenv('DB_USERNAME') ?: '';
$kennwort = (string) getenv('DB_PASSWORD');

$versuche = (int) (getenv('DB_WAIT_TRIES') ?: 60);
$pause = 2;
$letzterFehler = '';

for ($i = 1; $i <= $versuche; $i++) {
    try {
        new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $name),
            $benutzer,
            $kennwort,
            [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        if ($i > 1) {
            fwrite(STDERR, sprintf("intranet: Datenbank nach %d Sekunden erreichbar.\n", ($i - 1) * $pause));
        }

        exit(0);
    } catch (Throwable $e) {
        // Die Meldung von PDO enthaelt Host und Benutzer, nie das Kennwort.
        $letzterFehler = $e->getMessage();
        sleep($pause);
    }
}

fwrite(STDERR, sprintf(
    "intranet: Datenbank %s:%s war nach %d Sekunden nicht erreichbar. Letzte Meldung: %s\n",
    $host,
    $port,
    $versuche * $pause,
    $letzterFehler,
));

exit(1);
