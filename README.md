# Müller Holding AG – Intranet

Interne Unternehmensplattform der Müller Holding AG für Darlehen, Beteiligungen,
Corporate Governance und Vermögensmanagement.

Produktive URL: **https://intranet.mueller-holding.ag**

## Fachlicher Umfang

- **Stammdaten:** zentraler Geschäftspartnerstamm (Personen, Unternehmen, sonstige
  Organisationen) mit Mehrfachrollen, Adressen, Bankkonten, Steuerdaten,
  Identitätsdokumenten, Unternehmensbeziehungen und Organstellungen.
- **Darlehen:** Darlehensverwaltung mit strikter SOLL/IST-Trennung, systemseitig
  angenommenen Zahlungen (deutlich gekennzeichnet), Teilzahlungen, rückwirkender
  Erfassung und Korrektur, taggenauer Zinsrechnung (ACT/365, ACT/360, 30/360,
  ACT/ACT), Staffelzinsen, Gebühren, Sicherheiten, Bürgschaften, Darlehenskonto
  (append-only) und deterministischer Neuberechnungs-Engine mit Protokoll.
- **Verträge und Dokumente:** versionierte Vertragsvorlagen mit Platzhaltern,
  Vertrags-Snapshots, PDF-Erzeugung im CI, Dokumentenmanagement mit
  SHA-256-Integritätsprüfung und SFTP-Ablage.
- **Holding:** Aktionärsverwaltung, Aktienbewegungen (Bestand ausschließlich aus
  wirksamen Transaktionen berechnet, stichtagsfähig), Aktionärslisten als
  unveränderliche PDF-Snapshots, Beteiligungen, Vorstand und Aufsichtsrat mit
  Organhistorie, Beschlussverwaltung mit Abstimmungen und Beschlussregister,
  digitale Signaturen über abstrakte Anbieter-Schnittstelle.
- **Controlling und Organisation:** Dashboards mit "Heute relevant", Reports mit
  PDF/XLSX/CSV-Export, Liquiditätsplanung, Kalender, Wiedervorlagen,
  Benachrichtigungen, Hilfe/FAQ/Changelog.
- **Sicherheit:** RBAC mit granularen Berechtigungen, Datenscope für externe
  Benutzer, Zwei-Faktor-Authentifizierung (TOTP, für sensible Rollen
  verpflichtend), Einladungsflow mit Einmal-Tokens, Login-Historie,
  Rate-Limiting, Audit-Trail, Backups.

Fachliche Leitplanken: keine stillen Finanzkorrekturen (nur Storno und
Gegenbuchung), Wirkungsdatum und Erfassungsdatum getrennt, keine doppelte
Wahrheit (berechnete Salden), rechtliche Zurückhaltung (das System bewertet
keine gesetzlichen Mehrheiten oder Formwirksamkeiten), keine erfundenen Daten.

## Technik

| Komponente | Technologie |
|---|---|
| Backend | PHP 8.4, Laravel 13, Service-Layer, Enums, BCMath-Geldarithmetik |
| Datenbank | MariaDB 10.11/11 (Produktion), SQLite (Entwicklung/Tests) |
| Frontend | Blade, Bootstrap 5.3 (lokal, kein CDN), Bootstrap Icons, Chart.js |
| PDF | dompdf (CI-Briefkopf mit Pflichtangaben) |
| RBAC | spatie/laravel-permission |
| 2FA | pragmarx/google2fa + QR-Code |
| Dateiablage | Flysystem: SFTP (bevorzugt) oder lokal, konfigurierbar |

## Entwicklung

```bash
composer install
cp .env.example .env            # für lokale Entwicklung DB_CONNECTION=sqlite setzen
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Anmeldung nach dem Seeding: `timo@muellerhv.de` mit dem Passwort aus
`SEED_ADMIN_PASSWORD` (Standard: `Bitte-sofort-aendern-2026`).
**Das Passwort ist nach der ersten Anmeldung sofort zu ändern.** Die Rolle
Administrator unterliegt der 2FA-Pflicht; die Einrichtung wird beim ersten
Login erzwungen.

Tests:

```bash
php artisan test
```

## Struktur

- `docs/BAUPLAN.md` – Architektur und verbindliche Konventionen
- `docs/DEPLOYMENT.md` – Produktivbetrieb unter intranet.mueller-holding.ag
- `docs/RESTORE.md` – Wiederherstellung von Datenbank und Dokumenten
- `app/Services/` – Fachlogik (Zinsen, Neuberechnung, Verrechnung, Aktien, Storage, Signaturen)
- `app/Enums/` – Statuswerte mit deutschen Labels und Statusfarben
- `routes/modules/` – Routen je Fachmodul
