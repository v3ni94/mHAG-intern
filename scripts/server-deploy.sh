#!/usr/bin/env bash
# Deployment des Intranets. Laeuft AUF dem Betreiberserver im Verzeichnis des
# Klons (Vorgabe /opt/mhag-intranet).
#
#   scripts/server-deploy.sh <tag-oder-branch-oder-commit>
#
# Ablauf: Sperre setzen, Stand holen, Abbilder bauen, Datenbank sichern,
# Migration, Start, Bereitschaft pruefen.
#
# Die Abbilder werden mit dem kurzen Commit gekennzeichnet, nicht mit dem
# Namen des Stands: ein Branchname wie "claude/den-master-prompt-finish"
# enthaelt einen Schraegstrich und ist als Docker-Tag unzulaessig. Der Commit
# ist zugleich eindeutig, so dass der vorherige Stand als Abbild erhalten
# bleibt und ein Rueckfall ohne neuen Bau moeglich ist.
set -euo pipefail

REF="${1:-}"
if [[ -z "$REF" ]]; then
    echo "server-deploy: Tag, Branch oder Commit als erstes Argument angeben" >&2
    exit 2
fi
if [[ ! "$REF" =~ ^[A-Za-z0-9._/-]+$ ]]; then
    echo "server-deploy: unzulaessige Zeichen in '$REF'" >&2
    exit 2
fi

cd "$(dirname "$0")/.."
umask 022

ENV_FILE=".env.prod"
if [[ ! -f "$ENV_FILE" ]]; then
    echo "server-deploy: $ENV_FILE fehlt (Vorlage infra/env.prod.example)" >&2
    exit 2
fi

# Zwei gleichzeitige Deployments wuerden im selben Arbeitsverzeichnis bauen und
# sich den Stand unter den Haenden wegziehen.
exec 9>".deploy.lock"
if ! flock -n 9; then
    echo "server-deploy: es laeuft bereits ein Deployment in diesem Verzeichnis" >&2
    exit 3
fi

# Einzelnen Wert aus der .env.prod lesen, OHNE die Datei auszufuehren. Ein
# "source" wuerde Kennwoerter mit Sonderzeichen anders auslegen als der Leser
# von docker compose und im schlimmsten Fall Befehle ausfuehren.
env_wert() {
    local name="$1" vorgabe="${2:-}" zeile wert
    zeile="$(grep -m1 -E "^[[:space:]]*${name}=" "$ENV_FILE" || true)"
    if [[ -z "$zeile" ]]; then
        printf '%s' "$vorgabe"
        return 0
    fi
    wert="${zeile#*=}"
    # Umschliessende Anfuehrungszeichen entfernen, wie docker compose es tut.
    if [[ "$wert" =~ ^\"(.*)\"$ ]] || [[ "$wert" =~ ^\'(.*)\'$ ]]; then
        wert="${BASH_REMATCH[1]}"
    fi
    printf '%s' "$wert"
}

REGISTRY="$(env_wert INTRANET_IMAGE_REGISTRY local)"
PROJECT=mhag-intranet
COMPOSE=(docker compose -p "$PROJECT" --env-file "$ENV_FILE"
         -f infra/compose.yaml -f infra/compose.prod.yaml)

# ---------------------------------------------------------------------------
# 1. Stand holen
# ---------------------------------------------------------------------------
echo "server-deploy: Stand $REF holen"
git fetch --quiet --prune --tags origin

if git rev-parse --verify --quiet "refs/tags/$REF^{commit}" >/dev/null; then
    TARGET="refs/tags/$REF"
elif git rev-parse --verify --quiet "refs/remotes/origin/$REF^{commit}" >/dev/null; then
    TARGET="refs/remotes/origin/$REF"
elif git rev-parse --verify --quiet "$REF^{commit}" >/dev/null; then
    TARGET="$REF"
else
    echo "server-deploy: '$REF' ist im Klon nicht auffindbar" >&2
    exit 2
fi

git checkout --quiet --force --detach "$TARGET"
COMMIT="$(git rev-parse --short=12 HEAD)"
TAG="$COMMIT"
echo "server-deploy: HEAD steht auf $COMMIT (aus $REF)"

# Dateien muessen fuer die Benutzer in den Abbildern lesbar sein. .env.prod
# ist bewusst nicht in der Liste und behaelt seine Rechte 600.
chmod -R u=rwX,go=rX app artisan bootstrap config database infra public \
    resources routes scripts composer.json composer.lock

# ---------------------------------------------------------------------------
# 2. Abbilder bauen
# ---------------------------------------------------------------------------
export INTRANET_IMAGE_TAG="$TAG"
export INTRANET_IMAGE_REGISTRY="$REGISTRY"

echo "server-deploy: Abbilder bauen ($REGISTRY/mhag-intranet-*:$TAG)"
docker build --target app -t "$REGISTRY/mhag-intranet-app:$TAG" .
docker build --target web -t "$REGISTRY/mhag-intranet-web:$TAG" .

# ---------------------------------------------------------------------------
# 3. Datenbank sichern, bevor die Migration laeuft
# ---------------------------------------------------------------------------
# Kennwort und Datenbankname stehen in der Umgebung des db-Containers. Sie
# werden dort gelesen und erscheinen dadurch weder in der Prozessliste des
# Servers noch in der Ausgabe dieses Skripts.
# Ohne Zeilenumbruch: die Zeichenkette wird von "sh -c" ausgewertet, ein
# Umbruch waere dort ein Befehlsende.
DUMP_BEFEHL='MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-dump -u root'
DUMP_BEFEHL+=' --single-transaction --routines --events'
DUMP_BEFEHL+=' --default-character-set=utf8mb4'
# --add-drop-database: beim Einspielen wird die Datenbank verworfen und neu
# angelegt. Ohne das blieben Tabellen einer halb gelaufenen Migration stehen,
# die in der Sicherung gar nicht vorkommen.
DUMP_BEFEHL+=' --add-drop-database --databases "$MARIADB_DATABASE"'

ZAEHL_BEFEHL='MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -u root -N -B'
ZAEHL_BEFEHL+=' -D "$MARIADB_DATABASE"'
ZAEHL_BEFEHL+=' -e "select count(*) from information_schema.tables'
ZAEHL_BEFEHL+=' where table_schema = database();"'

# ps -aq statt ps -q: ein nur gestoppter Dienst wuerde sonst uebersehen, die
# Migration liefe dann ohne Sicherung auf vorhandene Daten.
if [[ -n "$("${COMPOSE[@]}" ps -aq db 2>/dev/null || true)" ]]; then
    echo "server-deploy: Datenbank starten und auf Bereitschaft warten"
    "${COMPOSE[@]}" up -d db

    bereit=0
    for _ in $(seq 1 60); do
        if "${COMPOSE[@]}" exec -T db sh -c \
             'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-admin ping -u root --silent' \
             >/dev/null 2>&1; then
            bereit=1
            break
        fi
        sleep 2
    done

    if [[ "$bereit" -ne 1 ]]; then
        echo "server-deploy: Datenbank wurde nicht bereit, Abbruch vor der Migration" >&2
        exit 1
    fi

    TABELLEN="$("${COMPOSE[@]}" exec -T db sh -c "$ZAEHL_BEFEHL" 2>/dev/null | tr -d '\r' | tail -n1)"
    TABELLEN="${TABELLEN:-0}"

    if [[ "$TABELLEN" =~ ^[0-9]+$ ]] && (( TABELLEN > 0 )); then
        mkdir -p backups
        chmod 700 backups
        STAMP="$(date +%Y-%m-%d_%H%M%S)"
        DUMP="backups/mhag-intranet_${STAMP}_vor_${COMMIT}.sql.gz"

        echo "server-deploy: $TABELLEN Tabellen vorhanden, Sicherung nach $DUMP"
        (
            # Unter einer eigenen Maske, damit die Datei nie kurzzeitig fuer
            # andere lesbar ist.
            umask 077
            # Erst unter .part schreiben und nur bei Erfolg umbenennen: eine
            # abgebrochene Sicherung ist sonst von einer vollstaendigen nicht
            # zu unterscheiden.
            "${COMPOSE[@]}" exec -T db sh -c "$DUMP_BEFEHL" | gzip > "${DUMP}.part"
        )
        mv "${DUMP}.part" "$DUMP"

        # Pruefung auf Vollstaendigkeit: gueltiges Archiv und der Schlussvermerk
        # von mariadb-dump. Die Ausgabe wird vollstaendig gelesen und erst
        # danach durchsucht, damit die Leitung nicht vorzeitig schliesst und
        # einen Fehler vortaeuscht.
        if ! gzip -t "$DUMP" 2>/dev/null; then
            echo "server-deploy: die Sicherung $DUMP ist kein gueltiges Archiv, Abbruch" >&2
            exit 1
        fi
        ENDE="$(gunzip -c "$DUMP" | tail -n 5)"
        if ! printf '%s' "$ENDE" | grep -q 'Dump completed'; then
            echo "server-deploy: die Sicherung $DUMP endet ohne Schlussvermerk, Abbruch" >&2
            exit 1
        fi

        # Aeltere Sicherungen als 30 Tage entfernen.
        find backups -name 'mhag-intranet_*.sql.gz' -mtime +30 -delete
    else
        echo "server-deploy: Datenbank ist leer, keine Sicherung noetig"
    fi
else
    echo "server-deploy: kein Datenbankdienst vorhanden, erste Einrichtung"
fi

# ---------------------------------------------------------------------------
# 4. Migration und Start
# ---------------------------------------------------------------------------
echo "server-deploy: Migration"
"${COMPOSE[@]}" run --rm migrate

echo "server-deploy: Dienste starten"
"${COMPOSE[@]}" up -d --remove-orphans

printf '%s\n' "$TAG" > .deployed-tag
printf '%s\n' "$REF" > .deployed-ref

# ---------------------------------------------------------------------------
# 5. Nachweis
# ---------------------------------------------------------------------------
echo "server-deploy: Bereitschaft pruefen"
for _ in $(seq 1 30); do
    if "${COMPOSE[@]}" exec -T web wget -qO- http://127.0.0.1/up >/dev/null 2>&1; then
        echo "server-deploy: $REF ($COMMIT) laeuft, /up antwortet"
        exit 0
    fi
    sleep 2
done

cat >&2 <<HINWEIS
server-deploy: /up hat innerhalb von 60 Sekunden nicht geantwortet.

WICHTIG: Migration und Start sind bereits gelaufen, der neue Stand $COMMIT ist
produktiv. Dies ist kein Abbruch vor der Aenderung, sondern ein fehlender
Nachweis danach. Naechste Schritte:
  scripts/mhag.sh ps
  scripts/mhag.sh logs --tail 100 app
  scripts/mhag.sh logs --tail 50 web
Rueckfall auf den vorherigen Stand: siehe docs/DEPLOYMENT-SERVER.md, Abschnitt 9.
HINWEIS
"${COMPOSE[@]}" ps
exit 1
