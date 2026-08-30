<?php
/**
 * Diagnose für die Einrichtung des Intranets der Müller Holding AG.
 *
 * Diese Datei ist absichtlich in sehr altem PHP-Stil geschrieben (PHP 5.4
 * aufwärts) und lädt die Anwendung NICHT. Dadurch läuft sie auch dann, wenn die
 * eigentliche Anwendung wegen zu alter PHP-Version abbricht, und zeigt die
 * Ursache an.
 *
 * VERWENDUNG
 * 1. Datei nach public/ hochladen.
 * 2. Im Browser aufrufen: https://<domain>/diagnose.php
 * 3. Ergebnis prüfen, Datei anschließend löschen.
 *
 * Es werden keine Zugangsdaten und keine Passwörter angezeigt.
 */

header('Content-Type: text/html; charset=utf-8');

$base = dirname(__DIR__);
$required = '8.3.0';

function jaNein($ok)
{
    return $ok
        ? '<span style="color:#1E7B34;font-weight:600;">ja</span>'
        : '<span style="color:#B3261E;font-weight:600;">nein</span>';
}

function zeile($label, $wert)
{
    echo '<tr><td style="padding:6px 10px;color:#9F9F9F;width:42%;border-bottom:1px solid #f0eee9;">'
        . htmlspecialchars($label)
        . '</td><td style="padding:6px 10px;border-bottom:1px solid #f0eee9;">' . $wert . '</td></tr>';
}

$phpOk = version_compare(PHP_VERSION, $required, '>=');

$verzeichnisse = array('storage', 'storage/logs', 'storage/framework', 'bootstrap/cache');
$erweiterungen = array('bcmath', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'dom', 'fileinfo', 'zip', 'gd', 'intl');

?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Diagnose · Müller Holding AG Intranet</title>
</head>
<body style="font-family: Calibri, 'Segoe UI', Arial, sans-serif; color:#2E2D2E; background:#f7f5f1; margin:0; padding:28px 16px;">
<div style="max-width:800px;margin:0 auto;">

    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <div style="font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:#9F9F9F;font-weight:600;">Müller Holding AG · Intranet</div>
        <h1 style="font-size:22px;margin:6px 0;">Diagnose der Serverumgebung</h1>
        <div style="width:52px;height:3px;background:#E3AC48;border-radius:2px;"></div>

        <?php if (!$phpOk) { ?>
            <div style="margin-top:18px;padding:12px 16px;background:#FBEAE9;border:1px solid #F0C4C1;border-radius:6px;color:#B3261E;">
                <strong>Das ist die Ursache des Serverfehlers.</strong><br>
                Auf diesem Webspace läuft PHP <?php echo htmlspecialchars(PHP_VERSION); ?>.
                Die Anwendung benötigt mindestens PHP <?php echo htmlspecialchars($required); ?>.
                Stellen Sie die PHP-Version im Kundenbereich Ihres Hosting-Anbieters für diese Domain
                auf <?php echo htmlspecialchars($required); ?> oder neuer um und rufen diese Seite erneut auf.
            </div>
        <?php } else { ?>
            <div style="margin-top:18px;padding:12px 16px;background:#E8F5EC;border:1px solid #BFE0C8;border-radius:6px;color:#1E7B34;">
                Die PHP-Version ist ausreichend (<?php echo htmlspecialchars(PHP_VERSION); ?>).
                Prüfen Sie die weiteren Punkte unten.
            </div>
        <?php } ?>
    </div>

    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <h2 style="font-size:16px;margin:0 0 12px;">PHP</h2>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <?php
            zeile('PHP-Version', htmlspecialchars(PHP_VERSION) . ' (benötigt: ' . $required . ' oder neuer)');
            zeile('Ausreichend', jaNein($phpOk));
            zeile('Schnittstelle', htmlspecialchars(PHP_SAPI));
            zeile('Speichergrenze', htmlspecialchars(ini_get('memory_limit')));
            zeile('Maximale Laufzeit', htmlspecialchars(ini_get('max_execution_time')) . ' Sekunden');
            zeile('Maximale Uploadgröße', htmlspecialchars(ini_get('upload_max_filesize')));
            $log = ini_get('error_log');
            zeile('Fehlerprotokoll', $log ? htmlspecialchars($log) : 'nicht gesetzt (Protokoll im Kundenbereich des Anbieters)');
            ?>
        </table>
    </div>

    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <h2 style="font-size:16px;margin:0 0 12px;">PHP-Erweiterungen</h2>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <?php
            foreach ($erweiterungen as $ext) {
                $geladen = extension_loaded($ext);
                $optional = in_array($ext, array('zip', 'gd', 'intl'));
                $hinweis = $geladen
                    ? '<span style="color:#1E7B34;font-weight:600;">vorhanden</span>'
                    : ($optional
                        ? '<span style="color:#B77400;font-weight:600;">fehlt (optional)</span>'
                        : '<span style="color:#B3261E;font-weight:600;">fehlt, zwingend erforderlich</span>');
                zeile($ext, $hinweis);
            }
            ?>
        </table>
    </div>

    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <h2 style="font-size:16px;margin:0 0 12px;">Dateien und Verzeichnisse</h2>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <?php
            zeile('Verzeichnis dieser Datei', htmlspecialchars(__DIR__));
            zeile('Erwartetes Anwendungsverzeichnis', htmlspecialchars($base));
            zeile('vendor/autoload.php vorhanden', jaNein(file_exists($base . '/vendor/autoload.php')));
            zeile('.env vorhanden', jaNein(file_exists($base . '/.env')));
            if (file_exists($base . '/.env')) {
                zeile('.env beschreibbar', jaNein(is_writable($base . '/.env')));
                $inhalt = file_get_contents($base . '/.env');
                $keyGesetzt = (bool) preg_match('/^APP_KEY=base64:.+$/m', $inhalt);
                zeile('Anwendungsschlüssel gesetzt', jaNein($keyGesetzt));
                if (preg_match('/^APP_URL=(.*)$/m', $inhalt, $m)) {
                    zeile('APP_URL', htmlspecialchars(trim($m[1])));
                }
                if (preg_match('/^DB_DATABASE=(.*)$/m', $inhalt, $m)) {
                    zeile('DB_DATABASE', htmlspecialchars(trim($m[1])));
                }
                if (preg_match('/^DB_HOST=(.*)$/m', $inhalt, $m)) {
                    zeile('DB_HOST', htmlspecialchars(trim($m[1])));
                }
            }
            zeile('artisan vorhanden', jaNein(file_exists($base . '/artisan')));
            zeile('.htaccess in public', jaNein(file_exists(__DIR__ . '/.htaccess')));
            foreach ($verzeichnisse as $dir) {
                $pfad = $base . '/' . $dir;
                $ok = is_dir($pfad) && is_writable($pfad);
                zeile($dir . ' beschreibbar', jaNein($ok));
            }
            ?>
        </table>
    </div>

    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <h2 style="font-size:16px;margin:0 0 12px;">Plattformanforderung des Pakets</h2>
        <?php
        $checkDatei = $base . '/vendor/composer/platform_check.php';
        if (file_exists($checkDatei)) {
            $inhalt = file_get_contents($checkDatei);
            if (preg_match('/PHP_VERSION_ID >= (\d+)/', $inhalt, $m)) {
                $id = (int) $m[1];
                $major = intval($id / 10000);
                $minor = intval(($id % 10000) / 100);
                $patch = $id % 100;
                $verlangt = $major . '.' . $minor . '.' . $patch;
                $erfuellt = PHP_VERSION_ID >= $id;
                echo '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
                zeile('Verlangte PHP-Version des Pakets', htmlspecialchars($verlangt));
                zeile('Von diesem Server erfüllt', jaNein($erfuellt));
                echo '</table>';
                if (!$erfuellt) {
                    echo '<div style="margin-top:12px;padding:12px 16px;background:#FDF2E0;border:1px solid #EDD5A8;border-radius:6px;">'
                        . 'Das Paket verlangt eine höhere PHP-Version als hier läuft. Entweder die PHP-Version der Domain '
                        . 'erhöhen oder ein Paket anfordern, das für PHP ' . htmlspecialchars(PHP_VERSION) . ' gebaut ist.'
                        . '</div>';
                }
            } else {
                echo '<p style="font-size:14px;">Die Plattformanforderung konnte nicht gelesen werden.</p>';
            }
        } else {
            echo '<p style="font-size:14px;">Die Datei vendor/composer/platform_check.php wurde nicht gefunden. '
                . 'Wurde das Verzeichnis vendor vollständig hochgeladen?</p>';
        }
        ?>
    </div>

    <div style="background:#fff;border:1px solid #DDDBD6;border-radius:8px;padding:20px 24px;margin-bottom:18px;">
        <h2 style="font-size:16px;margin:0 0 12px;">Letzte Einträge des Anwendungsprotokolls</h2>
        <?php
        $logs = glob($base . '/storage/logs/laravel*.log');
        if ($logs && count($logs)) {
            rsort($logs);
            $datei = $logs[0];
            $inhalt = file_get_contents($datei);
            $zeilen = explode("\n", trim($inhalt));
            $auszug = array_slice($zeilen, -25);
            echo '<p style="font-size:13px;color:#55534f;">Datei: ' . htmlspecialchars(basename($datei)) . '</p>';
            echo '<pre style="background:#FBF6EC;border:1px solid #DDDBD6;padding:10px;border-radius:6px;font-size:12px;overflow-x:auto;white-space:pre-wrap;">'
                . htmlspecialchars(implode("\n", $auszug)) . '</pre>';
        } else {
            echo '<p style="font-size:14px;">Noch kein Anwendungsprotokoll vorhanden. Das ist vor der ersten '
                . 'erfolgreichen Ausführung normal.</p>';
        }
        ?>
    </div>

    <div style="background:#2E2D2E;color:#bbb;font-size:11px;padding:14px 24px;border-radius:8px;border-top:2px solid #E3AC48;line-height:1.7;">
        <strong style="color:#fff;">Müller Holding AG</strong> · Rheinpromenade 13 · 40789 Monheim am Rhein · kontakt@mueller-holding.ag · mueller-holding.ag<br>
        Diese Diagnosedatei bitte nach der Einrichtung löschen.
    </div>
</div>
</body>
</html>
