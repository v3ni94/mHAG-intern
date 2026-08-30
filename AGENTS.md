# Müller Holding AG Intranet – Hinweise für die Entwicklung

Laravel-13-Anwendung (PHP 8.4). Interne Plattform der Müller Holding AG für
Darlehen, Beteiligungen, Corporate Governance und Vermögensmanagement.

## Verbindliche Dokumente

- `docs/BAUPLAN.md` – Architektur, Schema, Service-Signaturen, Konventionen.
  Vor jeder inhaltlichen Änderung lesen.
- Masterprompt-Anforderungen sind in BAUPLAN Teil 1 zusammengefasst
  (Abschnittsnummern §1-§145 referenzieren den Masterprompt).

## Eiserne Regeln

1. **Geld:** nie float. `App\Support\Money` (BCMath, Dezimalstrings),
   DB `DECIMAL(18,2)`, Zinssätze `DECIMAL(9,6)`. Ausgabe `format_money()`
   (1.234,56 EUR), Datum `format_date()` (TT.MM.JJJJ).
2. **SOLL/IST strikt getrennt.** Jeder IST-Wert trägt eine Herkunft
   (`PaymentOrigin`): systemseitig angenommen ist NIE gleich bestätigt.
3. **Keine stillen Finanzkorrekturen:** nur Storno/Gegenbuchung/Korrektur,
   `loan_transactions` und `share_transactions` sind append-only.
4. **Wirkungsdatum vs. Erfassungsdatum** überall getrennt führen.
5. **Keine doppelte Wahrheit:** Salden und Aktienbestände werden aus
   Transaktionen berechnet (LoanBalanceService, ShareholdingService).
6. **Datenscope:** externe Rollen sehen nur zugeordnete Entities
   (`visibleTo`-Scopes); interne Notizen und Risikoeinstufung nur für
   `$user->isInternal()`.
7. **Audit:** kritische Aktionen über `AuditService::log()`.
8. **Rechtliche Zurückhaltung:** keine Bewertung von Mehrheiten,
   Formwirksamkeit oder Verzugszinshöhen; keine erfundenen Daten.
9. UI Deutsch, Status immer Icon+Text (`<x-enum-badge>`), keine
   Gedankenstriche in deutschen Texten, kein CDN (Assets liegen lokal).

## Entwicklung

```bash
php artisan migrate:fresh --seed   # SQLite (database/database.sqlite)
php artisan test                   # PHPUnit, SQLite :memory:
php artisan serve
```

Login nach Seeding: `timo@muellerhv.de` / `SEED_ADMIN_PASSWORD`
(Standard `Bitte-sofort-aendern-2026`).

Neue Fachrouten gehören in `routes/modules/<modul>.php` (wird automatisch
innerhalb der auth+active+two-factor-Gruppe geladen).
