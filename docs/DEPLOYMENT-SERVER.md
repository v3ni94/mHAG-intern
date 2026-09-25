# Betrieb auf dem eigenen Server (Container hinter Traefik)

Stand: 25.09.2026

Diese Anleitung beschreibt den Betrieb des Intranets auf dem Server der
Müller Holding AG, auf dem bereits das CRM läuft. Sie ersetzt für den
Zielbetrieb die beiden bisherigen Wege:

- `docs/DEPLOYMENT.md`: Nginx und PHP-FPM direkt auf einem Linux-Server
- `.github/workflows/deploy.yml`: Übertragung per SFTP auf den IONOS-Webspace

Der Webspace bleibt so lange in Betrieb, bis der Server abgenommen ist. Erst
danach wird die Domain umgestellt.

## 1. Warum Container

Auf dem Server läuft das CRM als Docker-Stapel hinter einem vorhandenen
Traefik, der die Zertifikate von Let's Encrypt bezieht. Das Intranet nutzt
denselben Weg: ein eigener Stapel, der sich mit Beschriftungen in das
vorhandene Traefik-Netz einhängt. Damit gilt:

- keine zweite Zertifikatsverwaltung, keine zweite Proxy-Konfiguration
- PHP 8.4 und alle Erweiterungen liegen im Abbild, nicht in der
  Systemverwaltung des Servers, und können das CRM nicht stören
- der Zeitplan (`php artisan schedule:work`) läuft als eigener Dienst; der
  Cron-Eintrag des Webspace entfällt
- die Datenbank ist von außen nicht erreichbar, es werden keine Ports
  veröffentlicht

Eine Zusammenführung der beiden Anwendungen in einen Programmcode ist nicht
vorgesehen: das CRM ist Python und TypeScript auf PostgreSQL, das Intranet ist
Laravel auf MariaDB. Verbunden werden sie über die zentrale Anmeldung
(nächster Arbeitsschritt), nicht über gemeinsamen Code.

## 2. Bestandteile im Repository

| Datei | Zweck |
| --- | --- |
| `Dockerfile` | zwei Ziele: `app` (PHP-FPM 8.4) und `web` (Nginx) |
| `infra/compose.yaml` | Grundstapel: db, migrate, app, scheduler, web |
| `infra/compose.prod.yaml` | Traefik-Beschriftungen, Neustartverhalten, Protokollgrenzen |
| `infra/env.prod.example` | Vorlage für `.env.prod` auf dem Server |
| `infra/entrypoint.sh` | Startskript der PHP-Container |
| `infra/wait-for-db.php` | wartet beim Start auf die Datenbank |
| `infra/nginx/intranet.conf` | Nginx vor PHP-FPM |
| `infra/php/intranet.ini` | PHP-Einstellungen für den Betrieb |
| `infra/php/fpm-pool.conf` | Prozesspool von PHP-FPM |
| `scripts/server-deploy.sh` | Ablauf auf dem Server |
| `scripts/deploy.sh` | Aufruf über SSH von außen |
| `scripts/mhag.sh` | Kurzform für `docker compose` auf dem Server |
| `scripts/ssh-forced-command.sh` | begrenzt den Deploy-Schlüssel auf genau einen Befehl |
| `.github/workflows/deploy-server.yml` | Auslösung über GitHub Actions |

## 3. Voraussetzungen

Auf dem Server:

1. Docker Engine mit Compose-Plugin, `git`.
2. Das Traefik-Netz des CRM. Name ablesen mit `docker network ls`; im CRM
   steht er in `.env.prod` unter `TRAEFIK_NETWORK`.
3. Name des Zertifikatsauflösers und des Eingangs aus derselben Datei
   (`TRAEFIK_CERTRESOLVER`, `TRAEFIK_ENTRYPOINT`).

Im Netz:

4. DNS-Eintrag A und AAAA für `intern.mueller-holding.ag` auf die Adresse des
   Servers. Ports 80 und 443 müssen erreichbar sein, sonst stellt Let's
   Encrypt kein Zertifikat aus.

Im Repository:

5. Deploy-Key mit Leserecht für `v3ni94/mHAG-intern`, damit der Server den
   Stand holen kann.

## 4. Einmalige Einrichtung

```bash
sudo mkdir -p /opt/mhag-intranet
sudo chown "$USER" /opt/mhag-intranet
git clone git@github.com:v3ni94/mHAG-intern.git /opt/mhag-intranet
cd /opt/mhag-intranet

cp infra/env.prod.example .env.prod
chmod 600 .env.prod
```

`.env.prod` vollständig ausfüllen. Jeder Wert `aendern` ist zu ersetzen.
Besonders zu beachten:

- `TRAEFIK_NETWORK`, `TRAEFIK_ENTRYPOINT`, `TRAEFIK_CERTRESOLVER` aus der
  `.env.prod` des CRM übernehmen
- `DB_PASSWORD` und `DB_ROOT_PASSWORD` aus der Passwortverwaltung, nicht von
  Hand ausgedacht
- `APP_KEY`: der Schlüssel entscheidet, ob die verschlüsselten Felder lesbar
  bleiben. Siehe Abschnitt 6.

## 5. Erstes Deployment

Aus dem Verzeichnis auf dem Server:

```bash
scripts/server-deploy.sh claude/den-master-prompt-finish-m84y3d
```

Das Skript holt den Stand, baut beide Abbilder, sichert die Datenbank, sofern
darin schon Tabellen stehen, führt die Migration aus und startet die Dienste.
Am Ende prüft es `/up` im Container.

Die Abbilder werden mit dem kurzen Commit gekennzeichnet, nicht mit dem Namen
des Stands: ein Branchname mit Schrägstrich ist als Docker-Kennzeichnung
unzulässig. Der verwendete Wert steht danach in `.deployed-tag`, der
zugehörige Name in `.deployed-ref`. Weil jeder Stand eine eigene Kennzeichnung
erhält, bleibt das Abbild des vorherigen Stands auf dem Server liegen und ein
Rückfall ist ohne neuen Bau möglich.

Von außen, zum Beispiel vom Arbeitsplatz:

```bash
DEPLOY_HOST=benutzer@server DEPLOY_PATH=/opt/mhag-intranet \
  INTRANET_REF=claude/den-master-prompt-finish-m84y3d \
  DEPLOY_CONFIRM=claude/den-master-prompt-finish-m84y3d \
  scripts/deploy.sh
```

Oder über GitHub Actions: Actions, "Deployment auf Server", Run workflow,
Stand und Bestätigung eintragen. Die dafür nötigen Secrets stehen im Kopf von
`.github/workflows/deploy-server.yml`.

## 6. Datenübernahme vom Webspace

Auf dem Webspace stehen bereits Daten. Die Übernahme entscheidet über den
Zeitpunkt der Umschaltung.

**Schlüssel zuerst.** Die Geheimnisse der Zwei-Faktor-Anmeldung sind mit dem
`APP_KEY` des Webspace verschlüsselt. Wird auf dem Server ein neuer Schlüssel
erzeugt, sind sie nicht mehr lesbar und jeder Benutzer muss seine
Zwei-Faktor-Anmeldung neu einrichten. Zwei Wege:

- **Empfohlen:** den bestehenden `APP_KEY` aus der `.env` des Webspace in die
  `.env.prod` des Servers übernehmen. Dann bleibt alles lesbar.
- Andernfalls neuen Schlüssel erzeugen und den alten unter
  `APP_PREVIOUS_KEYS` eintragen, mit Komma getrennt und ohne Leerzeichen.
  Die Felder bleiben dann lesbar. Wichtig: sie werden **nicht** von selbst auf
  den neuen Schlüssel umgeschrieben. Das geschieht erst, wenn der jeweilige
  Datensatz erneut gespeichert wird, beim Geheimnis der Zwei-Faktor-Anmeldung
  also erst, wenn der Benutzer sie neu einrichtet. Der alte Schlüssel muss
  deshalb dauerhaft eingetragen bleiben, solange noch ein Feld mit ihm
  verschlüsselt ist.

Ein neuer Schlüssel wird so erzeugt:

```bash
docker run --rm local/mhag-intranet-app:<tag> php artisan key:generate --show
```

**Datenbank.** Auf dem Webspace über phpMyAdmin einen Export der Datenbank
erstellen (Format SQL, mit Daten, Zeichensatz utf8mb4). Die Datei auf den
Server bringen und einspielen, bevor die Migration läuft:

```bash
scripts/mhag.sh up -d db
gunzip -c export.sql.gz | scripts/mhag.sh exec -T db sh -c \
  'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -u root "$MARIADB_DATABASE"'
scripts/server-deploy.sh <stand>
```

Das Kennwort steht bewusst nicht auf der Kommandozeile. Es wird aus der
Umgebung des Datenbankcontainers gelesen und erscheint dadurch weder in der
Prozessliste des Servers noch in der Verlaufsdatei der Shell.

Die Migration ist danach ein Aufsetzen auf den vorhandenen Stand, kein
Neuaufbau.

**Dokumente.** Liegt `DOCUMENT_DISK=local`, stehen die Dokumente auf dem
Webspace unter `storage/app/documents`. Verzeichnis herunterladen und in das
Volume des Stapels legen:

```bash
scripts/mhag.sh cp ./documents app:/var/www/html/storage/app/
scripts/mhag.sh exec app chown -R www-data:www-data /var/www/html/storage/app
```

Ist `DOCUMENT_DISK=sftp` eingestellt, entfällt dieser Schritt; die Dokumente
liegen dann ohnehin auf dem SFTP-Ziel.

**Reihenfolge der Umschaltung.** Damit zwischen Export und Umschaltung keine
Buchungen verloren gehen: Benutzer auf dem Webspace informieren, dort keine
Eingaben mehr vornehmen, Export ziehen, einspielen, prüfen, danach den
DNS-Eintrag umstellen. Erst wenn der Server abgenommen ist, wird der
Webspace abgeschaltet.

## 7. Sicherung

Zwei Ebenen, die sich ergänzen:

1. **Anwendung:** `app:backup-run` läuft täglich um 02:00 Uhr Ortszeit über
   den Zeitplan des Stapels und legt eine komprimierte Sicherung im Volume
   `sicherungen` ab. `mariadb-dump` liegt im Abbild. Sicherungen, die älter
   sind als `BACKUP_RETENTION_DAYS` (Vorgabe 30 Tage), werden nach jedem Lauf
   entfernt; dabei werden ausschließlich Dateien mit dem eigenen Namensmuster
   angefasst.
2. **Deployment:** `scripts/server-deploy.sh` sichert die Datenbank vor jeder
   Migration nach `backups/` im Projektverzeichnis, prüft die Sicherung auf
   Vollständigkeit und löscht dort Dateien, die älter als 30 Tage sind. Das
   Verzeichnis hat die Rechte 700, die Dateien 600.

**Zwei Punkte sind offen und vom Betreiber zu entscheiden.**

*Kopie außerhalb des Servers.* Eine Sicherung, die nur auf demselben Server
liegt, schützt nicht gegen dessen Ausfall, gegen ein verschlüsselndes
Schadprogramm und nicht gegen eine versehentliche Löschung des Volumes. Das
CRM löst das über `BACKUP_REMOTE` und ein age-Schlüsselpaar; derselbe Weg
lässt sich für das Intranet einrichten.

*Verschlüsselung.* Die Sicherungen liegen unverschlüsselt. Sie enthalten
personenbezogene Daten von Darlehensnehmern, Aktionären und Mitarbeitern.
Solange sie den Server nicht verlassen, ist das Risiko auf den Serverzugang
begrenzt; vor einer Kopie nach außen ist eine Verschlüsselung einzurichten.
Dies ist als datenschutzrechtlich zu bewertender Punkt festzuhalten.

## 8. Betrieb

```bash
scripts/mhag.sh ps                    # Zustand der Dienste
scripts/mhag.sh logs -f app           # Protokoll der Anwendung
scripts/mhag.sh logs -f scheduler     # Protokoll des Zeitplans
scripts/mhag.sh exec app php artisan about
scripts/mhag.sh exec app php artisan schedule:list
```

Der Dienst `scheduler` ersetzt den Cron-Eintrag. Ein Nachweis, dass der
Zeitplan läuft, steht im Protokoll dieses Dienstes.

## 9. Aktualisierung und Rückfall

Aktualisierung: dasselbe Kommando mit dem neuen Stand. Vor der Migration wird
gesichert.

Rückfall: den vorherigen Stand ausrollen. Ist eine Migration fehlgeschlagen,
zuerst die Sicherung aus `backups/` einspielen, danach den vorherigen Stand:

```bash
gunzip -c backups/<datei>.sql.gz | scripts/mhag.sh exec -T db sh -c \
  'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -u root'
scripts/server-deploy.sh <vorheriger-stand>
```

Die Sicherung enthält `DROP DATABASE` und legt die Datenbank vollständig neu
an. Tabellen, die eine zur Hälfte gelaufene Migration hinterlassen hat,
bleiben dadurch nicht stehen. Deshalb wird hier kein Datenbankname angegeben,
er steht in der Sicherung selbst.

## 10. Offene Punkte

| Punkt | Wer entscheidet |
| --- | --- |
| Adresse, Benutzer und Zugangsweg des Servers | Betreiber |
| Name des Traefik-Netzes, Eingang, Zertifikatsauflöser | aus der `.env.prod` des CRM ablesen |
| DNS-Eintrag für `intern.mueller-holding.ag` | Betreiber |
| Übernahme des bestehenden `APP_KEY` oder Neuvergabe | Betreiber, siehe Abschnitt 6 |
| Kopie der Sicherungen außerhalb des Servers | Betreiber, siehe Abschnitt 7 |
| Verschlüsselung der Sicherungen | Betreiber mit Datenschutz, siehe Abschnitt 7 |
| Rechte des Deploy-Benutzers, SSH-Schlüssel in GitHub | Betreiber, siehe Abschnitt 11 |
| Zeitpunkt der Abschaltung des Webspace | Betreiber |

Die Werte in dieser Anleitung, die mit spitzen Klammern stehen, sind
Platzhalter. Sie wurden nicht angenommen, sondern sind vom Betreiber
einzusetzen.

## 11. Rechte des Deploy-Benutzers

Dieser Abschnitt beschreibt ein Risiko, das mit dem Deployment einhergeht und
vor der Einrichtung bewusst entschieden werden sollte.

**Docker-Rechte sind faktisch Administratorrechte.** Wer den Docker-Dienst
ansprechen darf, kann einen Container mit dem gesamten Dateisystem des Servers
starten und ist damit dem Systemverwalter gleichgestellt. Ein eigener
Deploy-Benutzer ohne `sudo`, aber in der Gruppe `docker`, ist deshalb kein
eingeschränkter Zugang. Er erreicht auch die Daten und Sicherungen des CRM.
Das ist keine Besonderheit dieses Aufbaus, sondern gilt für jedes Deployment
mit Docker. Es sollte nur nicht unausgesprochen bleiben.

**Der Schlüssel in GitHub Actions öffnet genau diesen Zugang.** Wird der
Ablauf `deploy-server.yml` verwendet, liegt ein privater SSH-Schlüssel als
Secret im Repository. Wer die Verwaltung des Repositories übernimmt oder einen
Ablauf ändern kann, erreicht darüber den Server. Abwägung:

- **Dafür:** jede Lieferung ist ein Knopfdruck, der Ablauf ist nachvollziehbar
  protokolliert, und niemand muss Zugangsdaten auf einem Arbeitsplatz halten.
- **Dagegen:** der Zugang zum Produktivserver hängt an der Sicherheit eines
  GitHub-Kontos.

Empfohlene Eingrenzung, falls der Ablauf verwendet wird:

1. Eigener Benutzer `deploy-intranet` auf dem Server, nicht der persönliche
   Zugang und nicht `root`.
2. Der Schlüssel wird ausschließlich für diesen Zweck erzeugt und nirgends
   sonst verwendet.
3. In `~/.ssh/authorized_keys` wird der Schlüssel auf einen einzigen Befehl
   festgelegt:

   ```
   restrict,command="/opt/mhag-intranet/scripts/ssh-forced-command.sh" ssh-ed25519 AAAA... deploy-intranet
   ```

   `scripts/ssh-forced-command.sh` liegt im Repository. Es prüft den
   übermittelten Befehl gegen ein festes Muster und gegen das erlaubte
   Verzeichnis und weist alles andere ab. Damit ist über diesen Schlüssel
   keine freie Sitzung möglich; der Ablauf in GitHub Actions funktioniert
   unverändert, weil er genau diesen einen Befehl absetzt.

   Vor der Umstellung prüfen, solange eine zweite Sitzung offen ist:

   ```bash
   ssh -i <schluessel> deploy-intranet@<server> "cd '/opt/mhag-intranet' && scripts/server-deploy.sh 'v1.0.0'"
   ssh -i <schluessel> deploy-intranet@<server> "bash"   # muss abgewiesen werden
   ```
4. Schutz des Repositories: Zwei-Faktor-Anmeldung für alle Beteiligten,
   Umgebung `produktion` mit Freigabe, und eine regelmäßige Durchsicht, wer
   Schreibrecht hat.

Wird der Ablauf nicht verwendet, entfällt der Schlüssel in GitHub und die
Lieferung erfolgt von Hand nach Abschnitt 5. Die Entscheidung liegt beim
Betreiber und ist vor der Einrichtung zu treffen.
