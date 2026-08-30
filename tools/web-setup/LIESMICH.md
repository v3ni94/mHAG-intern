# Web-Setup für Installationen ohne Kommandozeile

`setup.php` richtet die Anwendung über den Browser ein. Gedacht für
Hosting-Umgebungen ohne SSH-Zugang, etwa Webspace-Pakete.

Das Skript übernimmt:

1. Prüfung der Systemvoraussetzungen (PHP-Version, Erweiterungen, Schreibrechte)
2. Anlegen der `.env` aus der Vorlage
3. Erzeugen des Anwendungsschlüssels auf dem Server (wird nie angezeigt)
4. Prüfung der Datenbankverbindung
5. Anlegen von Datenbankstruktur und Startdaten
6. Zwischenspeichern von Konfiguration, Routen und Ansichten
7. Löschen der eigenen Datei zum Abschluss

## Verwendung

**1. Zugriffsschlüssel erzeugen.** Auf einem Rechner mit PHP:

```bash
php -r "\$t = bin2hex(random_bytes(16)); echo \"Schluessel: \$t\n\"; echo 'Hash: ' . password_hash(\$t, PASSWORD_DEFAULT) . \"\n\";"
```

**2. Hash eintragen.** Den ausgegebenen Hash in `setup.php` bei
`SETUP_TOKEN_HASH` einsetzen. Ohne Hash antwortet das Skript grundsätzlich mit
404 und tut nichts.

**3. Hochladen.** Datei nach `public/` der Anwendung übertragen. Ein
unauffälliger Dateiname ist sinnvoll, etwa `setup-7f3a91.php`.

**4. Aufrufen.**

```
https://<domain>/setup.php?token=<Zugriffsschlüssel aus Schritt 1>
```

**5. Abschließen.** Nach der Einrichtung die Schaltfläche
"Setup beenden und Datei löschen" betätigen. Bleibt die Datei liegen, entfernen
Sie sie über den Dateimanager.

## Sicherheitshinweise

- Ohne gültigen Zugriffsschlüssel liefert das Skript 404, es verrät seine
  Existenz also nicht.
- Der Anwendungsschlüssel wird auf dem Server erzeugt und gespeichert. Er wird
  weder angezeigt noch übertragen.
- Ein bereits gesetzter Anwendungsschlüssel wird nicht überschrieben, damit
  verschlüsselte Daten (Zwei-Faktor-Geheimnisse) lesbar bleiben.
- Die Datei gehört nach der Einrichtung gelöscht. Sie ist ein
  Einrichtungswerkzeug, kein Bestandteil des Betriebs.
- Mit SSH-Zugang ist der reguläre Weg vorzuziehen:
  `php artisan key:generate` und `php artisan migrate --seed --force`.

## `update.php` – Aktualisierungen ohne Kommandozeile

Für nachträgliche Datei-Uploads. Führt in dieser Reihenfolge aus:
Zwischenspeicher leeren, Datenbankänderungen einspielen, Rollen und
Berechtigungen einlesen, für den Produktivbetrieb optimieren, eigene Datei
löschen. Es legt keine Datenbank an, spielt keine Startdaten ein und kann
nichts zurücksetzen.

Zwei Sicherungen sind eingebaut:

- **Sperre bei fehlerhafter `.env`.** Vor allen Schritten wird die `.env`
  zeilenweise geprüft. Findet die Prüfung einen Fehler, der den Start
  verhindert, sind alle Schaltflächen gesperrt und die betroffenen
  Zeilennummern werden benannt. Grund siehe unten.
- **Rücknahme der Optimierung.** Bricht `config:cache`, `route:cache` oder
  `view:cache` ab, werden die bereits geschriebenen Dateien wieder entfernt.
  Ohne Zwischenspeicher ist die Anwendung langsamer, aber erreichbar.
  Erreichbarkeit geht vor.

## `notfall.php` – Notfalldiagnose bei Serverfehler 500

Antwortet die Anwendung auf jeder Seite mit einem nackten Serverfehler 500,
ist die Ursache meist **nicht** im Anwendungsprotokoll zu finden. `notfall.php`
arbeitet deshalb zuerst ohne das Framework:

1. zeilenweise Prüfung der `.env` mit Zeilennummer und Ursache
2. Zustand der Zwischenspeicher
3. Startversuch des Frameworks mit Anzeige der Ausnahme im Klartext
4. letzte 40 Zeilen des Anwendungsprotokolls
5. Entfernen der Zwischenspeicher **auf Dateiebene**, funktioniert also auch
   dann, wenn das Framework nicht mehr startet
6. Entfernen von `diagnose.php` und `pruefung.php`, falls noch vorhanden

Werte aus der `.env` werden nie angezeigt, auch nicht in Auszügen. Ein Befund
nennt Zeilennummer, Namen der Einstellung und Ursache. Eine gestörte Zeile kann
Teil eines Schlüssels oder Kennworts sein.

### Warum ein Fehler in der `.env` erst später auffällt

Die `.env` wird gelesen, bevor Laravel eine Fehlerbehandlung oder ein Protokoll
besitzt. Bei einer ungültigen Zeile setzt das Framework HTTP 500, schreibt die
Meldung nach `stderr` und beendet den Prozess mit `exit`. In der Antwort steht
also nichts, im Anwendungsprotokoll ebenfalls nichts, und ein `try/catch`
greift nicht.

Entscheidend: Solange `bootstrap/cache/config.php` vorhanden ist, wird die
`.env` **überhaupt nicht gelesen**. Ein Fehler bleibt dann verdeckt und wirkt
erst in dem Moment, in dem der Zwischenspeicher geleert wird, also
typischerweise unmittelbar nach einem Datei-Upload.

Häufigste Ursache in der Praxis: ein mehrzeiliger Wert, etwa ein privater
SSH-Schlüssel, wird von Hand aus der `.env` entfernt und es bleiben Zeilen wie
`-----END OPENSSH PRIVATE KEY-----` stehen. Solche Zeilen enthalten kein
Gleichheitszeichen und sind damit weder Einstellung noch Kommentar.

Die geprüften Regeln liegen in `app/Support/EnvFileInspector.php` und werden in
`tests/Unit/EnvFileInspectorTest.php` gegen den tatsächlich verwendeten Parser
abgeglichen.
