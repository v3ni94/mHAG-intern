<?php

/**
 * ===========================================================================
 * Müller Holding AG Intranet: Web-Setup für Installationen ohne Kommandozeile
 * ===========================================================================
 *
 * Zweck: Einrichtung auf Hosting-Umgebungen ohne SSH-Zugang. Das Skript
 * erzeugt den Anwendungsschlüssel, prüft die Systemvoraussetzungen, testet die
 * Datenbankverbindung, legt Struktur und Startdaten an und entfernt sich
 * anschließend selbst.
 *
 * VERWENDUNG
 * 1. Diese Datei in das Verzeichnis public/ der Anwendung hochladen.
 * 2. Im Browser mit dem Zugriffsschlüssel aufrufen:
 *    https://<domain>/setup.php?token=<zugriffsschluessel>
 * 3. Schritte in der angezeigten Reihenfolge ausführen.
 * 4. Abschließend "Setup beenden und Datei löschen" betätigen.
 *
 * SICHERHEIT
 * - Der Aufruf ist nur mit dem Zugriffsschlüssel möglich (Hash unten).
 * - Ohne Schlüssel antwortet das Skript mit 404, es gibt also keinen Hinweis
 *   auf seine Existenz.
 * - Der Anwendungsschlüssel wird auf dem Server erzeugt und dort gespeichert,
 *   er wird niemals angezeigt oder übertragen.
 * - Nach Abschluss muss die Datei gelöscht werden. Das Skript bietet die
 *   Löschung selbst an und weist bis dahin sichtbar darauf hin.
 * - Ein bereits gesetzter Anwendungsschlüssel wird nicht überschrieben.
 */

// ---------------------------------------------------------------------------
// Zugriffsschlüssel: Hash des Schlüssels, der beim Aufruf übergeben wird.
// Für einen eigenen Schlüssel: password_hash('<neuer-schluessel>', PASSWORD_DEFAULT)
// ---------------------------------------------------------------------------
const SETUP_TOKEN_HASH = '';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

// Ohne hinterlegten Zugriffsschlüssel ist das Skript wirkungslos. So kann eine
// versehentlich hochgeladene Datei aus dem Projektarchiv nichts auslösen.
if (SETUP_TOKEN_HASH === '' || $token === '' || ! password_verify($token, SETUP_TOKEN_HASH)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

/*
 * Versionsprüfung VOR dem Laden der Anwendung. Ohne diese Prüfung würde eine
 * zu alte PHP-Version zu einem nackten Serverfehler 500 führen, dessen Ursache
 * ohne Zugriff auf die Serverprotokolle nicht erkennbar ist.
 */
$requiredPhp = '8.3.0';
if (version_compare(PHP_VERSION, $requiredPhp, '<')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>PHP-Version zu alt</title>';
    echo '<body style="font-family: Calibri, sans-serif; padding:40px; color:#2E2D2E; max-width:700px;">';
    echo '<h1 style="font-size:20px;">PHP-Version zu alt</h1>';
    echo '<div style="width:52px;height:3px;background:#E3AC48;margin-bottom:18px;"></div>';
    echo '<p>Auf diesem Webspace läuft <strong>PHP '.htmlspecialchars(PHP_VERSION).'</strong>. '
        .'Die Anwendung benötigt mindestens <strong>PHP '.htmlspecialchars($requiredPhp).'</strong>.</p>';
    echo '<p>Bitte stellen Sie die PHP-Version im Kundenbereich Ihres Hosting-Anbieters für diese Domain '
        .'auf '.htmlspecialchars($requiredPhp).' oder neuer um und rufen Sie diese Seite anschließend erneut auf. '
        .'Bei IONOS findet sich die Einstellung unter Hosting, PHP-Einstellungen, jeweils für das Verzeichnis '
        .'oder die Domain.</p>';
    echo '<p style="color:#55534f;font-size:14px;">Solange eine zu alte Version aktiv ist, endet jeder Aufruf '
        .'der Anwendung mit einem Serverfehler, ohne dass eine Meldung erscheint.</p>';
    echo '</body></html>';
    exit;
}

$basePath = dirname(__DIR__);
$envPath = $basePath.'/.env';
$action = (string) ($_POST['action'] ?? '');
$messages = [];

/** Statuszeile für die Anzeige. */
function status(string $label, bool $ok, string $detail = '', bool $warningOnly = false): array
{
    return ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'warning' => $warningOnly];
}

/** Wert in der .env setzen oder ergänzen. */
function envSet(string $path, string $key, string $value): bool
{
    if (! is_writable($path)) {
        return false;
    }
    $content = (string) file_get_contents($path);
    $line = $key.'='.$value;
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
    $content = preg_match($pattern, $content)
        ? preg_replace($pattern, $line, $content)
        : rtrim($content, "\n")."\n".$line."\n";

    return file_put_contents($path, $content) !== false;
}

/** Einzelnen Wert aus der .env lesen. */
function envGet(string $path, string $key): ?string
{
    if (! is_readable($path)) {
        return null;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/', trim($line), $m)) {
            return trim($m[1], "\"' ");
        }
    }

    return null;
}

// ---------------------------------------------------------------------------
// Aktionen
// ---------------------------------------------------------------------------

if ($action === 'delete') {
    $deleted = @unlink(__FILE__);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Setup beendet</title>';
    echo '<body style="font-family: Calibri, sans-serif; padding: 40px; color: #2E2D2E;">';
    echo $deleted
        ? '<h1 style="font-size:20px;">Setup beendet</h1><p>Die Setup-Datei wurde gelöscht. Rufen Sie jetzt die Anwendung auf und melden sich an.</p>'
        : '<h1 style="font-size:20px;">Löschung fehlgeschlagen</h1><p><strong>Bitte löschen Sie die Datei '
            .htmlspecialchars(basename(__FILE__)).' im Verzeichnis public/ manuell über den Dateimanager.</strong></p>';
    echo '</body></html>';
    exit;
}

if (! file_exists($envPath) && $action === 'create_env') {
    if (file_exists($basePath.'/.env.example')) {
        copy($basePath.'/.env.example', $envPath);
        $messages[] = ['ok', 'Die Datei .env wurde aus .env.example erstellt. Bitte jetzt die Datenbankzugangsdaten eintragen.'];
    } else {
        $messages[] = ['error', 'Die Vorlage .env.example wurde nicht gefunden. Bitte die Anwendungsdateien vollständig hochladen.'];
    }
}

if ($action === 'generate_key') {
    $current = envGet($envPath, 'APP_KEY');
    if ($current !== null && $current !== '' && strncmp($current, 'base64:', 7) === 0) {
        $messages[] = ['warn', 'Es ist bereits ein Anwendungsschlüssel gesetzt. Er wurde nicht verändert, damit verschlüsselte Daten lesbar bleiben.'];
    } else {
        $key = 'base64:'.base64_encode(random_bytes(32));
        $messages[] = envSet($envPath, 'APP_KEY', $key)
            ? ['ok', 'Der Anwendungsschlüssel wurde erzeugt und in der .env gespeichert. Er wird aus Sicherheitsgründen nicht angezeigt.']
            : ['error', 'Die Datei .env ist nicht beschreibbar. Bitte Schreibrechte setzen (Datei .env, Rechte 640, Eigentümer Webserver-Benutzer).'];
    }
}

// Laravel für Datenbankprüfung und Migrationen laden
$app = null;
$laravelError = null;
if (file_exists($basePath.'/vendor/autoload.php') && file_exists($envPath)) {
    try {
        // Plattformprüfung von Composer vorab auswerten: sie beendet den
        // Vorgang sonst mit einem nicht abfangbaren Fehler.
        $platformCheck = $basePath.'/vendor/composer/platform_check.php';
        if (file_exists($platformCheck)) {
            $contents = (string) file_get_contents($platformCheck);
            if (preg_match('/PHP_VERSION_ID >= (\\d+)/', $contents, $m) && PHP_VERSION_ID < (int) $m[1]) {
                $id = (int) $m[1];
                throw new RuntimeException(sprintf(
                    'Das Paket wurde für PHP %d.%d.%d oder neuer gebaut, hier läuft PHP %s. '
                        .'Bitte die PHP-Version der Domain erhöhen.',
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

if ($action === 'migrate' && $app !== null) {
    @set_time_limit(300);
    try {
        Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = Illuminate\Support\Facades\Artisan::output();
        Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $output .= Illuminate\Support\Facades\Artisan::output();
        $messages[] = ['ok', 'Datenbankstruktur und Startdaten wurden angelegt.'];
        $messages[] = ['pre', $output];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Die Einrichtung der Datenbank ist fehlgeschlagen: '.$e->getMessage()];
    }
}

if ($action === 'optimize' && $app !== null) {
    try {
        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            Illuminate\Support\Facades\Artisan::call($command);
        }
        $messages[] = ['ok', 'Die Anwendung wurde für den Produktivbetrieb optimiert (Konfiguration, Routen und Ansichten zwischengespeichert).'];
    } catch (Throwable $e) {
        $messages[] = ['error', 'Die Optimierung ist fehlgeschlagen: '.$e->getMessage()];
    }
}

// ---------------------------------------------------------------------------
// Prüfungen
// ---------------------------------------------------------------------------

$checks = [];
$phpOk = version_compare(PHP_VERSION, '8.2', '>=');
$checks[] = status('PHP-Version', $phpOk, PHP_VERSION.($phpOk ? '' : ' (benötigt wird mindestens 8.2, empfohlen 8.4)'));

foreach (['bcmath', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'dom', 'zip', 'gd', 'intl', 'fileinfo'] as $ext) {
    $loaded = extension_loaded($ext);
    $optional = in_array($ext, ['gd', 'intl', 'zip'], true);
    $checks[] = status(
        'PHP-Erweiterung '.$ext,
        $loaded || $optional,
        $loaded ? 'vorhanden' : ($optional ? 'fehlt (optional, wird für einzelne Funktionen benötigt)' : 'fehlt, zwingend erforderlich'),
        ! $loaded && $optional,
    );
}

$envExists = file_exists($envPath);
$checks[] = status('Konfigurationsdatei .env', $envExists, $envExists ? 'vorhanden' : 'fehlt');
if ($envExists) {
    $checks[] = status('.env beschreibbar', is_writable($envPath), is_writable($envPath) ? 'ja' : 'nein, Schreibrechte erforderlich');
}

foreach (['storage', 'storage/logs', 'storage/framework', 'bootstrap/cache'] as $dir) {
    $path = $basePath.'/'.$dir;
    $writable = is_dir($path) && is_writable($path);
    $checks[] = status('Verzeichnis '.$dir.' beschreibbar', $writable, $writable ? 'ja' : 'nein, Rechte 775 erforderlich');
}

$appKey = $envExists ? (string) envGet($envPath, 'APP_KEY') : '';
$keySet = $appKey !== '' && strncmp($appKey, 'base64:', 7) === 0;
$checks[] = status('Anwendungsschlüssel gesetzt', $keySet, $keySet ? 'ja' : 'nein, im Schritt unten erzeugen');

$dbOk = false;
$dbDetail = 'nicht geprüft';
$tableCount = 0;
if ($app !== null) {
    try {
        $connection = Illuminate\Support\Facades\DB::connection();
        $connection->getPdo();
        $dbOk = true;
        $database = $connection->getDatabaseName();
        $tableCount = count($connection->select('SHOW TABLES'));
        $dbDetail = 'Verbindung zu "'.$database.'" erfolgreich, '.$tableCount.' Tabellen vorhanden';
    } catch (Throwable $e) {
        $dbDetail = 'Verbindung fehlgeschlagen: '.$e->getMessage();
    }
} elseif ($laravelError !== null) {
    $dbDetail = 'Anwendung konnte nicht geladen werden: '.$laravelError;
}
$checks[] = status('Datenbankverbindung', $dbOk, $dbDetail);

$migrated = false;
if ($dbOk) {
    try {
        $migrated = Illuminate\Support\Facades\Schema::hasTable('users')
            && Illuminate\Support\Facades\DB::table('migrations')->count() > 0;
    } catch (Throwable) {
        $migrated = false;
    }
    $checks[] = status('Datenbank eingerichtet', $migrated, $migrated ? 'Struktur und Startdaten vorhanden' : 'noch nicht eingerichtet');
}

$appUrl = $envExists ? (string) envGet($envPath, 'APP_URL') : '';
$sessionDomain = $envExists ? (string) envGet($envPath, 'SESSION_DOMAIN') : '';
$httpsHint = $appUrl !== '' && strncmp($appUrl, 'https://', 8) !== 0;
$domainHint = $sessionDomain !== '' && $sessionDomain !== 'null'
    && $appUrl !== '' && strpos($appUrl, $sessionDomain) === false;

$allRequiredOk = true;
foreach ($checks as $check) {
    if (! $check['ok'] && ! $check['warning']) {
        $allRequiredOk = false;
    }
}

?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einrichtung · Müller Holding AG Intranet</title>
    <style>
        body { margin: 0; background: #f7f5f1; font-family: Carlito, Calibri, 'Segoe UI', sans-serif; color: #2E2D2E; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 28px 16px 60px; }
        .card { background: #fff; border: 1px solid #DDDBD6; border-radius: 8px; padding: 20px 24px; margin-bottom: 18px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        h2 { font-size: 16px; margin: 0 0 12px; }
        .gold { width: 52px; height: 3px; background: #E3AC48; border-radius: 2px; margin-bottom: 18px; }
        .label { font-size: 11px; letter-spacing: .09em; text-transform: uppercase; color: #9F9F9F; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { padding: 6px 8px; border-bottom: 1px solid #f0eee9; vertical-align: top; }
        .ok { color: #1E7B34; font-weight: 600; white-space: nowrap; }
        .bad { color: #B3261E; font-weight: 600; white-space: nowrap; }
        .warn { color: #B77400; font-weight: 600; white-space: nowrap; }
        .msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 12px; font-size: 14px; }
        .msg.ok { background: #E8F5EC; border: 1px solid #BFE0C8; color: #1E7B34; font-weight: normal; }
        .msg.error { background: #FBEAE9; border: 1px solid #F0C4C1; color: #B3261E; font-weight: normal; }
        .msg.warn { background: #FDF2E0; border: 1px solid #EDD5A8; color: #B77400; font-weight: normal; }
        pre { background: #FBF6EC; border: 1px solid #DDDBD6; padding: 10px; border-radius: 6px; font-size: 12px; overflow-x: auto; }
        button { background: #2E2D2E; color: #fff; border: 0; border-radius: 4px; padding: 9px 18px; font-size: 14px; cursor: pointer; font-family: inherit; }
        button.gold-btn { background: #E3AC48; color: #2E2D2E; font-weight: 600; }
        button.danger { background: #B3261E; }
        button:disabled { background: #cfcdc9; cursor: not-allowed; }
        .step { display: flex; gap: 14px; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f0eee9; }
        .step:last-child { border-bottom: 0; }
        .step .num { background: #FBF6EC; border: 1px solid #E3AC48; color: #2E2D2E; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex: 0 0 26px; }
        .step .body { flex: 1; }
        .step p { margin: 4px 0 8px; font-size: 14px; }
        code { background: #FBF6EC; padding: 1px 5px; border-radius: 3px; font-size: 13px; }
        footer { background: #2E2D2E; color: #bbb; font-size: 11px; padding: 14px 24px; border-radius: 8px; line-height: 1.7; border-top: 2px solid #E3AC48; }
        footer strong { color: #fff; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="label">Müller Holding AG · Intranet</div>
        <h1>Einrichtung</h1>
        <div class="gold"></div>
        <div class="msg warn">
            <strong>Wichtig:</strong> Diese Setup-Datei muss nach Abschluss der Einrichtung gelöscht werden.
            Die Schaltfläche dafür befindet sich am Ende der Seite.
        </div>

        <?php foreach ($messages as [$type, $text]): ?>
            <?php if ($type === 'pre'): ?>
                <pre><?= htmlspecialchars($text) ?></pre>
            <?php else: ?>
                <div class="msg <?= $type === 'ok' ? 'ok' : ($type === 'warn' ? 'warn' : 'error') ?>"><?= htmlspecialchars($text) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Systemprüfung</h2>
        <table>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td style="width: 38%;"><?= htmlspecialchars($check['label']) ?></td>
                    <td style="width: 12%;" class="<?= $check['ok'] ? 'ok' : ($check['warning'] ? 'warn' : 'bad') ?>">
                        <?= $check['ok'] ? '&#10003; in Ordnung' : ($check['warning'] ? '&#9888; Hinweis' : '&#10007; Fehler') ?>
                    </td>
                    <td style="color:#55534f;"><?= htmlspecialchars($check['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($httpsHint): ?>
            <div class="msg warn" style="margin-top: 12px;">
                <strong>APP_URL</strong> beginnt nicht mit <code>https://</code>. Die Anmeldung setzt HTTPS voraus,
                weil das Sitzungs-Cookie als "secure" gesetzt ist.
            </div>
        <?php endif; ?>
        <?php if ($domainHint): ?>
            <div class="msg warn">
                <strong>SESSION_DOMAIN</strong> (<?= htmlspecialchars($sessionDomain) ?>) passt nicht zu
                <strong>APP_URL</strong> (<?= htmlspecialchars($appUrl) ?>). In dieser Konstellation verwirft der Browser
                das Sitzungs-Cookie und die Anmeldung springt ohne Fehlermeldung zurück auf die Anmeldeseite.
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Einrichtungsschritte</h2>

        <?php if (! $envExists): ?>
            <div class="step">
                <div class="num">1</div>
                <div class="body">
                    <strong>Konfigurationsdatei erstellen</strong>
                    <p>Es wird eine <code>.env</code> aus der Vorlage erstellt. Anschließend tragen Sie dort über den
                        Dateimanager Ihres Hosting-Panels die Datenbankzugangsdaten ein.</p>
                    <form method="post">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="action" value="create_env">
                        <button type="submit">.env erstellen</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="step">
            <div class="num"><?= $envExists ? '1' : '2' ?></div>
            <div class="body">
                <strong>Datenbankzugangsdaten eintragen</strong>
                <p>In der Datei <code>.env</code> müssen gesetzt sein: <code>DB_DATABASE</code>,
                    <code>DB_USERNAME</code>, <code>DB_PASSWORD</code> sowie <code>DB_HOST</code> (meist
                    <code>127.0.0.1</code> oder <code>localhost</code>). Ebenso <code>APP_URL</code> und
                    <code>SESSION_DOMAIN</code> passend zur Domain. Diese Datei wird über den Dateimanager bearbeitet.</p>
                <p><em>Aktueller Stand:</em> <?= $dbOk ? '<span class="ok">Verbindung steht</span>' : '<span class="bad">keine Verbindung</span>' ?></p>
            </div>
        </div>

        <div class="step">
            <div class="num"><?= $envExists ? '2' : '3' ?></div>
            <div class="body">
                <strong>Anwendungsschlüssel erzeugen</strong>
                <p>Der Schlüssel verschlüsselt Sitzungen und die Daten der Zwei-Faktor-Authentifizierung. Er wird
                    auf dem Server erzeugt, dort gespeichert und niemals angezeigt.
                    <?php if ($keySet): ?><br><span class="ok">Bereits gesetzt.</span> Ein vorhandener Schlüssel wird nicht überschrieben.<?php endif; ?>
                </p>
                <form method="post">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="generate_key">
                    <button type="submit" <?= $keySet ? 'disabled' : '' ?>>Schlüssel erzeugen</button>
                </form>
            </div>
        </div>

        <div class="step">
            <div class="num"><?= $envExists ? '3' : '4' ?></div>
            <div class="body">
                <strong>Datenbank einrichten</strong>
                <p>Legt die 66 Tabellen an und schreibt die Startdaten (Gesellschaft, Vorstand, Aufsichtsrat,
                    Aktienstruktur, Rollen und Berechtigungen, Darlehensarten, FAQ). Das Startpasswort des
                    Administrators wird der Angabe <code>SEED_ADMIN_PASSWORD</code> aus der <code>.env</code> entnommen.</p>
                <?php if ($migrated): ?>
                    <p><span class="ok">Die Datenbank ist bereits eingerichtet</span> (<?= (int) $tableCount ?> Tabellen).
                        Ein erneuter Aufruf ergänzt nur fehlende Strukturen und überschreibt keine Daten.</p>
                <?php endif; ?>
                <form method="post" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Wird eingerichtet ...';">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="migrate">
                    <button type="submit" class="gold-btn" <?= $dbOk && $keySet ? '' : 'disabled' ?>>
                        <?= $migrated ? 'Struktur prüfen und ergänzen' : 'Datenbank jetzt einrichten' ?>
                    </button>
                </form>
                <?php if (! ($dbOk && $keySet)): ?>
                    <p style="color:#B77400;">Erst möglich, wenn Datenbankverbindung und Anwendungsschlüssel in Ordnung sind.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="step">
            <div class="num"><?= $envExists ? '4' : '5' ?></div>
            <div class="body">
                <strong>Für den Produktivbetrieb optimieren</strong>
                <p>Konfiguration, Routen und Ansichten werden zwischengespeichert. Nach späteren Änderungen an der
                    <code>.env</code> ist dieser Schritt erneut auszuführen.</p>
                <form method="post">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="optimize">
                    <button type="submit" <?= $migrated ? '' : 'disabled' ?>>Optimierung ausführen</button>
                </form>
            </div>
        </div>

        <div class="step">
            <div class="num"><?= $envExists ? '5' : '6' ?></div>
            <div class="body">
                <strong>Setup beenden</strong>
                <p>Löscht diese Datei vom Server. Danach melden Sie sich mit
                    <code>timo@muellerhv.de</code> und dem in <code>SEED_ADMIN_PASSWORD</code> hinterlegten Passwort an,
                    ändern das Passwort und richten die Zwei-Faktor-Authentifizierung ein.</p>
                <form method="post" onsubmit="return confirm('Setup-Datei jetzt löschen? Die Einrichtung sollte abgeschlossen sein.');">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="danger">Setup beenden und Datei löschen</button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <strong>Müller Holding AG</strong> · Rheinpromenade 13 · 40789 Monheim am Rhein · kontakt@mueller-holding.ag · mueller-holding.ag<br>
        Sitz: Monheim am Rhein · Registergericht: Amtsgericht Düsseldorf · HRB 104291 · Vorstand: Timo Müller · Aufsichtsratsvorsitzender: Jan Walprecht
    </footer>
</div>
</body>
</html>
