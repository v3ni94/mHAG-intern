<?php
/**
 * Schrittweise Prüfung des Anwendungsstarts.
 *
 * Zweck: Wenn der Aufruf der Anwendung mit einem Serverfehler endet, ohne dass
 * eine Meldung erscheint, macht diese Datei den Fehler sichtbar. Sie führt den
 * Start in einzelnen Schritten aus, fängt jeden Fehler ab und zeigt Meldung,
 * Datei und Zeile an. Zusätzlich prüft sie, ob das Verzeichnis vendor
 * vollständig übertragen wurde, was bei Übertragung tausender Dateien per SFTP
 * eine häufige Fehlerquelle ist.
 *
 * VERWENDUNG
 * 1. Datei nach public/ hochladen.
 * 2. Im Browser aufrufen: https://<domain>/pruefung.php
 * 3. Ergebnis prüfen, Datei anschließend löschen.
 *
 * Absichtlich in altem PHP-Stil geschrieben, damit sie auf jeder Version läuft.
 * Es werden keine Passwörter angezeigt.
 */

// Fehler sichtbar machen, ausschließlich innerhalb dieser Prüfung.
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

$base = dirname(__DIR__);
$schritte = array();
$abbruch = false;

/**
 * Fatale Fehler abfangen, die sich nicht mit try/catch behandeln lassen,
 * und lesbar ausgeben.
 */
register_shutdown_function(function () {
    $fehler = error_get_last();
    if ($fehler !== null && in_array($fehler['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        echo '<div style="margin:18px auto;max-width:900px;padding:14px 18px;background:#FBEAE9;'
            . 'border:1px solid #F0C4C1;border-radius:6px;color:#B3261E;font-family:Calibri,sans-serif;">'
            . '<strong>Abbruch durch einen schweren Fehler.</strong><br>'
            . '<span style="font-family:monospace;font-size:13px;">'
            . htmlspecialchars($fehler['message']) . '<br>'
            . htmlspecialchars($fehler['file']) . ', Zeile ' . (int) $fehler['line']
            . '</span></div></body></html>';
    }
});

/**
 * Prüft die Konfigurationsdatei auf Syntaxfehler, BEVOR die Anwendung geladen
 * wird. Nötig, weil ein ungültiger Wert dort zum sofortigen Abbruch führt, den
 * keine Fehlerbehandlung mehr abfangen kann: Die Anwendung antwortet dann mit
 * einem nackten Serverfehler 500 ohne jede Meldung.
 *
 * Beanstandet werden Werte mit Leerzeichen ohne Anführungszeichen sowie
 * verbliebene Platzhalter in spitzen Klammern.
 */
function envProbleme($path)
{
    $probleme = array();
    if (! is_readable($path)) {
        return $probleme;
    }
    $zeilen = file($path, FILE_IGNORE_NEW_LINES);
    $nummer = 0;
    foreach ($zeilen as $zeile) {
        $nummer++;
        $roh = trim($zeile);
        if ($roh === '' || strpos($roh, '#') === 0) {
            continue;
        }
        if (strpos($roh, '=') === false) {
            continue;
        }
        $teile = explode('=', $roh, 2);
        $schluessel = trim($teile[0]);
        $wert = trim($teile[1]);

        if (strpos($wert, '<') !== false || strpos($wert, '>') !== false) {
            $probleme[] = 'Zeile '.$nummer.' ('.$schluessel.'): Der Platzhalter in spitzen Klammern wurde nicht ersetzt.';

            continue;
        }

        $inAnfuehrungszeichen = (strlen($wert) >= 2)
            && ((substr($wert, 0, 1) === '"' && substr($wert, -1) === '"')
                || (substr($wert, 0, 1) === "'" && substr($wert, -1) === "'"));

        if (! $inAnfuehrungszeichen && $wert !== '' && preg_match('/\s/', $wert)) {
            $probleme[] = 'Zeile '.$nummer.' ('.$schluessel.'): Der Wert enthält Leerzeichen und muss in Anführungszeichen stehen, oder das Leerzeichen ist zu entfernen.';
        }
    }

    return $probleme;
}

function schritt(&$schritte, $nummer, $titel, $ok, $detail)
{
    $schritte[] = array('nummer' => $nummer, 'titel' => $titel, 'ok' => $ok, 'detail' => $detail);
}

// ---------------------------------------------------------------------------
// Schritt 1: Vollständigkeit wichtiger Dateien
// ---------------------------------------------------------------------------
$pflichtdateien = array(
    'vendor/autoload.php',
    'vendor/composer/autoload_real.php',
    'vendor/composer/autoload_classmap.php',
    'vendor/composer/installed.php',
    'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    'vendor/laravel/framework/src/Illuminate/Support/helpers.php',
    'vendor/symfony/http-foundation/Request.php',
    'vendor/nesbot/carbon/src/Carbon/Carbon.php',
    'vendor/spatie/laravel-permission/src/PermissionServiceProvider.php',
    'vendor/barryvdh/laravel-dompdf/src/ServiceProvider.php',
    'bootstrap/app.php',
    'bootstrap/providers.php',
    'config/app.php',
    'config/database.php',
    'app/Providers/AppServiceProvider.php',
    'app/helpers.php',
    'routes/web.php',
    'database/seeders/DatabaseSeeder.php',
);
$fehlende = array();
foreach ($pflichtdateien as $datei) {
    if (!file_exists($base . '/' . $datei)) {
        $fehlende[] = $datei;
    }
}
schritt($schritte, 1, 'Wichtige Dateien vorhanden',
    count($fehlende) === 0,
    count($fehlende) === 0
        ? count($pflichtdateien) . ' Stichproben geprüft, alle vorhanden'
        : 'FEHLT: ' . implode(', ', $fehlende));

// Verzeichnisse, die zur Laufzeit beschreibbar sein müssen
$pflichtverzeichnisse = array(
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
);
$verzeichnisprobleme = array();
foreach ($pflichtverzeichnisse as $dir) {
    $pfad = $base . '/' . $dir;
    if (!is_dir($pfad)) {
        $verzeichnisprobleme[] = $dir . ' (fehlt)';
    } elseif (!is_writable($pfad)) {
        $verzeichnisprobleme[] = $dir . ' (nicht beschreibbar)';
    }
}
schritt($schritte, 2, 'Laufzeitverzeichnisse',
    count($verzeichnisprobleme) === 0,
    count($verzeichnisprobleme) === 0
        ? 'alle vorhanden und beschreibbar'
        : implode(', ', $verzeichnisprobleme));

/*
 * Zwischengespeicherte Konfiguration ist im Produktivbetrieb erwünscht. Ein
 * Problem ist sie nur, wenn sie aus einer anderen Umgebung stammt, denn dann
 * enthält sie Pfade, die hier nicht existieren. Genau darauf wird geprüft.
 */
$cacheDateien = array('bootstrap/cache/config.php', 'bootstrap/cache/events.php');
$vorhandeneCaches = array();
$fremdeCaches = array();

// Verzeichnisnamen, die in einem echten Dateisystempfad der Anwendung vorkommen.
// Nur solche Treffer werden bewertet, damit URL-Pfade aus dem Routen-Cache
// nicht fälschlich als Dateipfad gelten.
$pfadmerkmale = array('/storage/', '/vendor/', '/bootstrap/', '/resources/', '/database/', '/public/');

foreach ($cacheDateien as $datei) {
    $pfad = $base . '/' . $datei;
    if (!file_exists($pfad)) {
        continue;
    }
    $vorhandeneCaches[] = $datei;
    $inhalt = (string) file_get_contents($pfad);
    if (preg_match_all("#'(/[A-Za-z0-9_./-]{10,})'#", $inhalt, $treffer)) {
        foreach ($treffer[1] as $gefundenerPfad) {
            $istDateipfad = false;
            foreach ($pfadmerkmale as $merkmal) {
                if (strpos($gefundenerPfad, $merkmal) !== false) {
                    $istDateipfad = true;
                    break;
                }
            }
            if ($istDateipfad && strpos($gefundenerPfad, $base) !== 0 && !file_exists($gefundenerPfad)) {
                $fremdeCaches[$datei] = $gefundenerPfad;
                break;
            }
        }
    }
}

// Der Routen-Zwischenspeicher enthält keine Dateipfade, seine Anwesenheit wird
// daher nur nachrichtlich erfasst.
if (file_exists($base . '/bootstrap/cache/routes-v7.php')) {
    $vorhandeneCaches[] = 'bootstrap/cache/routes-v7.php';
}

if (count($vorhandeneCaches) === 0) {
    schritt($schritte, 3, 'Zwischengespeicherte Konfiguration', true,
        'keine vorhanden, das ist bei der Ersteinrichtung richtig');
} elseif (count($fremdeCaches) === 0) {
    schritt($schritte, 3, 'Zwischengespeicherte Konfiguration', true,
        'vorhanden und passend zu diesem Verzeichnis: ' . implode(', ', $vorhandeneCaches));
} else {
    $meldungen = array();
    foreach ($fremdeCaches as $datei => $gefundenerPfad) {
        $meldungen[] = $datei . ' (verweist auf ' . $gefundenerPfad . ')';
    }
    schritt($schritte, 3, 'Zwischengespeicherte Konfiguration', false,
        'Diese Dateien stammen aus einer anderen Umgebung und müssen gelöscht werden: '
        . implode(', ', $meldungen));
}

// Konfigurationsdatei prüfen, bevor die Anwendung geladen wird
$envPfad = $base . '/.env';
$envFehler = file_exists($envPfad) ? envProbleme($envPfad) : array('Die Datei .env fehlt.');
schritt($schritte, 4, 'Konfigurationsdatei .env fehlerfrei',
    count($envFehler) === 0,
    count($envFehler) === 0
        ? 'keine Beanstandungen'
        : implode(' | ', $envFehler));
if (count($envFehler) > 0) {
    $abbruch = true;
}

// ---------------------------------------------------------------------------
// Weitere Schritte: Anwendung schrittweise starten
// ---------------------------------------------------------------------------
$app = null;

if (!$abbruch) {
    try {
        require $base . '/vendor/autoload.php';
        schritt($schritte, 5, 'Klassenlader geladen (vendor/autoload.php)', true, 'erfolgreich');
    } catch (Throwable $e) {
        schritt($schritte, 5, 'Klassenlader geladen (vendor/autoload.php)', false,
            get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ', Zeile ' . $e->getLine() . ')');
        $abbruch = true;
    }
}

if (!$abbruch) {
    try {
        $app = require_once $base . '/bootstrap/app.php';
        schritt($schritte, 6, 'Anwendung erzeugt (bootstrap/app.php)', true, 'erfolgreich');
    } catch (Throwable $e) {
        schritt($schritte, 6, 'Anwendung erzeugt (bootstrap/app.php)', false,
            get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ', Zeile ' . $e->getLine() . ')');
        $abbruch = true;
    }
}

if (!$abbruch) {
    try {
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        schritt($schritte, 7, 'Konfiguration und Dienste geladen', true,
            'Umgebung: ' . $app->environment() . ', Fehleranzeige: ' . (config('app.debug') ? 'ein' : 'aus'));
    } catch (Throwable $e) {
        schritt($schritte, 7, 'Konfiguration und Dienste geladen', false,
            get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ', Zeile ' . $e->getLine() . ')');
        $abbruch = true;
    }
}

if (!$abbruch) {
    try {
        $verbindung = Illuminate\Support\Facades\DB::connection();
        $verbindung->getPdo();
        $tabellen = $verbindung->select('SHOW TABLES');
        schritt($schritte, 8, 'Datenbankverbindung', true,
            'Verbindung zu "' . $verbindung->getDatabaseName() . '" steht, '
            . count($tabellen) . ' Tabellen vorhanden');
    } catch (Throwable $e) {
        schritt($schritte, 8, 'Datenbankverbindung', false,
            get_class($e) . ': ' . $e->getMessage());
    }
}

?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Prüfung des Anwendungsstarts</title>
</head>
<body style="font-family: Calibri, 'Segoe UI', Arial, sans-serif; color:#2E2D2E; background:#f7f5f1; margin:0; padding:28px 16px;">
<div style="max-width:900px;margin:0 auto;">
    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <div style="font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#9F9F9F;font-weight:600;">Müller Holding AG · Intranet</div>
        <h1 style="font-size:22px;margin:6px 0;">Prüfung des Anwendungsstarts</h1>
        <div style="width:52px;height:3px;background:#E3AC48;border-radius:2px;"></div>
        <p style="font-size:14px;color:#55534f;">
            Der Start wird in einzelnen Schritten ausgeführt. Der erste rot markierte Schritt nennt die Ursache.
        </p>
    </div>

    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <?php foreach ($schritte as $s) { ?>
            <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f0eee9;">
                <div style="flex:0 0 26px;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;background:<?php echo $s['ok'] ? '#E8F5EC' : '#FBEAE9'; ?>;border:1px solid <?php echo $s['ok'] ? '#BFE0C8' : '#F0C4C1'; ?>;">
                    <?php echo (int) $s['nummer']; ?>
                </div>
                <div style="flex:1;">
                    <strong><?php echo htmlspecialchars($s['titel']); ?></strong>
                    <span style="margin-left:8px;font-weight:600;color:<?php echo $s['ok'] ? '#1E7B34' : '#B3261E'; ?>;">
                        <?php echo $s['ok'] ? '&#10003; in Ordnung' : '&#10007; Fehler'; ?>
                    </span>
                    <div style="font-size:13px;color:#55534f;margin-top:4px;word-break:break-word;font-family:<?php echo $s['ok'] ? 'inherit' : 'monospace'; ?>;">
                        <?php echo htmlspecialchars($s['detail']); ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div style="background:#2E2D2E;color:#bbb;font-size:11px;padding:14px 24px;border-radius:8px;border-top:2px solid #E3AC48;line-height:1.7;">
        <strong style="color:#fff;">Müller Holding AG</strong> · Rheinpromenade 13 · 40789 Monheim am Rhein · kontakt@mueller-holding.ag · mueller-holding.ag<br>
        Diese Prüfdatei bitte nach der Einrichtung löschen.
    </div>
</div>
</body>
</html>
