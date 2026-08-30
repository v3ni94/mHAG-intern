<div class="alert alert-warning">
    <strong>Nicht implementiert.</strong>
    Ein Datenimport aus Dateien (CSV, XLSX) ist in dieser Version nicht enthalten.
    Es gibt keine Importseite, keinen Assistenten zum Zuordnen von Spalten und keine
    Importvorschau. Der Menüpunkt <strong>Administration &rarr; Datenimport</strong>
    führt bewusst auf diese Seite, damit die Lücke erkennbar ist und nicht als
    vorhandene Funktion missverstanden wird.
</div>

<p>
    Der Masterprompt beschreibt den Dateiimport als optionalen Bestandteil
    (Abschnitt 109). Umgesetzt ist er nicht. Damit fehlen alle sieben Schritte des
    dort beschriebenen Ablaufs.
</p>

<h2 class="h6 mt-4">Was nicht vorhanden ist</h2>
<ul>
    <li>Datei hochladen und automatisch analysieren.</li>
    <li>Spalten der Datei den Feldern des Systems zuordnen.</li>
    <li>Vorschau der zu importierenden Datensätze.</li>
    <li>Anzeige fehlerhafter Zeilen vor dem Import.</li>
    <li>Bestätigung und protokollierter Importlauf.</li>
    <li>Import für Personen, Unternehmen, Darlehen, Zahlungen, Aktienbewegungen
        und historische Beschlüsse.</li>
</ul>

<h2 class="h6 mt-4">Vorgehen bis zur Umsetzung</h2>
<ol>
    <li>Datensätze über die Erfassungsmasken der Module anlegen
        (<strong>Stammdaten</strong>, <strong>Finanzen</strong>,
        <strong>Müller Holding AG</strong>).</li>
    <li>Historische Vorgänge über die Anleitung
        <strong>Historische Darlehen anlegen</strong> erfassen. Wirkungsdatum und
        Erfassungsdatum werden dabei getrennt geführt, der Verlauf wird
        rekonstruiert.</li>
    <li>Belege als Dokument hochladen und mit der jeweiligen Akte verknüpfen.</li>
    <li>Für größere Datenmengen bitte den Bedarf an die Geschäftsführung melden.
        Ein Import muss fachlich abgestimmt, entwickelt und getestet werden,
        weil fehlerhaft importierte Beträge und Datumsangaben unmittelbar in die
        Zins- und Saldenberechnung wirken.</li>
</ol>

<div class="alert alert-info small mt-3 mb-0">
    Kein direkter Zugriff auf die Datenbank zum Einspielen von Daten. Ohne die
    Prüf- und Protokollschritte der Anwendung entstehen Bestände ohne Audit-Trail
    und ohne die Trennung von SOLL und IST.
</div>
