# syntax=docker/dockerfile:1

# =============================================================================
# Mueller Holding AG Intranet, Containerabbild
# =============================================================================
# Aus einem Bauzusammenhang entstehen zwei Abbilder:
#
#   --target app   PHP-FPM 8.4 mit dem vollstaendigen Anwendungscode. Wird fuer
#                  die Anwendung, die Migration und den Zeitplan verwendet.
#   --target web   Nginx mit den statischen Dateien aus public/. Gibt PHP-
#                  Anfragen an den app-Container weiter.
#
# Beide Abbilder legen die Anwendung unter /var/www/html ab. Nginx uebergibt
# SCRIPT_FILENAME als absoluten Pfad; PHP-FPM loest diesen Pfad im eigenen
# Dateisystem auf, deshalb muessen beide Abbilder denselben Pfad verwenden.
#
# Bau (auf dem Server, ohne Registry):
#   docker build --target app -t local/mhag-intranet-app:<tag> .
#   docker build --target web -t local/mhag-intranet-web:<tag> .

# -----------------------------------------------------------------------------
# Grundlage: PHP 8.4 FPM mit den benoetigten Erweiterungen
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

# mariadb-client liefert mariadb-dump fuer das taegliche Datenbank-Backup
# (App\Services\BackupService). su-exec senkt im Startskript die Rechte.
# tzdata wird fuer APP_TIMEZONE=Europe/Berlin benoetigt.
RUN set -eux; \
    apk add --no-cache \
        freetype \
        icu-data-full \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libzip \
        mariadb-client \
        su-exec \
        tzdata; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip; \
    apk del .build-deps; \
    php -m

COPY infra/php/intranet.ini /usr/local/etc/php/conf.d/zz-intranet.ini
COPY infra/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-intranet.conf

WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# Abhaengigkeiten: Composer ohne Entwicklungspakete
# -----------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Zuerst nur die Abhaengigkeitsdateien, damit die Ebene bei reinen
# Codeaenderungen erhalten bleibt.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . ./
RUN set -eux; \
    composer dump-autoload --no-dev --optimize; \
    php artisan package:discover --ansi

# -----------------------------------------------------------------------------
# Anwendung: PHP-FPM
# -----------------------------------------------------------------------------
FROM base AS app

COPY --from=vendor /var/www/html ./
COPY infra/entrypoint.sh /usr/local/bin/intranet-entrypoint
COPY infra/wait-for-db.php /usr/local/bin/intranet-wait-for-db.php

RUN set -eux; \
    chmod +x /usr/local/bin/intranet-entrypoint; \
    mkdir -p \
        storage/app/documents \
        storage/app/private \
        storage/app/public \
        storage/backups \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug=rwX,o= storage bootstrap/cache

# Kein eigener Gesundheitstest: der web-Container prueft mit /up die gesamte
# Kette aus Nginx, PHP-FPM und Anwendung.
ENTRYPOINT ["intranet-entrypoint"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Auslieferung: Nginx mit den statischen Dateien
# -----------------------------------------------------------------------------
FROM nginx:stable-alpine AS web

COPY infra/nginx/intranet.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /var/www/html/public /var/www/html/public

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1
