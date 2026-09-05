# Deployment – intranet.mueller-holding.ag

Betrieb der Anwendung auf einem Linux-Server (Debian/Ubuntu) mit Nginx,
PHP-FPM 8.4, MariaDB 10.11/11, Cron und Queue-Worker.

## 1. Systemvoraussetzungen

- PHP 8.4 (FPM) mit Erweiterungen: `bcmath`, `pdo_mysql`, `mbstring`, `intl`,
  `gd`, `zip`, `curl`, `dom`, `openssl`
- MariaDB 10.11 oder 11 (InnoDB, utf8mb4)
- Nginx mit gültigem TLS-Zertifikat für `intranet.mueller-holding.ag`
- Composer 2
- Optional: SFTP-Zielserver für die Dokumentenablage

## 2. Installation

```bash
cd /var/www
git clone <repo-url> intranet && cd intranet
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

`.env` für Produktion (Auszug, Werte einsetzen):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://intranet.mueller-holding.ag
APP_TIMEZONE=Europe/Berlin

DB_CONNECTION=mariadb
DB_DATABASE=mhag_intranet
DB_USERNAME=mhag_intranet
DB_PASSWORD=<stark, aus Passwortverwaltung>

SESSION_DOMAIN=intranet.mueller-holding.ag
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true

DOCUMENT_DISK=sftp
SFTP_HOST=<sftp-host>
SFTP_USERNAME=<benutzer>
SFTP_PRIVATE_KEY=/pfad/zum/key
SFTP_ROOT_PATH=/mueller-holding
SFTP_HOST_FINGERPRINT=<ssh-keyscan-Fingerprint>

MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
```

Datenbank anlegen und migrieren:

```sql
CREATE DATABASE mhag_intranet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

Nach dem ersten Login als Administrator: Seed-Passwort ändern und 2FA einrichten
(wird für die Rolle Administrator erzwungen).

## 3. Nginx (HTTPS erzwingen)

```nginx
server {
    listen 80;
    server_name intranet.mueller-holding.ag;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name intranet.mueller-holding.ag;
    root /var/www/intranet/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/intranet.mueller-holding.ag/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/intranet.mueller-holding.ag/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;

    client_max_body_size 60m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Die Anwendung erzwingt HTTPS zusätzlich anwendungsseitig
(`URL::forceScheme('https')` in Produktion); alle generierten Links basieren auf
`APP_URL`.

## 4. Cron (Scheduler)

```cron
* * * * * www-data cd /var/www/intranet && php artisan schedule:run >> /dev/null 2>&1
```

Geplante Aufgaben: tägliche Fälligkeits- und Ablaufprüfung
(Benachrichtigungen, Wiedervorlagen) sowie tägliches Backup.

## 5. Queue-Worker (systemd)

`/etc/systemd/system/mhag-intranet-worker.service`:

```ini
[Unit]
Description=MHAG Intranet Queue Worker
After=network.target mariadb.service

[Service]
User=www-data
Restart=always
RestartSec=3
ExecStart=/usr/bin/php /var/www/intranet/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now mhag-intranet-worker
```

Über die Queue laufen E-Mails, PDF-Erzeugung, große Reports und
SFTP-Operationen, sofern als Jobs implementiert.

## 6. Updates einspielen

```bash
cd /var/www/intranet
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
systemctl restart mhag-intranet-worker
php artisan up
```

## 6b. Deployment über GitHub Actions (IONOS, ohne SSH)

`.github/workflows/deploy.yml` überträgt die Anwendung per SFTP mit `lftp`.
Die Bibliotheken werden auf dem Läufer mit PHP 8.4 gebaut, passend zur
Laufzeitfassung des Webspace.

### Warum überhaupt

Am 05.09.2026 hat ein Upload über ein FTP-Programm das Verzeichnis `public/`
ersetzt statt ergänzt und damit `index.php` und `.htaccess` entfernt. Die
Anwendung war nicht mehr erreichbar. Dieser Ablauf schließt genau das aus: er
überträgt nur geänderte Dateien und löscht nie etwas.

### Auslöser

| Auslöser | Zustand |
| --- | --- |
| Manuell, "Run workflow" | sofort nutzbar, Branch frei wählbar |
| Push auf `main` oder `master` | erst wenn ein solcher Branch besteht |

Am Ursprung besteht derzeit nur der Arbeitszweig, und er ist zugleich der
Standardbranch. Der automatische Auslöser greift deshalb nicht. Das ist
Absicht: ein Ablauf, der jeden Commit eines Arbeitszweigs auf das
Produktivsystem schreibt, wäre gefährlich. Wer automatisch ausliefern will,
legt `main` an und führt dorthin zusammen, was live gehen soll.

### Secrets hinterlegen

Im Repository unter **Settings → Secrets and variables → Actions → New
repository secret**:

| Secret | Wert | Woher |
| --- | --- | --- |
| `SFTP_HOST` | Serveradresse des Webspace | eigene `.env` (`SFTP_HOST`) oder IONOS-Panel |
| `SFTP_USERNAME` | SFTP-Benutzer | ebenda |
| `SFTP_PASSWORD` | Kennwort des SFTP-Benutzers | IONOS-Panel |
| `SFTP_TARGET` | `/homepages/43/d866575280/htdocs/Mueller-HoldingAG/Intranet` | Pfad der Anwendung |
| `SFTP_PORT` | optional, Vorgabe 22 | |
| `HEALTHCHECK_URL` | optional, z. B. `https://intranet.mueller-holding.ag/login` | |

Der Ablauf prüft die Secrets als ersten Schritt und benennt fehlende, statt
mitten in der Übertragung abzubrechen.

### Freigabe durch eine zweite Person

Der Ablauf läuft in der Umgebung `produktion`. Unter **Settings →
Environments → produktion → Required reviewers** lässt sich eine Freigabe
erzwingen. Bei einem Ablauf, der direkt auf das Produktivsystem schreibt, ist
das zu empfehlen.

### Was übertragen wird

Nicht übertragen werden `.env` und `.env.*`, `storage/` (Dokumente,
Protokolle, Sitzungen), `bootstrap/cache/`, die Entwicklungsdatenbank sowie
`tests/`, `tools/`, `docs/`, `node_modules/` und `CLAUDE.md`. Übertragen
werden `app/`, `config/`, `routes/`, `resources/`, `public/`, `vendor/`,
`database/migrations/`, `bootstrap/app.php` und `cron.php`.

Die Ausschlussmuster sind mit lftp gegen ein Testverzeichnis geprüft.

Gespiegelt wird **ohne** `--delete`: auf dem Server nicht mehr benötigte
Dateien bleiben liegen und sind von Hand zu entfernen. Das ist bewusst so
gewählt, damit ein fehlerhaftes Muster keine Produktivdaten löscht.

### Nach der Übertragung

Der Ablauf entfernt `bootstrap/cache/config.php`, `bootstrap/cache/events.php`,
`bootstrap/cache/routes*.php` und die vorkompilierten Oberflächen. Ein eigener
Schritt weist nach, dass sie tatsächlich weg sind, und lässt den Ablauf sonst
scheitern. Ohne diesen Schritt liefe die Anwendung mit dem vorherigen Stand
weiter.

Zwei Dinge erledigt der Ablauf **nicht**:

1. **Datenbankänderungen.** Bringt eine Lieferung Migrationen mit, ist
   `tools/web-setup/update.php` mit Zugriffsschlüssel einzeln nach `public/`
   zu laden, auszuführen und danach zu löschen. `tools/` wird absichtlich
   nicht übertragen.
2. **Erneutes Zwischenspeichern.** Die Anwendung läuft nach der Übertragung
   ohne Zwischenspeicher, also langsamer. Das Optimieren erfolgt über
   dasselbe Werkzeug.

Die Zusammenfassung jedes Laufs weist auf beides hin.

## 6c. Nach einem Wechsel des Anwendungsschlüssels

Ein Wechsel von `APP_KEY` macht alle mit dem alten Schlüssel verschlüsselten
Felder unlesbar. In dieser Anwendung sind das ausschließlich
`users.two_factor_secret` und `users.two_factor_recovery_codes`. Fachdaten,
Beträge und Dokumente liegen unverschlüsselt und sind nicht betroffen.

Zwei Wege:

1. **Verlustfrei, wenn der alte Schlüssel noch bekannt ist.** In der `.env`
   `APP_PREVIOUS_KEYS=base64:<alter Schlüssel>` setzen (mehrere mit Komma
   getrennt). Laravel liest die Felder damit weiter und legt sie beim nächsten
   Schreiben mit dem neuen Schlüssel ab. Es ist nichts zurückzusetzen.
2. **Zurücksetzen, wenn der alte Schlüssel verloren ist.** Je Benutzer über
   Administration, Benutzer, "2FA zurücksetzen", oder gesammelt über
   `tools/web-setup/notfall.php`. Jeder Vorgang wird im Prüfpfad festgehalten.
   Die Benutzer richten die Zwei-Faktor-Anmeldung danach neu ein.

### Warum ein unlesbares Feld früher die ganze Anwendung ausgeschaltet hat

Der Cast `encrypted` entschlüsselt beim Lesen. Eine `DecryptException` wurde
nirgends abgefangen, deshalb genügte ein einziger nicht lesbarer Datensatz, um
die Anmeldung, die Benutzerverwaltung und über die Middleware für die
Zwei-Faktor-Pflicht jede weitere Seite mit einem Serverfehler 500 auszuschalten.

Zwei Punkte sind dabei nicht offensichtlich:

- **Auch das Schreiben bricht ab.** `Model::save()` vergleicht den neuen mit dem
  bisherigen Wert (`HasAttributes::originalIsEquivalent`) und entschlüsselt dazu
  beide. Selbst die Neueinrichtung, also der Weg aus dem Zustand heraus, schlug
  daran fehl. Ein Leeren des Feldes ist dagegen unkritisch, weil der Vergleich
  bei einem neuen Wert `null` vorher abbricht. `User::saveTwoFactorFields()`
  nutzt genau das.
- **Der zweite Faktor bleibt bestehen.** `hasTwoFactorEnabled()` liefert auch bei
  unlesbarem Geheimnis `true`. Andernfalls käme man durch Austausch des
  Anwendungsschlüssels ohne zweiten Faktor hinein.

## 7. Überwachung

- Admin-Bereich: Systemstatus (DB, Queue, Backups, SFTP, fehlgeschlagene Logins,
  Neuberechnungsfehler)
- Logs: `storage/logs/laravel-*.log` (daily)
- SFTP-Verbindungstest im Admin-Bereich
