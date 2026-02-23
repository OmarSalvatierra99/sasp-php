#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVICE_TEMPLATE="$ROOT_DIR/deploy/sasp-report.service.template"
TIMER_TEMPLATE="$ROOT_DIR/deploy/sasp-report.timer.template"
ENV_EXAMPLE="$ROOT_DIR/deploy/sasp-report.env.example"

ENV_FILE="${ENV_FILE:-/etc/sasp-report.env}"
RUN_USER="${RUN_USER:-$(id -un)}"
RUN_GROUP="${RUN_GROUP:-$(id -gn)}"
SYSTEMD_DIR="/etc/systemd/system"
SERVICE_NAME="sasp-report.service"
TIMER_NAME="sasp-report.timer"

if [[ ! -f "$SERVICE_TEMPLATE" || ! -f "$TIMER_TEMPLATE" ]]; then
  echo "No se encontraron plantillas en $ROOT_DIR/deploy" >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "No existe $ENV_FILE. Creando desde ejemplo..."
  sudo cp "$ENV_EXAMPLE" "$ENV_FILE"
  sudo chmod 600 "$ENV_FILE"
  echo "Edita $ENV_FILE y reemplaza EMAIL_PASS antes de activar en producción."
fi

TMP_SERVICE="$(mktemp)"
TMP_TIMER="$(mktemp)"

sed \
  -e "s|__WORKDIR__|$ROOT_DIR|g" \
  -e "s|__ENV_FILE__|$ENV_FILE|g" \
  -e "s|__RUN_USER__|$RUN_USER|g" \
  -e "s|__RUN_GROUP__|$RUN_GROUP|g" \
  "$SERVICE_TEMPLATE" > "$TMP_SERVICE"

cp "$TIMER_TEMPLATE" "$TMP_TIMER"

sudo cp "$TMP_SERVICE" "$SYSTEMD_DIR/$SERVICE_NAME"
sudo cp "$TMP_TIMER" "$SYSTEMD_DIR/$TIMER_NAME"
rm -f "$TMP_SERVICE" "$TMP_TIMER"

sudo systemctl daemon-reload
sudo systemctl enable --now "$TIMER_NAME"

echo "Instalación completada."
echo "- Servicio: $SYSTEMD_DIR/$SERVICE_NAME"
echo "- Timer:    $SYSTEMD_DIR/$TIMER_NAME"
echo "- Env:      $ENV_FILE"
echo
echo "Prueba manual del servicio:"
echo "  sudo systemctl start $SERVICE_NAME"
echo "  sudo systemctl status $SERVICE_NAME --no-pager"
echo
echo "Próximas ejecuciones del timer:"
echo "  systemctl list-timers --all | grep sasp-report"
