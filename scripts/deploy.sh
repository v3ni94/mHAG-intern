#!/usr/bin/env bash
# Deployment von einem Arbeitsplatz oder aus GitHub Actions heraus.
# Ruft ueber SSH scripts/server-deploy.sh auf dem Server auf.
#
#   DEPLOY_HOST=benutzer@server DEPLOY_PATH=/opt/mhag-intranet \
#     INTRANET_REF=v1.2.0 DEPLOY_CONFIRM=v1.2.0 scripts/deploy.sh
#
# DEPLOY_CONFIRM verhindert ein versehentliches Deployment: der Wert muss mit
# INTRANET_REF uebereinstimmen.
set -euo pipefail

missing=()
for var in DEPLOY_HOST DEPLOY_PATH INTRANET_REF; do
    [[ -n "${!var:-}" ]] || missing+=("$var")
done
if (( ${#missing[@]} )); then
    echo "deploy: ${missing[*]} fehlt (siehe docs/DEPLOYMENT-SERVER.md)" >&2
    exit 2
fi

if [[ ! "$INTRANET_REF" =~ ^[A-Za-z0-9._/-]+$ ]]; then
    echo "deploy: unzulaessige Zeichen in INTRANET_REF" >&2
    exit 2
fi
if [[ ! "$DEPLOY_PATH" =~ ^/[A-Za-z0-9._/-]*$ ]]; then
    echo "deploy: DEPLOY_PATH muss ein absoluter Pfad ohne Sonderzeichen sein" >&2
    exit 2
fi
if [[ "${DEPLOY_CONFIRM:-}" != "$INTRANET_REF" ]]; then
    echo "deploy: Produktion verlangt DEPLOY_CONFIRM=$INTRANET_REF" >&2
    exit 2
fi

echo "deploy: $INTRANET_REF nach $DEPLOY_HOST:$DEPLOY_PATH"
ssh -o BatchMode=yes "$DEPLOY_HOST" \
    "cd '$DEPLOY_PATH' && scripts/server-deploy.sh '$INTRANET_REF'"
echo "deploy: fertig, https-Aufruf von /up pruefen"
