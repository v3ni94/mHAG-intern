<p>
    Wurde eine Zinsrate nicht gezahlt, erfassen Sie den Ausfall in der Monatsübersicht des Darlehens.
    Das System berechnet danach alle Folgewerte automatisch neu.
</p>

<h2 class="h6 mt-4">Nicht gezahlte Zinsrate erfassen</h2>
<ol>
    <li>Öffnen Sie das Darlehen (<strong>Finanzen &rarr; Darlehen</strong>, Darlehen anklicken).</li>
    <li>Wechseln Sie zum Tab <strong>Soll/Ist</strong> (Monatsübersicht des Zahlungsplans).</li>
    <li>Wählen Sie den betroffenen Monat aus.</li>
    <li>Setzen Sie den Status auf <strong>"Nicht bezahlt"</strong>; der Istbetrag wird 0,00 EUR.</li>
    <li>Ergänzen Sie optional einen Kommentar (z. B. "Rate lt. Kontoauszug nicht eingegangen").</li>
    <li>Klicken Sie auf <strong>Speichern</strong>.</li>
    <li>Warten Sie die automatische <strong>Neuberechnung</strong> ab; sie wird im Tab
        <strong>Neuberechnungen</strong> protokolliert.</li>
    <li>Prüfen Sie den neuen Forderungsstand im Kopf der Darlehensakte bzw. in der Forderungsaufstellung.</li>
</ol>

<h2 class="h6 mt-4">Auswirkungen</h2>
<ul>
    <li>Die offene Zinsforderung erhöht sich um die ausgefallene Rate.</li>
    <li>Dashboard, Reports (offene Posten, überfällige Darlehen) und der Block "Heute relevant"
        zeigen den Ausfall als überfällige Zahlung in Rot.</li>
    <li>Falls vertraglich vereinbart, können Verzugszinsen auf den rückständigen Betrag berechnet werden.</li>
</ul>

<div class="alert alert-info small mt-3 mb-0">
    Rückwirkende Korrektur: Auch weiter zurückliegende Monate können nachträglich auf "Nicht bezahlt"
    gesetzt werden. Das System berechnet ab dem betroffenen Monat alle Folgewerte neu und protokolliert
    die Änderung im Audit-Trail.
</div>
