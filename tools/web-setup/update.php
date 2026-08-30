<?php

/**
 * ===========================================================================
 * Müller Holding AG Intranet: Web-Aktualisierung ohne Kommandozeile
 * ===========================================================================
 *
 * Zweck: Nach dem Hochladen geänderter Anwendungsdateien werden die
 * Zwischenspeicher geleert, neue Datenbankänderungen eingespielt, bei
 * Bedarf Rollen und Berechtigungen eingelesen und die Anwendung wieder für
 * den Produktivbetrieb optimiert.
 *
 * ABGRENZUNG ZUM SETUP
 * Dieses Skript legt KEINE Datenbank neu an, führt KEINE Startdaten ein und
 * kann die Datenbank nicht zurücksetzen. Es ist bewusst auf das beschränkt,
 * was eine Aktualisierung braucht. Bestehende Daten bleiben unberührt.
 *
 * VERWENDUNG
 * 1. Zugriffsschlüssel eintragen: unten SETUP_TOKEN_HASH mit dem Ergebnis von
 *    password_hash('<eigener-schluessel>', PASSWORD_DEFAULT) belegen.
 * 2. Datei in das Verzeichnis public/ der Anwendung hochladen.
 * 3. Aufruf: https://<domain>/update.php?token=<zugriffsschluessel>
 * 4. Schritte in der angezeigten Reihenfolge ausführen.
 * 5. Abschließend "Datei löschen" betätigen.
 *
 * SICHERHEIT
 * - Ohne gültigen Zugriffsschlüssel antwortet das Skript mit 404.
 * - Ohne hinterlegten Schlüssel ist es wirkungslos, eine versehentlich
 *   hochgeladene Datei aus dem Projektarchiv kann nichts auslösen.
 * - Nach Abschluss ist die Datei zu löschen. Das Skript bietet die Löschung an
 *   und weist bis dahin sichtbar darauf hin.
 */

// ---------------------------------------------------------------------------
// Zugriffsschlüssel
// ---------------------------------------------------------------------------
const SETUP_TOKEN_HASH = '';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if (SETUP_TOKEN_HASH === '' || $token === '' || ! password_verify($token, SETUP_TOKEN_HASH)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

$requiredPhp = '8.3.0';
if (version_compare(PHP_VERSION, $requiredPhp, '<')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>PHP-Version zu alt</title>';
    echo '<body style="font-family: Calibri, sans-serif; padding:40px; color:#2E2D2E; max-width:700px;">';
    echo '<h1 style="font-size:20px;">PHP-Version zu alt</h1>';
    echo '<p>Auf diesem Webspace läuft PHP '.htmlspecialchars(PHP_VERSION).'. Benötigt wird '
        .htmlspecialchars($requiredPhp).' oder neuer.</p></body></html>';
    exit;
}

$basePath = dirname(__DIR__);
$envPath = $basePath.'/.env';
$action = (string) ($_POST['action'] ?? '');
$messages = [];

/**
 * Prüft die Konfigurationsdatei auf Syntaxfehler, BEVOR die Anwendung geladen
 * wird: Ein ungültiger Wert führt sonst zum sofortigen Abbruch mit einem
 * nackten Serverfehler 500 ohne jede Meldung.
 */
function envProbleme($path)
{
    $probleme = [];
    if (! is_readable($path)) {
        return $probleme;
    }
    $nummer = 0;
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $zeile) {
        $nummer++;
        $roh = trim($zeile);
        if ($roh === '' || strpos($roh, '#') === 0 || strpos($roh, '=') === false) {
            continue;
        }
        [$schluessel, $wert] = array_map('trim', explode('=', $roh, 2));

        if (strpos($wert, '<') !== false || strpos($wert, '>') !== false) {
            $probleme[] = 'Zeile '.$nummer.' ('.$schluessel.'): Der Platzhalter in spitzen Klammern wurde nicht ersetzt.';

            continue;
        }
        $inAnfuehrungszeichen = strlen($wert) >= 2
            && ((substr($wert, 0, 1) === '"' && substr($wert, -1) === '"')
                || (substr($wert, 0, 1) === "'" && substr($wert, -1) === "'"));
        if (! $inAnfuehrungszeichen && $wert !== '' && preg_match('/\s/', $wert)) {
            $probleme[] = 'Zeile '.$nummer.' ('.$schluessel.'): Der Wert enthält Leerzeichen und muss in Anführungszeichen stehen.';
        }
    }

    return $probleme;
}

// ---------------------------------------------------------------------------
// Selbstlöschung
// ---------------------------------------------------------------------------
if ($action === 'delete') {
    $deleted = @unlink(__FILE__);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Aktualisierung beendet</title>';
    echo '<body style="font-family: Calibri, sans-serif; padding:40px; color:#2E2D2E;">';
    echo $deleted
        ? '<h1 style="font-size:20px;">Aktualisierung beendet</h1><p>Die Datei wurde gelöscht. Rufen Sie jetzt die Anwendung auf.</p>'
        : '<h1 style="font-size:20px;">Löschung fehlgeschlagen</h1><p><strong>Bitte löschen Sie die Datei '
            .htmlspecialchars(basename(__FILE__)).' im Verzeichnis public/ manuell über den Dateimanager.</strong></p>';
    echo '</body></html>';
    exit;
}

// ---------------------------------------------------------------------------
// Anwendung laden
// ---------------------------------------------------------------------------
$envFehler = file_exists($envPath) ? envProbleme($envPath) : ['Die Datei .env wurde nicht gefunden.'];

$app = null;
$laravelError = null;
if ($envFehler === [] && file_exists($basePath.'/vendor/autoload.php')) {
    try {
        $platformCheck = $basePath.'/vendor/composer/platform_check.php';
        if (file_exists($platformCheck)) {
            $contents = (string) file_get_contents($platformCheck);
            if (preg_match('/PHP_VERSION_ID >= (\d+)/', $contents, $m) && PHP_VERSION_ID < (int) $m[1]) {
                $id = (int) $m[1];
                throw new RuntimeException(sprintf(
                    'Das Paket wurde für PHP %d.%d.%d oder neuer gebaut, hier läuft PHP %s.',
                    intdiv($id, 10000),
                    intdiv($id % 10000, 100),
                    $id % 100,
                    PHP_VERSION,
                ));
            }
        }
        require $basePath.'/vendor/autoload.php';
        $app = require_once $basePath.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    } catch (Throwable $e) {
        $laravelError = $e->getMessage();
        $app = null;
    }
}

// ---------------------------------------------------------------------------
// Aktionen
// ---------------------------------------------------------------------------
if ($action === 'clear' && $app !== null) {
    try {
        // Reihenfolge bewusst: zuerst die zwischengespeicherte Konfiguration
        // entfernen, damit die folgenden Schritte die neuen Dateien sehen.
        $output = '';
        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'event:clear'] as $command) {
            Illuminate\Support\Facades\Artisan::call($command);
            $output .= Illuminate\Support\Facades\Artisan::output();
        }
        $messages[] = ['ok', 'Alle Zwischenspeicher wurden geleert. Die Anwendung liest jetzt die hochgeladenen Dateien.'];
        $messages[] = ['pre', trim($output) !== '' ? $output : 'Zwischenspeicher geleert.'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Das Leeren der Zwischenspeicher ist fehlgeschlagen: '.$e->getMessage()];
    }
}

if ($action === 'migrate' && $app !== null) {
    @set_time_limit(300);
    try {
        // Nur additive Strukturänderungen einspielen. Kein migrate:fresh,
        // kein db:seed: bestehende Daten bleiben unberührt.
        Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = Illuminate\Support\Facades\Artisan::output();
        $messages[] = ['ok', 'Die Datenbankänderungen wurden eingespielt. Bestehende Daten sind unverändert.'];
        $messages[] = ['pre', trim($output) !== '' ? $output : 'Keine offenen Änderungen.'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Das Einspielen der Datenbankänderungen ist fehlgeschlagen: '.$e->getMessage()];
    }
}

if ($action === 'roles' && $app !== null) {
    @set_time_limit(120);
    try {
        /*
         * Rollen und Berechtigungen einlesen. Der Seeder arbeitet mit
         * findOrCreate und syncPermissions: vorhandene Rollen bleiben
         * erhalten, neue kommen hinzu. Es werden KEINE Fachdaten angelegt.
         */
        Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolePermissionSeeder',
            '--force' => true,
        ]);
        $output = Illuminate\Support\Facades\Artisan::output();
        $messages[] = ['ok', 'Rollen und Berechtigungen wurden eingelesen. Bestehende Zuordnungen bleiben erhalten.'];
        $messages[] = ['pre', trim($output) !== '' ? $output : 'Rollen aktualisiert.'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Das Einlesen der Rollen ist fehlgeschlagen: '.$e->getMessage()];
    }
}

if ($action === 'optimize' && $app !== null) {
    try {
        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            Illuminate\Support\Facades\Artisan::call($command);
        }
        $messages[] = ['ok', 'Die Anwendung wurde wieder für den Produktivbetrieb optimiert.'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Die Optimierung ist fehlgeschlagen: '.$e->getMessage()];
    }
}

// ---------------------------------------------------------------------------
// Statusprüfungen
// ---------------------------------------------------------------------------
$checks = [];
$checks[] = ['PHP-Version', PHP_VERSION, version_compare(PHP_VERSION, $requiredPhp, '>=')];
$checks[] = ['Konfigurationsdatei .env', $envFehler === [] ? 'in Ordnung' : 'fehlerhaft', $envFehler === []];
$checks[] = ['Anwendung ladbar', $app !== null ? 'ja' : 'nein', $app !== null];

$offeneMigrationen = null;
$migrationStatus = '';
if ($app !== null) {
    try {
        Illuminate\Support\Facades\Artisan::call('migrate:status');
        $migrationStatus = Illuminate\Support\Facades\Artisan::output();
        $offeneMigrationen = preg_match_all('/\bPending\b/i', $migrationStatus);
        $checks[] = [
            'Offene Datenbankänderungen',
            $offeneMigrationen > 0 ? $offeneMigrationen.' offen' : 'keine',
            true,
        ];
    } catch (Throwable $e) {
        $checks[] = ['Datenbankverbindung', 'nicht möglich: '.$e->getMessage(), false];
    }
}

$configCache = file_exists($basePath.'/bootstrap/cache/config.php');
$routeCache = file_exists($basePath.'/bootstrap/cache/routes-v7.php')
    || count(glob($basePath.'/bootstrap/cache/routes*.php') ?: []) > 0;
$checks[] = ['Konfiguration zwischengespeichert', $configCache ? 'ja' : 'nein', true];
$checks[] = ['Routen zwischengespeichert', $routeCache ? 'ja' : 'nein', true];

// ---------------------------------------------------------------------------
// Ausgabe
// ---------------------------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
$tokenEscaped = htmlspecialchars($token, ENT_QUOTES);
?>
<!doctype html>
<html lang="de">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Intranet aktualisieren</title>
<style>
    body { font-family: Calibri, Carlito, sans-serif; color: #2E2D2E; background: #FBF6EC; margin: 0; padding: 32px; }
    .blatt { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #DDDBD6; padding: 32px; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .balken { width: 52px; height: 3px; background: #E3AC48; margin: 0 0 24px; }
    h2 { font-size: 16px; margin: 28px 0 10px; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #DDDBD6; vertical-align: top; }
    th { width: 300px; font-weight: 600; }
    .ok { color: #2f6f3e; } .fail { color: #9a2b2b; }
    .hinweis { border-left: 3px solid #E3AC48; background: #FBF6EC; padding: 12px 14px; font-size: 14px; margin: 14px 0; }
    .fehler { border-left: 3px solid #9a2b2b; background: #fdf3f3; padding: 12px 14px; font-size: 14px; margin: 14px 0; }
    .erfolg { border-left: 3px solid #2f6f3e; background: #f3f8f4; padding: 12px 14px; font-size: 14px; margin: 14px 0; }
    pre { background: #2E2D2E; color: #FBF6EC; padding: 12px; overflow: auto; font-size: 12px; max-height: 320px; }
    button { font: inherit; background: #E3AC48; color: #2E2D2E; border: 0; padding: 9px 16px; cursor: pointer; }
    button.leise { background: #9F9F9F; color: #fff; }
    form { display: inline; }
    ol { font-size: 14px; line-height: 1.7; }
    footer { margin-top: 28px; padding-top: 14px; border-top: 1px solid #DDDBD6; font-size: 12px; color: #9F9F9F; }
</style>
<div class="blatt">
    <h1>Intranet aktualisieren</h1>
    <div class="balken"></div>

    <?php foreach ($messages as [$art, $text]) { ?>
        <?php if ($art === 'pre') { ?>
            <pre><?= htmlspecialchars($text) ?></pre>
        <?php } else { ?>
            <div class="<?= $art === 'ok' ? 'erfolg' : ($art === 'error' ? 'fehler' : 'hinweis') ?>"><?= htmlspecialchars($text) ?></div>
        <?php } ?>
    <?php } ?>

    <?php if ($envFehler !== []) { ?>
        <div class="fehler">
            <strong>Die Konfigurationsdatei .env ist fehlerhaft.</strong> Die Anwendung kann so nicht starten.
            <ul><?php foreach ($envFehler as $f) { ?><li><?= htmlspecialchars($f) ?></li><?php } ?></ul>
        </div>
    <?php } ?>

    <?php if ($laravelError !== null) { ?>
        <div class="fehler"><strong>Die Anwendung konnte nicht geladen werden:</strong> <?= htmlspecialchars($laravelError) ?></div>
    <?php } ?>

    <h2>Status</h2>
    <table>
        <?php foreach ($checks as [$name, $wert, $gut]) { ?>
            <tr>
                <th><?= htmlspecialchars($name) ?></th>
                <td class="<?= $gut ? 'ok' : 'fail' ?>"><?= htmlspecialchars((string) $wert) ?></td>
            </tr>
        <?php } ?>
    </table>

    <h2>Schritte in dieser Reihenfolge</h2>
    <ol>
        <li>
            <strong>Zwischenspeicher leeren.</strong> Notwendig nach jedem Datei-Upload, sonst läuft die
            alte Fassung weiter.
            <form method="post">
                <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
                <input type="hidden" name="action" value="clear">
                <button type="submit" <?= $app === null ? 'disabled' : '' ?>>Zwischenspeicher leeren</button>
            </form>
        </li>
        <li>
            <strong>Datenbankänderungen einspielen.</strong> Nur zusätzliche Felder und Tabellen,
            bestehende Daten bleiben unverändert.
            <form method="post" onsubmit="return confirm('Datenbankänderungen jetzt einspielen?');">
                <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
                <input type="hidden" name="action" value="migrate">
                <button type="submit" <?= $app === null ? 'disabled' : '' ?>>Änderungen einspielen</button>
            </form>
        </li>
        <li>
            <strong>Rollen und Berechtigungen einlesen.</strong> Nur nötig, wenn eine Aktualisierung neue
            Rollen oder Berechtigungen mitbringt. Bestehende Zuordnungen bleiben erhalten, es werden keine
            Fachdaten angelegt.
            <form method="post">
                <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
                <input type="hidden" name="action" value="roles">
                <button type="submit" <?= $app === null ? 'disabled' : '' ?>>Rollen einlesen</button>
            </form>
        </li>
        <li>
            <strong>Für den Produktivbetrieb optimieren.</strong>
            <form method="post">
                <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
                <input type="hidden" name="action" value="optimize">
                <button type="submit" <?= $app === null ? 'disabled' : '' ?>>Optimieren</button>
            </form>
        </li>
        <li>
            <strong>Diese Datei löschen.</strong> Sie darf nicht dauerhaft erreichbar bleiben.
            <form method="post" onsubmit="return confirm('Datei jetzt löschen?');">
                <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="leise">Datei löschen</button>
            </form>
        </li>
    </ol>

    <?php if ($migrationStatus !== '') { ?>
        <h2>Übersicht der Datenbankänderungen</h2>
        <pre><?= htmlspecialchars($migrationStatus) ?></pre>
    <?php } ?>

    <div class="hinweis">
        Dieses Skript setzt die Datenbank nicht zurück und legt keine Startdaten an. Für eine
        Erstinstallation ist das Setup-Skript zu verwenden.
    </div>

    <footer>
        Müller Holding AG, Aktiengesellschaft mit Sitz in Braunschweig.<br>
        Internes Werkzeug zur Aktualisierung. Nach Abschluss ist die Datei zu löschen.
    </footer>
</div>
</html>
