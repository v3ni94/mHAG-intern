{{--
    Hinweis auf die zwischengespeicherte Konfiguration.

    Wurde php artisan config:cache ausgeführt, liest die Anwendung die Datei
    .env nicht mehr. Änderungen an Zugangsdaten wirken dann erst, nachdem die
    Zwischenspeicher geleert wurden. Ohne diesen Hinweis sucht man den Fehler
    in den Zugangsdaten, obwohl er in der Zwischenspeicherung liegt.
--}}
@php($konfigurationZwischengespeichert = file_exists(base_path('bootstrap/cache/config.php')))
@if ($konfigurationZwischengespeichert)
    <div class="alert alert-warning small">
        <strong>Die Konfiguration ist zwischengespeichert.</strong>
        Änderungen in der Datei .env wirken erst, nachdem die Zwischenspeicher geleert wurden.
        Solange gelten die Werte aus dem Zwischenspeicher, auch wenn die .env bereits geändert wurde.
        Auf Systemen ohne Kommandozeile geschieht das über das Aktualisierungswerkzeug
        (Schritt "Zwischenspeicher leeren"), sonst mit <code>php artisan optimize:clear</code>.
    </div>
@endif
