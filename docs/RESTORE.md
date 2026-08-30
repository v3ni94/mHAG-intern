# Backup und Restore

Grundsatz: Datenbank (strukturierte Wahrheit) und Dokumentenablage (Dateien)
müssen **gemeinsam konsistent** gesichert und wiederhergestellt werden.
Ein Restore nur einer der beiden Seiten führt zu verwaisten Metadaten oder
Dateien.

## 1. Was wird gesichert

| Bestandteil | Inhalt | Verfahren |
|---|---|---|
| MariaDB | Stammdaten, Transaktionen, Verträge, Soll/Ist, Aktien, Beschlüsse, Dokument-Metadaten, Audit-Trail | `mysqldump` (täglich via Scheduler, zusätzlich vor jedem Update manuell) |
| Dokumente | Dateien auf dem SFTP-Server bzw. `storage/app/documents` | Snapshot/rsync des SFTP-Ziels bzw. Verzeichnisses |
| Konfiguration | `.env` (enthält Schlüssel!), Nginx-Konfiguration | verschlüsselte Ablage in der Passwort-/Secret-Verwaltung |

Wichtig: `APP_KEY` aus der `.env` unbedingt mitsichern. Ohne den Schlüssel sind
verschlüsselte Felder (2FA-Secrets, Recovery-Codes, verschlüsselte Sessions)
nicht wiederherstellbar.

## 2. Automatisches Backup

Der Scheduler erzeugt täglich ein Datenbank-Backup nach `BACKUP_PATH`
(Standard `storage/backups`). Status, Größe und Fehler sind im Admin-Bereich
unter Backups sichtbar; ein manueller Lauf ist dort ebenfalls möglich.

Die Backup-Dateien müssen zusätzlich **vom Server weg** gesichert werden
(z. B. auf den SFTP-Server unter `/mueller-holding/backups/` oder in die
bestehende Serversicherung). Ein Backup, das nur auf demselben Server liegt,
ist kein Backup.

## 3. Restore-Prozess (Datenbank + Dokumente)

1. Anwendung anhalten: `php artisan down`, Queue-Worker stoppen
   (`systemctl stop mhag-intranet-worker`).
2. Konsistenten Stand wählen: Datenbank-Dump und Dokumenten-Snapshot desselben
   Zeitpunkts (Zeitstempel vergleichen).
3. Datenbank wiederherstellen:
   ```bash
   mysql -u root -p -e "DROP DATABASE mhag_intranet; CREATE DATABASE mhag_intranet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p mhag_intranet < backup_JJJJMMTT_HHMMSS.sql
   ```
4. Dokumente wiederherstellen: SFTP-Zielverzeichnis bzw.
   `storage/app/documents` aus dem Snapshot zurückspielen.
5. `.env` prüfen (`APP_KEY` identisch mit dem Stand des Backups).
6. Konsistenz prüfen:
   ```bash
   php artisan migrate:status        # alle Migrationen "Ran"
   ```
   Anschließend im Admin-Bereich stichprobenartig Dokumente öffnen und die
   Integritätsprüfung (SHA-256) für einige Dokumente ausführen. Abweichungen
   deuten auf einen inkonsistenten Stand zwischen Datenbank und Dateiablage.
7. Anwendung starten: Worker starten, `php artisan up`.
8. Restore im Audit dokumentieren (der Anwendungsstart nach Restore wird
   protokolliert; zusätzlich manueller Vermerk mit Quelle und Zeitpunkt des
   verwendeten Backups).

## 4. Restore-Test

Mindestens quartalsweise einen Restore-Test auf einem separaten System
durchführen und das Ergebnis (Datum, verwendetes Backup, Befund) im
Admin-Bereich bzw. in der Betriebsdokumentation festhalten. Ein ungetestetes
Backup gilt als nicht vorhanden.
