#!/usr/bin/env bash
# Erzwungener Befehl fuer den Deploy-Schluessel.
#
# Eintrag in ~/.ssh/authorized_keys des Deploy-Benutzers:
#
#   restrict,command="/opt/mhag-intranet/scripts/ssh-forced-command.sh" ssh-ed25519 AAAA... deploy-intranet
#
# Damit oeffnet der Schluessel keine freie Sitzung. Er laesst ausschliesslich
# den einen Befehl zu, den scripts/deploy.sh und der Ablauf in GitHub Actions
# absetzen. Alles andere wird abgewiesen.
#
# Wichtig: Der Deploy-Benutzer gehoert der Gruppe docker an und ist damit
# faktisch Systemverwalter. Diese Einschraenkung verhindert den bequemen Weg,
# nicht jeden denkbaren. Siehe docs/DEPLOYMENT-SERVER.md, Abschnitt 11.
set -euo pipefail

BEFEHL="${SSH_ORIGINAL_COMMAND:-}"

# Erwartet genau die Form, die scripts/deploy.sh und deploy-server.yml senden:
#   cd '<absoluter pfad>' && scripts/server-deploy.sh '<stand>'
MUSTER="^cd '(/[A-Za-z0-9._/-]+)' && scripts/server-deploy\.sh '([A-Za-z0-9._/-]+)'\$"

# Nur dieses eine Verzeichnis. Abweichender Ort: MHAG_DEPLOY_PATH in der
# Umgebung des Deploy-Benutzers setzen (zum Beispiel in ~/.profile).
ERLAUBTER_PFAD="${MHAG_DEPLOY_PATH:-/opt/mhag-intranet}"

if [[ "$BEFEHL" =~ $MUSTER ]] && [[ "${BASH_REMATCH[1]}" == "$ERLAUBTER_PFAD" ]]; then
    cd "${BASH_REMATCH[1]}"
    exec scripts/server-deploy.sh "${BASH_REMATCH[2]}"
fi

echo "Dieser Zugang erlaubt ausschliesslich das Deployment des Intranets." >&2
exit 2
