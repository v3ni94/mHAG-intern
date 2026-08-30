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
