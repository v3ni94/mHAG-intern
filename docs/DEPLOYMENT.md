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

## 7. Überwachung

- Admin-Bereich: Systemstatus (DB, Queue, Backups, SFTP, fehlgeschlagene Logins,
  Neuberechnungsfehler)
- Logs: `storage/logs/laravel-*.log` (daily)
- SFTP-Verbindungstest im Admin-Bereich
