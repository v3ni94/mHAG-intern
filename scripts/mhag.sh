#!/usr/bin/env sh
# Kurzform fuer docker compose mit den Dateien dieses Stapels.
# Auf dem Server aus dem Projektverzeichnis aufrufen, zum Beispiel:
#   scripts/mhag.sh ps
#   scripts/mhag.sh logs -f app
#   scripts/mhag.sh exec app php artisan tinker
set -eu
cd "$(dirname "$0")/.."
INTRANET_IMAGE_TAG="${INTRANET_IMAGE_TAG:-$(cat .deployed-tag 2>/dev/null || echo latest)}"
export INTRANET_IMAGE_TAG
exec docker compose -p mhag-intranet --env-file .env.prod \
    -f infra/compose.yaml -f infra/compose.prod.yaml "$@"
