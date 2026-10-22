#!/usr/bin/env bash
# Siembra el fixture de la suite E2E en la demo y guarda sus datos en e2e/.fixtures.json
#
# La demo vive en Andromeda; se siembra con wp-cli por ssh y el JSON se queda en local (ignorado por git,
# porque lleva credenciales de las personas de prueba).
set -euo pipefail

HOST="${E2E_SSH_HOST:-andromeda}"
WP_PATH="${E2E_WP_PATH:-/var/www/demo.getconvoca.app/public}"
WP_USER="${E2E_WP_USER:-http}"
AQUI="$(cd "$(dirname "$0")" && pwd)"

scp "$AQUI/fixture-remoto.php" "$HOST:/tmp/fixture-e2e-courses.php" >/dev/null

SALIDA=$(ssh "$HOST" "sudo -u $WP_USER wp --path=$WP_PATH eval-file /tmp/fixture-e2e-courses.php 2>/dev/null" | tail -1)

case "$SALIDA" in
  \{*) printf '%s\n' "$SALIDA" > "$AQUI/.fixtures.json"; echo "fixture: $AQUI/.fixtures.json"; printf '%s\n' "$SALIDA" | head -c 300; echo ;;
  *) echo "FALLO: el fixture no devolvió JSON"; printf '%s\n' "$SALIDA"; exit 1 ;;
esac
