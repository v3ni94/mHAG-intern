# Anforderungen: Zinsmodalitäten, Kontostand, Ertragsauswertung, Ausfall

Fachliche Vorgaben des Auftraggebers vom 30.08.2026. Diese Datei ist die
verbindliche Grundlage für die Umsetzung; sie ergänzt `docs/BAUPLAN.md`.

## Stand vor der Umsetzung

| Anforderung | Stand | Bemerkung |
|---|---|---|
| Zinsfälligkeit monatlich, jährlich, zum Vertragsende | vorhanden | `loans.interest_frequency`: monthly, quarterly, semiannual, annual, at_maturity, custom |
| Fälligkeitstag (zu welchem Tag) | **fehlt** | Der Zahlungsplan leitet den Tag heute aus dem Wirkungsbeginn ab, er ist nicht einstellbar |
| Zinsen dem valutierten Betrag zuschreiben (Zinskapitalisierung) | **fehlt** | Kein Feld, kein Rechenweg, keine Buchungsart |
| Kontostand je Darlehen | teilweise | Konto-Tab zeigt einen laufenden Saldo, Kennzahl "Gesamtforderung" vorhanden; in der Darlehensliste fehlt der Stand |
| Ertrag und Rendite | **fehlt** | Keine Auswertung vorhanden |
| Markierung als Ausfall | vorhanden | Status "Ausgefallen" und "Abgeschrieben" über Statuswechsel; Wirkung auf die Zinsrechnung ist noch nicht geregelt |

## 1. Fälligkeitstag der Zinsen

Neue Felder an `loans` (additive Migration):

- `interest_due_day_mode` (string): `effective_from` (Tag des Wirkungsbeginns, Standard und bisheriges Verhalten),
  `fixed_day` (fester Tag im Monat), `month_end` (Monatsletzter).
- `interest_due_day` (unsigned tinyint, nullable): 1 bis 28, nur bei `fixed_day`.

Begrenzung auf 28 ist bewusst: Ein fester 29., 30. oder 31. existiert nicht in
jedem Monat und führt zu uneinheitlichen Perioden. Wer den Monatsletzten
möchte, wählt `month_end`.

Wirkung im `LoanScheduleService`: Der Fälligkeitstag jeder Zinsperiode richtet
sich nach dieser Einstellung. Die Zinsperiode selbst bleibt taggenau, es
verschiebt sich nur der Fälligkeitstag. Bestehende Darlehen behalten durch den
Standardwert ihr bisheriges Verhalten.

**Umgesetzt am 30.08.2026.** Präzisierung gegenüber dem Entwurf, weil sonst
offen bliebe, welcher Zeitraum zu welcher Fälligkeit gehört:

- Die Periode endet am Fälligkeitstag **einschließlich**, die nächste beginnt
  am Folgetag. Perioden sind dadurch lückenlos und überschneidungsfrei. Das
  entspricht dem bisherigen Verhalten, bei dem die Fälligkeit einen Tag vor
  dem gleichen Tag der Folgeperiode liegt.
- Erster Fälligkeitstag ist der erste des Rasters, der nicht vor dem
  Wirkungsbeginn liegt. Die erste Periode kann dadurch kürzer oder länger als
  die Folgeperioden sein (Stummelperiode); sie wird taggenau gerechnet.
- Eine unvollständige Vorgabe (Modus `fixed_day` ohne Tag oder mit einem Tag
  außerhalb 1 bis 28) wird nicht geraten: es bleibt beim Standardverhalten.
- Tilgungsraten bei Ratentilgung und Annuität folgen demselben Raster, damit
  Zins und Tilgung einer Periode am gleichen Tag fällig werden. Endfällige
  Tilgung bleibt am Vertragsende.

Belegt durch `tests/Feature/Loans/EngineInterestDueDayTest.php` (9 Tests) und
drei Formulartests in `tests/Feature/Loans/UiLoanCrudTest.php`.

## 2. Zinskapitalisierung

Neues Feld `loans.interest_capitalization` (boolean, Standard false).

Ist es gesetzt, werden fällige Zinsen **nicht** als Zahlung erwartet, sondern
dem valutierten Betrag zugeschrieben. Fachliche Folgen:

1. Zum Fälligkeitstag entsteht eine Buchung der neuen Art
   `interest_capitalization`, die das Kapital erhöht. Der Betrag entspricht den
   für die Periode berechneten Zinsen.
2. Für diese Periode entsteht **keine** offene Zinsforderung. Die Position im
   Zahlungsplan erhält den Status "kapitalisiert" und wird nicht als überfällig
   gezählt.
3. Die Folgeperioden verzinsen das erhöhte Kapital (Zinseszins). Das ergibt
   sich automatisch, weil der Kapitalverlauf aus `loan_transactions` gebildet
   wird; die neue Buchungsart muss dort als kapitalerhöhend berücksichtigt
   werden.
4. `LoanBalanceService`: Kapitalisierte Zinsen erscheinen im Kapital, nicht in
   `interest_open`. Ein eigener Schlüssel `interest_capitalized` weist die
   Summe getrennt aus, damit der Ertrag nachvollziehbar bleibt.
5. In der Forderungsaufstellung werden kapitalisierte Zinsen im Kapital
   ausgewiesen, mit erläuternder Zeile. Keine Doppelzählung.

Umschaltbar auch bei laufenden Darlehen: Die Umstellung wirkt ab dem
Wirkungsdatum der Änderung, frühere Perioden bleiben unverändert. Der Wechsel
löst eine Neuberechnung ab diesem Datum aus und wird im Änderungsprotokoll
festgehalten.

## 3. Kontostand je Darlehen

- Neue Kennzahl "Kontostand" auf der Darlehensdetailseite: der aktuelle Saldo
  des Darlehenskontos zum heutigen Tag, also die Summe aller Buchungen. Klar
  abgegrenzt von "Gesamtforderung", die zusätzlich noch nicht gebuchte
  Soll-Positionen bis zum Stichtag enthält. Beide Werte mit Hilfe-Symbol und
  Erläuterung, damit der Unterschied verständlich ist.
- Spalte "Kontostand" in der Darlehensliste, mit Sortiermöglichkeit.
  Performance beachten: Aggregation je Darlehen in einer Abfrage, keine
  Berechnung je Zeile.

## 4. Ertrag und Rendite

Neue Auswertung je Darlehen (Tab "Ertrag") und als Report über alle sichtbaren
Darlehen. Alle Kennzahlen werden mit ihrer Berechnungsweise angezeigt, damit
nichts als unerklärte Zahl im Raum steht (Masterprompt §140).

Auszuweisen:

1. **Vereinnahmte Zinsen**: Summe der bestätigten Zinszahlungen. Systemseitig
   angenommene Zahlungen werden getrennt ausgewiesen und nicht mit bestätigten
   vermischt (Masterprompt §24).
2. **Kapitalisierte Zinsen**: Summe der Buchungen `interest_capitalization`.
3. **Vereinnahmte Gebühren**: Summe der bestätigten Gebührenzahlungen.
4. **Ertrag insgesamt**: vereinnahmte Zinsen + kapitalisierte Zinsen +
   vereinnahmte Gebühren.
5. **Durchschnittlich gebundenes Kapital**: zeitgewichteter Mittelwert des
   offenen Kapitals über den Betrachtungszeitraum
   (Summe aus Kapital mal Tage, geteilt durch die Gesamttage).
6. **Rendite p. a.**: Ertrag insgesamt, geteilt durch durchschnittlich
   gebundenes Kapital, hochgerechnet auf ein Jahr über die Zinsmethode des
   Darlehens. Formel wird angezeigt.
7. **Effektivrendite (interner Zinsfuß)**: aus den tatsächlichen Zahlungsströmen
   (Auszahlungen negativ, Zahlungseingänge positiv, Restforderung zum Stichtag
   als Schlussbetrag). Ermittlung numerisch über Intervallhalbierung mit
   BCMath, Abbruch bei einer Genauigkeit von 0,000001. Ist keine Lösung im
   Bereich von minus 99 Prozent bis plus 1000 Prozent ermittelbar, wird
   "nicht berechenbar" ausgewiesen, statt eine Zahl zu erfinden.

Wichtig: Die Effektivrendite ist eine rechnerische Kennzahl, keine
Bonitäts- oder Wertaussage. Kein Vergleich mit Marktzinsen, keine Prognose.

## 5. Ausfall

Der Status "Ausgefallen" existiert. Zu ergänzen:

- Eigene Aktion "Ausfall erfassen" mit Ausfalldatum (Wirkungsdatum), Grund und
  optionalem Abschreibungsbetrag.
- Ab dem Ausfalldatum werden **keine** weiteren Soll-Zinsen erzeugt, bereits
  entstandene bleiben erhalten. Begründung: Zinsen nach dem Ausfall wären eine
  Forderung, die das System nicht unterstellen darf.
- Ein Abschreibungsbetrag wird als Buchung `write_off` erfasst und reduziert
  die Forderung. Ohne Betrag bleibt die Forderung bestehen und nur der Status
  ändert sich.
- Der Ausfall ist rücknehmbar (Statuswechsel zurück nach aktiv), die Buchungen
  bleiben erhalten und werden bei Bedarf per Gegenbuchung aufgehoben.
- Keine rechtliche Bewertung, keine automatische Einstufung als
  uneinbringlich (Masterprompt §133).

## Reihenfolge der Umsetzung

1. Fälligkeitstag (kleine, klar abgegrenzte Änderung am Zahlungsplan)
2. Zinskapitalisierung (Eingriff in Kapitalverlauf und Salden, sorgfältig testen)
3. Kontostand in Detailseite und Liste
4. Ertrag und Rendite
5. Ausfall erfassen

Jeder Punkt mit Tests, deren Erwartungswerte von Hand vorgerechnet und als
Kommentar hinterlegt sind.
