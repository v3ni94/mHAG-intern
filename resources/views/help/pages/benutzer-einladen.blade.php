<p>
    Neue Benutzer werden per E-Mail-Einladung aufgenommen. Der Einladungslink ist zufällig,
    sieben Tage gültig und nur einmal verwendbar. Diese Funktion steht Administratoren zur Verfügung.
</p>

<h2 class="h6 mt-4">Einladung versenden</h2>
<ol>
    <li>Öffnen Sie <strong>Administration &rarr; Benutzer</strong> und wechseln Sie zu <strong>Einladungen</strong>.</li>
    <li>Wählen Sie optional die zugehörige Person bzw. das Unternehmen aus dem Stammdatenbestand.</li>
    <li>Geben Sie die E-Mail-Adresse des Empfängers ein.</li>
    <li>Wählen Sie die Rollen, zum Beispiel <em>Darlehensgeber</em> oder <em>Aufsichtsratsmitglied</em>.</li>
    <li>Legen Sie den <strong>Datenbereich</strong> fest: die Entitäten, deren Daten der Benutzer sehen darf.
        Externe Benutzer sehen ausschließlich Datensätze ihrer zugeordneten Entitäten.</li>
    <li>Klicken Sie auf <strong>Einladung senden</strong>. Der Empfänger erhält eine E-Mail mit persönlichem Link.</li>
</ol>

<h2 class="h6 mt-4">Ablauf beim Empfänger</h2>
<ol>
    <li>Der Empfänger öffnet den Link, vergibt Name und Passwort (mindestens 12 Zeichen mit Buchstaben und Zahlen).</li>
    <li>Nach der Aktivierung wird er zur Einrichtung der Zwei-Faktor-Authentifizierung geführt,
        sofern seine Rolle dies verlangt.</li>
</ol>

<h2 class="h6 mt-4">Einladungen verwalten</h2>
<ul>
    <li><strong>Erneut senden</strong> erzeugt einen neuen Link; der alte wird ungültig.</li>
    <li><strong>Widerrufen</strong> sperrt die Einladung dauerhaft.</li>
    <li>Der Status (offen, angenommen, abgelaufen, widerrufen) ist in der Liste sichtbar.</li>
</ul>

<div class="alert alert-info small mt-3 mb-0">
    Aus Sicherheitsgründen speichert das System nur einen Prüfwert (SHA-256-Hash) des Einladungslinks,
    nie den Link selbst. Ein verlorener Link kann daher nicht angezeigt, sondern nur neu erzeugt werden.
</div>
