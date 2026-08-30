<p>
    Bestehende Altverträge können vollständig rückwirkend erfasst werden. Das System rekonstruiert
    den historischen Verlauf ab dem Vertragsbeginn.
</p>

<h2 class="h6 mt-4">Historisches Darlehen erfassen</h2>
<ol>
    <li>Legen Sie das Darlehen wie gewohnt an (<strong>Finanzen &rarr; Darlehen &rarr; Neues Darlehen</strong>).</li>
    <li>Setzen Sie den <strong>Wirkungsbeginn</strong> auf das tatsächliche historische Vertragsdatum,
        zum Beispiel den 01.01.2024. Das Erfassungsdatum (heute) wird getrennt gespeichert.</li>
    <li>Erfassen Sie die historischen Auszahlungen mit ihren tatsächlichen Daten und Beträgen.</li>
    <li>Das System erzeugt den Zahlungsplan ab dem Wirkungsbeginn. Vergangene Perioden erhalten den
        Status <strong>systemseitig angenommen</strong>: Es wird zunächst planmäßige Erfüllung unterstellt
        und deutlich als Annahme gekennzeichnet.</li>
    <li>Korrigieren Sie anschließend alle bekannten Abweichungen: Öffnen Sie im Darlehen den Tab
        <strong>Soll/Ist</strong> und erfassen Sie je Monat den tatsächlichen Zahlungsstatus
        (bestätigt, nicht bezahlt, Teilzahlung).</li>
    <li>Nach jeder Änderung berechnet das System Zinsen, offene Posten und Forderungsstand ab dem
        betroffenen Datum deterministisch neu.</li>
</ol>

<div class="alert alert-warning small mt-3 mb-0">
    Prüfen Sie bei historischen Verträgen den rekonstruierten Forderungsstand gegen Ihre Unterlagen
    (z. B. letzte Zinsabrechnung), bevor Sie das Darlehen produktiv weiterführen.
</div>
