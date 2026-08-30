<p>
    Zahlungseingänge und -ausgänge werden mit Herkunftskennzeichnung erfasst und nach der konfigurierten
    Verrechnungsreihenfolge (Kosten, Gebühren, Verzugszinsen, Zinsen, Tilgung) auf offene Posten verteilt.
</p>

<h2 class="h6 mt-4">Zahlung erfassen</h2>
<ol>
    <li>Öffnen Sie <strong>Finanzen &rarr; Zahlungen</strong> und klicken Sie auf <strong>Neue Zahlung</strong>,
        oder erfassen Sie die Zahlung direkt aus dem Darlehen heraus.</li>
    <li>Wählen Sie das Darlehen sowie Zahler und Empfänger.</li>
    <li>Erfassen Sie Zahlungsdatum, Wertstellung, Betrag und Verwendungszweck.</li>
    <li>Wählen Sie die <strong>Herkunft</strong>: manuell bestätigt (z. B. anhand des Kontoauszugs),
        manuell erfasst oder Bankimport.</li>
    <li>Speichern Sie. Das System erstellt die Verrechnung auf Gebühren, Zinsen und Tilgung und bucht
        die Beträge in das Darlehenskonto. Die betroffenen Zahlungsplan-Positionen erhalten den Status
        <strong>Bestätigt bezahlt</strong> bzw. <strong>Teilweise bezahlt</strong>.</li>
</ol>

<h2 class="h6 mt-4">Zahlung stornieren</h2>
<ol>
    <li>Öffnen Sie die Zahlung und wählen Sie <strong>Stornieren</strong>.</li>
    <li>Geben Sie einen Stornogrund an. Die Zahlung wird nicht gelöscht, sondern durch eine Gegenbuchung
        neutralisiert; die Historie bleibt vollständig erhalten.</li>
</ol>

<div class="alert alert-info small mt-3 mb-0">
    Keine stillen Korrekturen: Beträge werden nie überschrieben. Fehler werden immer über Storno oder
    Korrekturbuchung bereinigt, damit der Audit-Trail lückenlos bleibt.
</div>
