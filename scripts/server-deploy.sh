#!/usr/bin/env bash
# Deployment des Intranets. Laeuft AUF dem Betreiberserver im Verzeichnis des
# Klons (Vorgabe /opt/mhag-intranet).
#
#   scripts/server-deploy.sh <tag-oder-branch-oder-commit>
#
# Ablauf: Stand holen, Abbilder bauen, Datenbank sichern, Migration, Start.
# Bricht ein Schritt ab, endet das Skript; der bisherige Stand laeuft weiter,
# solange die Migration noch nicht gelaufen ist.
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

# Werte fuer Bau und Sicherung. Die Datei enthaelt Geheimnisse, deshalb wird
# sie nur gelesen und nichts davon ausgegeben.
set -a
# shellcheck disable=SC1090
. "./$ENV_FILE"
set +a

REGISTRY="${INTRANET_IMAGE_REGISTRY:-local}"
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
COMMIT="$(git rev-parse --short HEAD)"
echo "server-deploy: HEAD steht auf $COMMIT"

# Dateien muessen fuer die Benutzer in den Abbildern lesbar sein. .env.prod
# ist bewusst nicht in der Liste und behaelt seine Rechte 600.
chmod -R u=rwX,go=rX app artisan bootstrap config database infra public \
    resources routes scripts composer.json composer.lock

# ---------------------------------------------------------------------------
# 2. Abbilder bauen
# ---------------------------------------------------------------------------
TAG="$REF"
export INTRANET_IMAGE_TAG="$TAG"
export INTRANET_IMAGE_REGISTRY="$REGISTRY"

echo "server-deploy: Abbilder bauen ($REGISTRY/mhag-intranet-*:$TAG)"
docker build --target app -t "$REGISTRY/mhag-intranet-app:$TAG" .
docker build --target web -t "$REGISTRY/mhag-intranet-web:$TAG" .

# ---------------------------------------------------------------------------
# 3. Datenbank sichern, bevor die Migration laeuft
# ---------------------------------------------------------------------------
if [[ -n "$("${COMPOSE[@]}" ps -q db 2>/dev/null || true)" ]]; then
    mkdir -p backups
    STAMP="$(date +%Y-%m-%d_%H%M%S)"
    DUMP="backups/mhag-intranet_${STAMP}_vor_${COMMIT}.sql.gz"
    echo "server-deploy: Datenbank sichern nach $DUMP"
    # MYSQL_PWD statt -p, damit das Kennwort nicht in der Prozessliste steht.
    "${COMPOSE[@]}" exec -T -e MYSQL_PWD="${DB_ROOT_PASSWORD}" db \
        mariadb-dump -u root --single-transaction --routines --events \
        --default-character-set=utf8mb4 "${DB_DATABASE}" | gzip > "$DUMP"
    chmod 600 "$DUMP"
    # Aeltere Sicherungen als 30 Tage entfernen.
    find backups -name 'mhag-intranet_*.sql.gz' -mtime +30 -delete
else
    echo "server-deploy: kein laufender Datenbankdienst, erste Einrichtung"
fi

# ---------------------------------------------------------------------------
# 4. Migration und Start
# ---------------------------------------------------------------------------
echo "server-deploy: Migration"
"${COMPOSE[@]}" run --rm migrate

echo "server-deploy: Dienste starten"
"${COMPOSE[@]}" up -d --remove-orphans

echo "$TAG" > .deployed-tag

# ---------------------------------------------------------------------------
# 5. Nachweis
# ---------------------------------------------------------------------------
echo "server-deploy: Bereitschaft pruefen"
for _ in $(seq 1 30); do
    if "${COMPOSE[@]}" exec -T web wget -qO- http://127.0.0.1/up >/dev/null 2>&1; then
        echo "server-deploy: $TAG ($COMMIT) laeuft, /up antwortet"
        exit 0
    fi
    sleep 2
done

echo "server-deploy: /up hat innerhalb von 60 Sekunden nicht geantwortet" >&2
"${COMPOSE[@]}" ps
exit 1
