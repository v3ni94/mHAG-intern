<?php

/**
 * ===========================================================================
 * Müller Holding AG Intranet: Notfalldiagnose und Wiederherstellung
 * ===========================================================================
 *
 * ZWECK
 * Wenn die Anwendung mit einem nackten Serverfehler 500 antwortet, ist die
 * Ursache in aller Regel nicht im Anwendungsprotokoll zu finden: der Abbruch
 * erfolgt, bevor Laravel eine Fehlerbehandlung besitzt. Diese Seite arbeitet
 * deshalb bewusst OHNE das Framework. Sie prüft zuerst die Konfigurationsdatei
 * .env zeilenweise, benennt den Zustand der Zwischenspeicher und versucht das
 * Framework erst danach zu starten, um die Ausnahme im Klartext anzuzeigen.
 *
 * WIEDERHERSTELLUNG
 * Die Zwischenspeicher werden auf Dateiebene entfernt, nicht über artisan.
 * Das ist der entscheidende Punkt: die Bereinigung funktioniert auch dann,
 * wenn das Framework selbst nicht mehr startet.
 *
 * VERWENDUNG
 * 1. Zugriffsschlüssel eintragen: unten NOTFALL_TOKEN_HASH mit dem Ergebnis
 *    von password_hash('<eigener-schluessel>', PASSWORD_DEFAULT) belegen.
 * 2. Datei in das Verzeichnis public/ hochladen.
 * 3. Aufruf: https://<domain>/notfall.php?token=<zugriffsschluessel>
 * 4. Nach der Behebung "Datei löschen" betätigen.
 *
 * SICHERHEIT
 * - Ohne gültigen Zugriffsschlüssel antwortet die Seite mit 404.
 * - Ohne hinterlegten Schlüssel ist die Datei wirkungslos.
 * - Werte aus der .env werden NIE angezeigt, nur Zeilennummern und Namen der
 *   Einstellungen. Kennwörter, Zugangsdaten und Schlüssel bleiben verdeckt.
 * - Die Datei ist nach der Behebung zu löschen.
 */

// ---------------------------------------------------------------------------
// Zugriffsschlüssel
// ---------------------------------------------------------------------------
const NOTFALL_TOKEN_HASH = '';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if (NOTFALL_TOKEN_HASH === '' || $token === '' || ! password_verify($token, NOTFALL_TOKEN_HASH)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

$basePath = dirname(__DIR__);
$envPath = $basePath.'/.env';

// ---------------------------------------------------------------------------
// Schutzschirm
// ---------------------------------------------------------------------------
/*
 * Laravel beendet den Prozess bei einer ungültigen .env mit exit(1) und
 * schreibt die Meldung nach stderr, nicht in die Antwort. Ein try/catch greift
 * dort NICHT. Genau das ist die Ursache des nackten Serverfehlers 500 ohne
 * Protokolleintrag. Diese Seite darf daran nicht selbst scheitern: die ganze
 * Ausgabe wird gepuffert, und eine Abschlussroutine liefert notfalls einen
 * Kurzbericht aus.
 */
$GLOBALS['notfallFertig'] = false;
$GLOBALS['notfallBericht'] = [];

register_shutdown_function(function () {
    if ($GLOBALS['notfallFertig'] === true) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');

    $letzter = error_get_last();

    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Notfalldiagnose</title>';
    echo '<body style="font-family: Calibri, sans-serif; padding:32px; color:#2E2D2E; max-width:820px;">';
    echo '<h1 style="font-size:21px;">Der Start der Anwendung hat den Vorgang abgebrochen</h1>';
    echo '<p style="font-size:14px;">Das Framework hat den Prozess beendet, statt eine Ausnahme '
        .'auszulösen. Genau so entsteht der Serverfehler 500 ohne Eintrag im Anwendungsprotokoll. '
        .'Die vor dem Abbruch erhobenen Befunde:</p>';
    echo '<ul style="font-size:14px;">';
    foreach ($GLOBALS['notfallBericht'] as $zeile) {
        echo '<li>'.htmlspecialchars((string) $zeile, ENT_QUOTES, 'UTF-8').'</li>';
    }
    echo '</ul>';
    if ($letzter !== null) {
        echo '<p style="font-size:14px;">Letzter PHP-Fehler:</p>';
        echo '<pre style="background:#FBF6EC;border:1px solid #DDDBD6;padding:10px;border-radius:6px;'
            .'font-size:12px;white-space:pre-wrap;">'
            .htmlspecialchars($letzter['message'].' in '.$letzter['file'].':'.$letzter['line'], ENT_QUOTES, 'UTF-8')
            .'</pre>';
    }
    echo '<p style="font-size:14px;">Bitte diese Seite neu laden. Sie versucht den Start dann '
        .'nicht mehr, sofern die Ursache in der .env liegt.</p>';
    echo '</body></html>';
});

ob_start();
$action = (string) ($_POST['action'] ?? '');
$meldungen = [];

// ---------------------------------------------------------------------------
// Selbstlöschung
// ---------------------------------------------------------------------------
if ($action === 'delete') {
    $geloescht = @unlink(__FILE__);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Notfalldiagnose beendet</title>';
    echo '<body style="font-family: Calibri, sans-serif; padding:40px; color:#2E2D2E; max-width:700px;">';
    echo $geloescht
        ? '<h1 style="font-size:20px;">Notfalldiagnose beendet</h1><p>Die Datei wurde gelöscht.</p>'
        : '<h1 style="font-size:20px;">Löschung fehlgeschlagen</h1><p><strong>Bitte die Datei '
            .htmlspecialchars(basename(__FILE__)).' im Verzeichnis public/ manuell über den '
            .'Dateimanager entfernen.</strong></p>';
    echo '</body></html>';
    exit;
}

// ---------------------------------------------------------------------------
// Anwendungsschlüssel erzeugen
// ---------------------------------------------------------------------------
/*
 * Nur zulaessig, wenn KEIN Schluessel hinterlegt ist. Ein vorhandener
 * Schluessel wird nie ueberschrieben: mit ihm sind verschluesselte Felder
 * gespeichert, ein Austausch macht sie unlesbar. Dieselbe Regel gilt im
 * Einrichtungswerkzeug.
 */
if ($action === 'app-key') {
    $inhalt = is_readable($envPath) ? (string) file_get_contents($envPath) : null;

    if ($inhalt === null) {
        $meldungen[] = ['fehler', 'Die Datei .env ist nicht lesbar. Es wurde nichts geändert.'];
    } elseif (preg_match('/^[ \t]*APP_KEY[ \t]*=[ \t]*(\S+)/m', $inhalt) === 1) {
        $meldungen[] = ['warnung', 'Es ist bereits ein Anwendungsschlüssel hinterlegt. '
            .'Er wird nicht überschrieben, weil damit verschlüsselte Felder gespeichert sind. '
            .'Liegt der Fehler an der Länge des Schlüssels, ist der Eintrag von Hand zu '
            .'berichtigen.'];
    } elseif (! is_writable($envPath)) {
        $meldungen[] = ['fehler', 'Die Datei .env ist nicht beschreibbar. Bitte die Rechte im '
            .'Dateimanager prüfen.'];
    } else {
        $neuerSchluessel = 'base64:'.base64_encode(random_bytes(32));

        // Sicherung vor der Änderung, damit der vorherige Stand nachvollziehbar bleibt.
        @copy($envPath, $envPath.'.vor-schluessel-'.date('Ymd-His'));

        if (preg_match('/^[ \t]*APP_KEY[ \t]*=.*$/m', $inhalt) === 1) {
            $geaendert = preg_replace('/^[ \t]*APP_KEY[ \t]*=.*$/m', 'APP_KEY='.$neuerSchluessel, $inhalt, 1);
        } else {
            $geaendert = rtrim($inhalt, "\r\n")."\nAPP_KEY=".$neuerSchluessel."\n";
        }

        if (@file_put_contents($envPath, $geaendert) === false) {
            $meldungen[] = ['fehler', 'Das Schreiben in die .env ist fehlgeschlagen. Es wurde '
                .'nichts geändert.'];
        } else {
            $meldungen[] = ['ok', 'Ein neuer Anwendungsschlüssel wurde in der .env hinterlegt. '
                .'Der Wert wird nicht angezeigt. Eine Sicherung der vorherigen Datei liegt im '
                .'Wurzelverzeichnis. Bitte diese Seite neu laden und danach die Anwendung '
                .'aufrufen.'];
            $meldungen[] = ['warnung', 'Folge: Mit dem alten Schlüssel verschlüsselte Felder sind '
                .'nicht mehr lesbar. Betroffen sind die Geheimnisse der Zwei-Faktor-Anmeldung. '
                .'Für Benutzer mit aktiver Zwei-Faktor-Anmeldung ist diese in der '
                .'Benutzerverwaltung zurückzusetzen. Fachdaten, Beträge und Dokumente sind '
                .'nicht betroffen.'];
        }
    }
}

// ---------------------------------------------------------------------------
// Prüfung der Konfigurationsdatei, ohne das Framework zu laden
// ---------------------------------------------------------------------------
$inspektor = $basePath.'/app/Support/EnvFileInspector.php';
$envBefunde = [];
$inspektorFehlt = ! is_readable($inspektor);

if (! $inspektorFehlt) {
    require_once $inspektor;
    $envBefunde = App\Support\EnvFileInspector::inspectFile($envPath);
}

$envFehler = array_values(array_filter($envBefunde, fn ($b) => $b['severity'] === 'fehler'));
$envWarnungen = array_values(array_filter($envBefunde, fn ($b) => $b['severity'] === 'warnung'));

// ---------------------------------------------------------------------------
// Zwischenspeicher: Zustand und Bereinigung auf Dateiebene
// ---------------------------------------------------------------------------
/** @return array<int, string> */
function zwischenspeicherDateien($basePath)
{
    $treffer = [];
    foreach ([
        '/bootstrap/cache/config.php',
        '/bootstrap/cache/events.php',
        '/bootstrap/cache/packages.php',
        '/bootstrap/cache/services.php',
    ] as $relativ) {
        if (file_exists($basePath.$relativ)) {
            $treffer[] = $relativ;
        }
    }
    foreach (glob($basePath.'/bootstrap/cache/routes*.php') ?: [] as $pfad) {
        $treffer[] = str_replace($basePath, '', $pfad);
    }

    return $treffer;
}

if ($action === 'clear-files') {
    $entfernt = 0;
    $fehlgeschlagen = [];

    // packages.php und services.php werden bewusst mitgelöscht: sie werden
    // beim nächsten Aufruf automatisch neu erzeugt.
    foreach (zwischenspeicherDateien($basePath) as $relativ) {
        if (@unlink($basePath.$relativ)) {
            $entfernt++;
        } else {
            $fehlgeschlagen[] = $relativ;
        }
    }

    $sichten = 0;
    foreach (glob($basePath.'/storage/framework/views/*.php') ?: [] as $pfad) {
        if (@unlink($pfad)) {
            $sichten++;
        }
    }

    $meldungen[] = [
        $fehlgeschlagen === [] ? 'ok' : 'warnung',
        'Zwischenspeicher bereinigt: '.$entfernt.' Datei(en) unter bootstrap/cache und '
        .$sichten.' vorkompilierte Oberflächen entfernt.'
        .($fehlgeschlagen === [] ? '' : ' Nicht entfernbar: '.implode(', ', $fehlgeschlagen)
            .'. Bitte über den Dateimanager löschen.'),
    ];
}

// ---------------------------------------------------------------------------
// Framework laden, Ausnahme im Klartext festhalten
// ---------------------------------------------------------------------------
$app = null;
$startFehler = null;
$startUebersprungen = false;

$GLOBALS['notfallBericht'][] = 'Konfigurationsdatei .env: '
    .($envFehler === [] ? 'keine startverhindernden Befunde' : count($envFehler).' Fehler')
    .', '.count($envWarnungen).' Hinweis(e).';
foreach ($envFehler as $b) {
    $GLOBALS['notfallBericht'][] = 'Zeile '.$b['line'].': '.$b['message'];
}

if ($envFehler !== []) {
    /*
     * Kein Startversuch: er würde den Prozess beenden und diese Seite
     * mitnehmen. Die Ursache steht bereits fest.
     */
    $startUebersprungen = true;
} elseif (is_readable($basePath.'/vendor/autoload.php')) {
    try {
        require $basePath.'/vendor/autoload.php';
        $app = require_once $basePath.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    } catch (Throwable $e) {
        $startFehler = $e;
        $app = null;
    }
} else {
    $startFehler = new RuntimeException(
        'Die Datei vendor/autoload.php fehlt. Das Verzeichnis vendor wurde nicht '
        .'oder nicht vollständig hochgeladen.',
    );
}

// ---------------------------------------------------------------------------
// Pflichtangaben und gezielte Laufzeitproben
// ---------------------------------------------------------------------------
/*
 * Eine syntaktisch fehlerfreie .env kann trotzdem jede Seite lahmlegen. Der
 * haeufigste Fall: der Anwendungsschluessel APP_KEY fehlt. Die Anwendung
 * startet dann, aber jede Anfrage bricht ab, weil der Verschluesseler nicht
 * erzeugt werden kann. Deshalb wird hier gezielt geprobt, was eine Anfrage
 * braucht.
 */
$pflichtBefunde = [];
if (! $inspektorFehlt) {
    $pflichtBefunde = App\Support\EnvFileInspector::pflichtangabenFile($envPath);
}

$proben = [];
if ($app !== null) {
    $probe = function (string $name, string $erklaerung, callable $fn) use (&$proben) {
        try {
            $fn();
            $proben[] = [$name, 'ok', 'in Ordnung', $erklaerung];
        } catch (Throwable $e) {
            $proben[] = [$name, 'fehler', get_class($e).': '.$e->getMessage(), $erklaerung];
        }
    };

    $probe('Verschlüsseler', 'Wird für Sitzungen und verschlüsselte Felder benötigt. '
        .'Scheitert er, endet jede Seite mit einem Serverfehler 500.',
        fn () => app('encrypter'));

    $probe('Sitzungsspeicher', 'Ohne Sitzung ist keine Anmeldung möglich.',
        fn () => app('session.store'));

    $probe('Datenbankverbindung', 'Zugangsdaten und Erreichbarkeit der Datenbank.',
        fn () => Illuminate\Support\Facades\DB::connection()->getPdo());

    $probe('Schreibrecht storage', 'Protokolle, Sitzungen und vorkompilierte Oberflächen.',
        function () use ($basePath) {
            $datei = $basePath.'/storage/framework/schreibprobe.tmp';
            if (@file_put_contents($datei, 'probe') === false) {
                throw new RuntimeException('In storage/framework kann nicht geschrieben werden.');
            }
            @unlink($datei);
        });
}

// ---------------------------------------------------------------------------
// Zwei-Faktor-Geheimnisse, die nicht gelesen werden können
// ---------------------------------------------------------------------------
/*
 * Nach einem Wechsel des Anwendungsschluessels sind die mit dem alten
 * Schluessel verschluesselten Felder nicht mehr lesbar. Betroffen sind die
 * Geheimnisse der Zwei-Faktor-Anmeldung. Es gibt zwei Wege:
 *
 * 1. Verlustfrei: Ist der ALTE Schluessel noch bekannt, in die .env
 *    aufnehmen als APP_PREVIOUS_KEYS=base64:<alter Schluessel>. Laravel liest
 *    damit weiter und legt beim naechsten Schreiben mit dem neuen Schluessel
 *    ab. Nichts ist zurueckzusetzen.
 * 2. Zuruecksetzen: Ist der alte Schluessel verloren, bleibt nur das
 *    Zuruecksetzen. Die betroffenen Benutzer richten die Zwei-Faktor-Anmeldung
 *    danach neu ein.
 */
$zweiFaktorBetroffen = null;
$zweiFaktorFehler = null;

/**
 * Kennungen der Konten mit hinterlegtem, aber nicht entschlüsselbarem
 * Geheimnis.
 *
 * Bewusst über den Abfragebaukasten und Crypt, NICHT über das Modell: dieses
 * Werkzeug wird einzeln hochgeladen und muss auch mit einer älteren Fassung
 * der Anwendung arbeiten, die die entsprechenden Methoden noch nicht kennt.
 *
 * @return array<int, int>
 */
function nichtLesbareGeheimnisse()
{
    $treffer = [];

    $zeilen = Illuminate\Support\Facades\DB::table('users')
        ->select('id', 'two_factor_secret')
        ->whereNotNull('two_factor_secret')
        ->get();

    foreach ($zeilen as $zeile) {
        $wert = (string) $zeile->two_factor_secret;
        if (trim($wert) === '') {
            continue;
        }
        try {
            Illuminate\Support\Facades\Crypt::decryptString($wert);
        } catch (Throwable $e) {
            $treffer[] = (int) $zeile->id;
        }
    }

    return $treffer;
}

if ($app !== null) {
    try {
        $zweiFaktorBetroffen = count(nichtLesbareGeheimnisse());
    } catch (Throwable $e) {
        $zweiFaktorBetroffen = null;
        $zweiFaktorFehler = $e->getMessage();
    }
}

if ($action === 'reset-2fa' && $app !== null) {
    try {
        $kennungen = nichtLesbareGeheimnisse();

        foreach ($kennungen as $id) {
            // Direkt über den Abfragebaukasten: ein Speichern über das Modell
            // würde den bisherigen Wert zum Vergleich entschlüsseln und dabei
            // selbst abbrechen.
            Illuminate\Support\Facades\DB::table('users')->where('id', $id)->update([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);

            try {
                $benutzer = App\Models\User::withTrashed()->find($id);
                if ($benutzer !== null) {
                    App\Services\AuditService::log(
                        'admin.users.two_factor_reset_unreadable',
                        $benutzer,
                        ['two_factor_enabled' => true],
                        ['two_factor_enabled' => false],
                        [
                            'quelle' => 'Notfalldiagnose',
                            'grund' => 'Geheimnis nach Wechsel des Anwendungsschlüssels nicht lesbar',
                        ],
                    );
                }
            } catch (Throwable $e) {
                // Der Prüfpfad darf die Wiederherstellung nicht verhindern.
                // Die Anzahl wird auf der Seite in jedem Fall benannt.
            }
        }

        $meldungen[] = ['ok', 'Für '.count($kennungen).' Konto/Konten wurde die '
            .'Zwei-Faktor-Anmeldung zurückgesetzt. Die betroffenen Benutzer richten sie bei der '
            .'nächsten Anmeldung neu ein. Eine automatische Benachrichtigung erfolgt hier nicht, '
            .'sie ist gesondert zu veranlassen.'];
        $zweiFaktorBetroffen = 0;
    } catch (Throwable $e) {
        $meldungen[] = ['fehler', 'Das Zurücksetzen ist fehlgeschlagen: '.$e->getMessage()];
    }
}

// ---------------------------------------------------------------------------
// Konfiguration wieder zwischenspeichern
// ---------------------------------------------------------------------------
if ($action === 'cache-config' && $app !== null) {
    try {
        Illuminate\Support\Facades\Artisan::call('config:cache');
        $meldungen[] = ['ok', 'Die Konfiguration wurde wieder zwischengespeichert. '
            .'Bitte die Anwendung jetzt in einem neuen Tab aufrufen.'];
    } catch (Throwable $e) {
        $meldungen[] = ['fehler', 'Das Zwischenspeichern ist fehlgeschlagen: '.$e->getMessage()];
    }
}

// ---------------------------------------------------------------------------
// Altlasten aus der Erstinstallation entfernen
// ---------------------------------------------------------------------------
$altlasten = [];
foreach (['diagnose.php', 'pruefung.php'] as $datei) {
    if (file_exists(__DIR__.'/'.$datei)) {
        $altlasten[] = $datei;
    }
}

if ($action === 'delete-altlasten') {
    $ok = [];
    $nein = [];
    foreach ($altlasten as $datei) {
        if (@unlink(__DIR__.'/'.$datei)) {
            $ok[] = $datei;
        } else {
            $nein[] = $datei;
        }
    }
    $meldungen[] = [
        $nein === [] ? 'ok' : 'warnung',
        ($ok === [] ? 'Es wurde nichts entfernt.' : 'Entfernt: '.implode(', ', $ok).'.')
        .($nein === [] ? '' : ' Nicht entfernbar: '.implode(', ', $nein).'.'),
    ];
    $altlasten = array_values(array_diff($altlasten, $ok));
}

// ---------------------------------------------------------------------------
// Anwendungsprotokoll
// ---------------------------------------------------------------------------
$protokoll = null;
$protokollDatei = null;
$protokollKopf = null;
$logs = glob($basePath.'/storage/logs/laravel*.log') ?: [];
if ($logs !== []) {
    rsort($logs);
    $protokollDatei = basename($logs[0]);
    $inhalt = (string) file_get_contents($logs[0]);
    $zeilen = explode("\n", rtrim($inhalt));

    /*
     * Der Anfang des letzten Eintrags ist die entscheidende Zeile: dort steht
     * die Ausnahme mit ihrer Meldung. Ein Auszug vom Ende der Datei zeigt nur
     * das Ende des Aufrufstapels und damit nicht die Ursache. Gesucht wird
     * deshalb die letzte Zeile, die mit einem Zeitstempel beginnt.
     */
    $beginn = null;
    for ($i = count($zeilen) - 1; $i >= 0; $i--) {
        if (preg_match('/^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $zeilen[$i]) === 1) {
            $beginn = $i;
            break;
        }
    }

    if ($beginn !== null) {
        $protokollKopf = $zeilen[$beginn];
        $protokoll = implode("\n", array_slice($zeilen, $beginn, 60));
    } else {
        $protokoll = implode("\n", array_slice($zeilen, -60));
    }
}

$cacheVorhanden = zwischenspeicherDateien($basePath);
$tokenEscaped = htmlspecialchars($token, ENT_QUOTES);

function h($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Notfalldiagnose</title>
<style>
    body { font-family: Calibri, Carlito, sans-serif; background:#FBF6EC; color:#2E2D2E; margin:0; padding:32px 20px; }
    .huelle { max-width: 900px; margin: 0 auto; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    h2 { font-size: 16px; margin: 0 0 12px; }
    .karte { background:#fff; border:1px solid #DDDBD6; border-radius:8px; padding:20px 24px; margin-bottom:18px; }
    .m { border-radius:6px; padding:10px 14px; margin-bottom:10px; font-size:14px; }
    .m.ok { background:#E8F3E8; border:1px solid #A8CDA8; }
    .m.warnung { background:#FDF3DC; border:1px solid #E3AC48; }
    .m.fehler { background:#FBE9E7; border:1px solid #D08579; }
    pre { background:#FBF6EC; border:1px solid #DDDBD6; padding:10px; border-radius:6px;
          font-size:12px; overflow-x:auto; white-space:pre-wrap; margin:0; }
    table { border-collapse: collapse; width:100%; font-size:14px; }
    th, td { text-align:left; padding:6px 8px; border-bottom:1px solid #EEE; vertical-align:top; }
    button { font-family:inherit; font-size:14px; padding:8px 16px; border-radius:6px;
             border:1px solid #2E2D2E; background:#2E2D2E; color:#fff; cursor:pointer; }
    button.zweit { background:#fff; color:#2E2D2E; }
    form { display:inline-block; margin:0 8px 8px 0; }
    .fuss { background:#2E2D2E; color:#bbb; font-size:11px; padding:14px 24px; border-radius:8px;
            border-top:2px solid #E3AC48; line-height:1.7; }
    .fuss strong { color:#fff; }
    .klein { font-size:13px; color:#55534f; }
</style>
</head>
<body>
<div class="huelle">
    <h1>Notfalldiagnose</h1>
    <p class="klein">Müller Holding AG Intranet. Diese Seite lädt das Framework erst nach der
       Prüfung der Konfigurationsdatei und zeigt Werte aus der .env nicht an.</p>

    <?php foreach ($meldungen as [$art, $text]) { ?>
        <div class="m <?= h($art) ?>"><?= h($text) ?></div>
    <?php } ?>

    <div class="karte">
        <h2>1. Konfigurationsdatei .env</h2>
        <?php if ($inspektorFehlt) { ?>
            <div class="m warnung">Die Prüfroutine app/Support/EnvFileInspector.php wurde nicht
                gefunden. Bitte die Datei mit hochladen, dann ist die zeilenweise Prüfung möglich.</div>
        <?php } elseif ($envBefunde === []) { ?>
            <div class="m ok">Keine Auffälligkeiten. Die Datei kann gelesen werden.</div>
        <?php } else { ?>
            <?php if ($envFehler !== []) { ?>
                <div class="m fehler"><strong>Das ist die Ursache eines Serverfehlers 500 auf
                    jeder Seite.</strong> Die .env wird gelesen, bevor die Anwendung eine
                    Fehlerbehandlung besitzt. Solange bootstrap/cache/config.php vorhanden war,
                    blieb der Fehler verdeckt; nach dem Leeren des Zwischenspeichers wirkt er
                    sofort.</div>
            <?php } ?>
            <table>
                <thead><tr><th style="width:70px;">Zeile</th><th style="width:180px;">Einstellung</th><th>Befund</th></tr></thead>
                <tbody>
                <?php foreach ($envBefunde as $b) { ?>
                    <tr>
                        <td><?= $b['line'] > 0 ? (int) $b['line'] : '' ?></td>
                        <td><?= h($b['key']) ?></td>
                        <td><?= $b['severity'] === 'fehler' ? '<strong>Fehler:</strong> ' : 'Hinweis: ' ?><?= h($b['message']) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>

    <div class="karte">
        <h2>2. Start der Anwendung</h2>
        <?php if ($startUebersprungen) { ?>
            <div class="m fehler">Der Startversuch wurde bewusst nicht unternommen. Bei den unter
                Punkt 1 genannten Fehlern beendet das Framework den Prozess mit exit statt mit
                einer Ausnahme und würde diese Seite mitnehmen. Zuerst die .env berichtigen.</div>
        <?php } elseif ($startFehler === null) { ?>
            <div class="m ok">Die Anwendung startet. Der Serverfehler liegt dann nicht im Start,
                sondern in einer einzelnen Seite; der Auszug aus dem Anwendungsprotokoll unter
                Punkt 4 nennt sie.</div>
        <?php } else { ?>
            <div class="m fehler">Die Anwendung startet nicht.</div>
            <p class="klein">Ausnahme: <strong><?= h(get_class($startFehler)) ?></strong></p>
            <pre><?= h($startFehler->getMessage()) ?>

Datei: <?= h($startFehler->getFile()) ?>, Zeile <?= (int) $startFehler->getLine() ?>

<?= h(implode("\n", array_slice(explode("\n", $startFehler->getTraceAsString()), 0, 12))) ?></pre>
        <?php } ?>
    </div>

    <div class="karte">
        <h2>2b. Pflichtangaben und gezielte Proben</h2>
        <?php if ($pflichtBefunde === [] && $proben === []) { ?>
            <p class="klein">Keine Angaben prüfbar, solange die Anwendung nicht startet.</p>
        <?php } ?>
        <?php if ($pflichtBefunde !== []) { ?>
            <table>
                <thead><tr><th style="width:180px;">Einstellung</th><th>Befund</th></tr></thead>
                <tbody>
                <?php foreach ($pflichtBefunde as $b) { ?>
                    <tr>
                        <td><?= h($b['key']) ?></td>
                        <td><?= $b['severity'] === 'fehler' ? '<strong>Fehler:</strong> ' : 'Hinweis: ' ?><?= h($b['message']) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } elseif (! $inspektorFehlt) { ?>
            <div class="m ok">Die Pflichtangaben sind vorhanden, der Anwendungsschlüssel hat die
                richtige Länge.</div>
        <?php } ?>

        <?php
        $schluesselFehlt = false;
        foreach ($pflichtBefunde as $b) {
            if ($b['key'] === 'APP_KEY' && $b['severity'] === 'fehler'
                && str_contains($b['message'], 'fehlt')) {
                $schluesselFehlt = true;
            }
        }
        ?>
        <?php if ($schluesselFehlt) { ?>
            <div class="m fehler" style="margin-top:12px;">
                <strong>Rückweg ohne Kommandozeile.</strong>
                <p style="margin:8px 0 0;">Ohne Anwendungsschlüssel läuft die Anwendung nicht.
                Der alte Schlüssel lässt sich nicht wiederherstellen. Ein neuer Schlüssel wird
                hier erzeugt und in der .env hinterlegt; der Wert wird nicht angezeigt und eine
                Sicherung der bisherigen Datei angelegt.</p>
                <p style="margin:8px 0 0;">Folge: Mit dem alten Schlüssel verschlüsselte Felder
                sind nicht mehr lesbar. Betroffen sind ausschließlich die Geheimnisse der
                Zwei-Faktor-Anmeldung; sie sind für die betroffenen Benutzer in der
                Benutzerverwaltung zurückzusetzen. Fachdaten, Beträge und Dokumente sind nicht
                betroffen.</p>
                <form method="POST" style="margin-top:10px;"
                      onsubmit="return confirm('Neuen Anwendungsschlüssel erzeugen? Geheimnisse der Zwei-Faktor-Anmeldung werden dadurch unlesbar.');">
                    <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
                    <input type="hidden" name="action" value="app-key">
                    <button type="submit">Anwendungsschlüssel neu erzeugen</button>
                </form>
            </div>
        <?php } ?>

        <?php if (($zweiFaktorBetroffen ?? 0) > 0) { ?>
            <div class="m fehler" style="margin-top:12px;">
                <strong>Zwei-Faktor-Anmeldung: <?= (int) $zweiFaktorBetroffen ?> Konto/Konten mit
                nicht lesbarem Geheimnis.</strong>
                <p style="margin:8px 0 0;">Das tritt nach einem Wechsel des Anwendungsschlüssels
                auf. Diese Konten können sich derzeit nicht anmelden. Es gibt zwei Wege:</p>
                <p style="margin:8px 0 0;"><strong>1. Verlustfrei, wenn der ALTE Schlüssel noch
                bekannt ist.</strong> In der .env zusätzlich eintragen:
                <code>APP_PREVIOUS_KEYS=base64:&lt;alter Schlüssel&gt;</code>. Laravel liest die
                Felder damit weiter und legt sie beim nächsten Schreiben mit dem neuen Schlüssel
                ab. Es ist dann nichts zurückzusetzen. Danach den Zwischenspeicher bereinigen und
                diese Seite neu laden.</p>
                <p style="margin:8px 0 0;"><strong>2. Zurücksetzen, wenn der alte Schlüssel
                verloren ist.</strong> Die betroffenen Benutzer richten die Zwei-Faktor-Anmeldung
                danach neu ein. Jeder Vorgang wird im Prüfpfad festgehalten.</p>
                <form method="POST" style="margin-top:10px;"
                      onsubmit="return confirm('Zwei-Faktor-Anmeldung für die betroffenen Konten zurücksetzen? Die Benutzer müssen sie danach neu einrichten.');">
                    <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
                    <input type="hidden" name="action" value="reset-2fa">
                    <button type="submit">Zwei-Faktor-Anmeldung der betroffenen Konten zurücksetzen</button>
                </form>
            </div>
        <?php } elseif ($zweiFaktorBetroffen === 0) { ?>
            <div class="m ok" style="margin-top:12px;">Zwei-Faktor-Anmeldung: kein Konto mit nicht
                lesbarem Geheimnis.</div>
        <?php } elseif ($zweiFaktorFehler !== null) { ?>
            <div class="m warnung" style="margin-top:12px;">Die Zwei-Faktor-Geheimnisse konnten
                nicht geprüft werden: <?= h($zweiFaktorFehler) ?></div>
        <?php } ?>

        <?php if ($proben !== []) { ?>
            <table style="margin-top:12px;">
                <thead><tr><th style="width:180px;">Probe</th><th>Ergebnis</th></tr></thead>
                <tbody>
                <?php foreach ($proben as [$name, $art, $ergebnis, $erklaerung]) { ?>
                    <tr>
                        <td><?= h($name) ?><div class="klein"><?= h($erklaerung) ?></div></td>
                        <td<?= $art === 'fehler' ? ' style="color:#8a2b1c;"' : '' ?>>
                            <?= $art === 'fehler' ? '<strong>' : '' ?><?= h($ergebnis) ?><?= $art === 'fehler' ? '</strong>' : '' ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>

    <div class="karte">
        <h2>3. Zwischenspeicher</h2>
        <?php if ($cacheVorhanden === []) { ?>
            <p class="klein">Es liegen keine zwischengespeicherten Dateien unter bootstrap/cache.
               Die Anwendung liest Konfiguration und Routen bei jedem Aufruf neu. Das ist im
               Produktivbetrieb langsamer, aber unkritisch.</p>
        <?php } else { ?>
            <p class="klein">Vorhanden: <?= h(implode(', ', $cacheVorhanden)) ?></p>
        <?php } ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
            <input type="hidden" name="action" value="clear-files">
            <button type="submit">Zwischenspeicher auf Dateiebene bereinigen</button>
        </form>
        <form method="POST">
            <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
            <input type="hidden" name="action" value="cache-config">
            <button type="submit" class="zweit"<?= $app === null ? ' disabled' : '' ?>>Konfiguration wieder zwischenspeichern</button>
        </form>
        <p class="klein">Die Bereinigung arbeitet mit unlink und funktioniert auch dann, wenn das
           Framework nicht startet. Sie ist der erste Handgriff nach jedem Datei-Upload.</p>
    </div>

    <div class="karte">
        <h2>4. Letzte Einträge des Anwendungsprotokolls</h2>
        <?php if ($protokoll === null) { ?>
            <p class="klein">Es ist kein Anwendungsprotokoll vorhanden. Bricht der Start ab,
               bevor das Protokoll eingerichtet ist, bleibt es leer; Punkt 1 und 2 sind dann
               die belastbaren Angaben.</p>
        <?php } else { ?>
            <p class="klein">Datei: <?= h($protokollDatei) ?>. Gezeigt wird der letzte
               vollständige Eintrag ab seinem Zeitstempel, nicht das Ende der Datei: die
               Ursache steht in der ersten Zeile, nicht im Aufrufstapel.</p>
            <?php if ($protokollKopf !== null) { ?>
                <div class="m fehler" style="word-break:break-word;"><?= h(mb_substr($protokollKopf, 0, 600)) ?></div>
            <?php } ?>
            <pre><?= h($protokoll) ?></pre>
        <?php } ?>
    </div>

    <?php if ($altlasten !== []) { ?>
    <div class="karte">
        <h2>5. Offene Altlasten im Verzeichnis public/</h2>
        <div class="m warnung">Folgende Dateien sind ohne Zugriffsschlüssel öffentlich erreichbar
            und zeigen Serverpfade, den Datenbanknamen und Auszüge aus dem Fehlerprotokoll:
            <?= h(implode(', ', $altlasten)) ?>.</div>
        <form method="POST">
            <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
            <input type="hidden" name="action" value="delete-altlasten">
            <button type="submit">Diese Dateien jetzt entfernen</button>
        </form>
    </div>
    <?php } ?>

    <div class="karte">
        <h2><?= $altlasten !== [] ? '6' : '5' ?>. Abschluss</h2>
        <p class="klein">Diese Datei nach der Behebung entfernen. Sie ist mit einem
           Zugriffsschlüssel geschützt, gehört aber nicht dauerhaft in ein öffentliches
           Verzeichnis.</p>
        <form method="POST" onsubmit="return confirm('Notfalldiagnose jetzt löschen?');">
            <input type="hidden" name="token" value="<?= $tokenEscaped ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit">Datei löschen</button>
        </form>
    </div>

    <div class="fuss">
        <strong>Müller Holding AG</strong> · Rheinpromenade 13 · 40789 Monheim am Rhein ·
        kontakt@mueller-holding.ag · mueller-holding.ag<br>
        Notfalldiagnose. Nach Gebrauch löschen.
    </div>
</div>
</body>
</html>
<?php
$GLOBALS['notfallFertig'] = true;
if (ob_get_level() > 0) {
    ob_end_flush();
}
