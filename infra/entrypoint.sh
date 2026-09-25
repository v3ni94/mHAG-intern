#!/bin/sh
# Startskript aller PHP-Container (Anwendung, Migration, Zeitplan).
#
# Aufgaben:
#   1. Verzeichnisse anlegen, die in einem eingehaengten Volume fehlen koennen
#   2. Rechte auf storage und bootstrap/cache setzen
#   3. Warten, bis die Datenbank Verbindungen annimmt
#   4. Zwischenspeicher der Anwendung neu aufbauen
#   5. Befehl mit den Rechten von www-data ausfuehren
#
# Bricht ein Schritt ab, endet der Container mit einem Fehler. Ein Container,
# der mit unvollstaendiger Konfiguration startet, ist schwerer zu erkennen als
# einer, der gar nicht startet.
#
# Alle eigenen Ausgaben gehen auf die Standardfehlerausgabe. Damit bleibt die
# Standardausgabe einmaliger Aufrufe wie
# "docker compose run --rm app php artisan schedule:list" auswertbar.
set -eu

cd /var/www/html

mkdir -p \
    storage/app/documents \
    storage/app/private \
    storage/app/public \
    storage/backups \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

php /usr/local/bin/intranet-wait-for-db.php

# Zwischenspeicher immer neu aufbauen. Ein Abbild kann mit geaenderter
# Umgebung starten; ein alter Zwischenspeicher wuerde die alten Werte
# festschreiben. Genau dieser Fall hat am 24.09.2026 auf dem Webspace zu
# einem HTTP 500 gefuehrt.
su-exec www-data php artisan config:clear >&2
su-exec www-data php artisan config:cache >&2
su-exec www-data php artisan route:cache >&2
su-exec www-data php artisan view:cache >&2
su-exec www-data php artisan event:cache >&2

# php-fpm laeuft als Hauptprozess unter root und senkt die Rechte seiner
# Arbeitsprozesse selbst auf www-data (Vorgabe des Grundabbilds). Alle
# anderen Befehle laufen direkt als www-data.
if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

exec su-exec www-data "$@"
