#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="/projects/genesis"
COMPOSE_FILE="$ROOT_DIR/infra/compose/docker-compose.yml"
ENV_FILE="$ROOT_DIR/infra/compose/.env"
STATE_FILE="$ROOT_DIR/infra/runtime/deploy-state.env"
HOST_HEADER="${DEPLOY_HOST_HEADER:-shellr.net}"
LOGROTATE_SOURCE="$ROOT_DIR/infra/monitoring/logrotate/uptime-kuma.conf"
LOGROTATE_TARGET="/etc/logrotate.d/genesis-uptime-kuma"
SYSTEMD_SOURCE_DIR="$ROOT_DIR/infra/systemd"
SYSTEMD_TARGET_DIR="/etc/systemd/system"
SYSTEMD_UNITS=(
  genesis-post-reboot-health.service
  genesis-weekly-maintenance.service
  genesis-weekly-maintenance.timer
)

BACKUP_ITEMS=(app dma docs scripts .github infra/nginx/nginx.conf infra/nginx/conf.d infra/nginx/snippets infra/nginx/static infra/compose/docker-compose.yml infra/compose/.env infra/compose/.env.example infra/monitoring infra/logging infra/backup/cron infra/systemd)

log() {
  printf '[rollback] %s\n' "$*"
}

fail() {
  printf '[rollback] ERROR: %s\n' "$*" >&2
  exit 1
}

healthcheck() {
  local attempt
  local response

  for attempt in $(seq 1 24); do
    if response="$(curl -kfsS --max-time 5 -H "Host: $HOST_HEADER" https://127.0.0.1/health 2>/dev/null)"; then
      if printf '%s' "$response" | grep -Eq '"status"[[:space:]]*:[[:space:]]*"ok"'; then
        log "Healthcheck passed on attempt ${attempt}."
        return 0
      fi
    fi

    sleep 5
  done

  return 1
}

restore_item() {
  local relative="$1"
  local live_path="$ROOT_DIR/$relative"
  local backup_path="$BACKUP_DIR/$relative"

  if [[ -d "$backup_path" ]]; then
    mkdir -p "$live_path"
    rsync -a --delete --exclude=/local/ "$backup_path/" "$live_path/"
  elif [[ -e "$backup_path" ]]; then
    mkdir -p "$(dirname "$live_path")"
    cp -a "$backup_path" "$live_path"
  fi
}

restore_platform_services() {
  local cron_file target name existing keep
  local unit
  local -a expected_targets=()

  for cron_file in "$ROOT_DIR"/infra/backup/cron/*.cron; do
    [[ -f "$cron_file" ]] || continue
    name="$(basename "${cron_file%.cron}")"
    if [[ "$name" == genesis-* ]]; then
      target="/etc/cron.d/$name"
    else
      target="/etc/cron.d/genesis-$name"
    fi
    sudo install -m 0644 "$cron_file" "$target"
    expected_targets+=("$target")
  done

  for existing in /etc/cron.d/genesis-*; do
    [[ -f "$existing" ]] || continue
    keep=0
    for target in "${expected_targets[@]}"; do
      [[ "$existing" == "$target" ]] && keep=1 && break
    done
    (( keep == 1 )) || sudo rm -f -- "$existing"
  done

  if [[ -f "$LOGROTATE_SOURCE" ]]; then
    sudo install -o root -g root -m 0644 "$LOGROTATE_SOURCE" "$LOGROTATE_TARGET"
  else
    sudo rm -f -- "$LOGROTATE_TARGET"
  fi

  for unit in "${SYSTEMD_UNITS[@]}"; do
    if [[ -f "$SYSTEMD_SOURCE_DIR/$unit" ]]; then
      sudo install -o root -g root -m 0644 "$SYSTEMD_SOURCE_DIR/$unit" "$SYSTEMD_TARGET_DIR/$unit"
    fi
  done
  sudo systemctl daemon-reload
  if [[ -f "$SYSTEMD_TARGET_DIR/genesis-post-reboot-health.service" ]]; then
    sudo systemctl enable genesis-post-reboot-health.service >/dev/null
    sudo systemctl enable --now genesis-weekly-maintenance.timer >/dev/null
  fi

  docker compose --env-file "$ROOT_DIR/infra/monitoring/.env" -f "$ROOT_DIR/infra/monitoring/docker-compose.monitoring.yml" config >/dev/null
  docker compose --env-file "$ROOT_DIR/infra/monitoring/.env" -f "$ROOT_DIR/infra/monitoring/docker-compose.monitoring.yml" restart prometheus alertmanager grafana

  docker compose --env-file "$ROOT_DIR/infra/logging/.env" -f "$ROOT_DIR/infra/logging/docker-compose.logging.yml" config >/dev/null
  docker compose --env-file "$ROOT_DIR/infra/logging/.env" -f "$ROOT_DIR/infra/logging/docker-compose.logging.yml" up -d --no-build --remove-orphans --wait
}

[[ -f "$STATE_FILE" ]] || fail "Deployment state file not found: $STATE_FILE"
# shellcheck disable=SC1090
source "$STATE_FILE"

BACKUP_DIR="${1:-${LAST_BACKUP_DIR:-}}"
[[ -n "$BACKUP_DIR" ]] || fail "No backup directory specified and no LAST_BACKUP_DIR in state file."
[[ -d "$BACKUP_DIR" ]] || fail "Backup directory not found: $BACKUP_DIR"

command -v rsync >/dev/null 2>&1 || fail "Required command missing: rsync"
command -v systemctl >/dev/null 2>&1 || fail "Required command missing: systemctl"

for item in "${BACKUP_ITEMS[@]}"; do
  restore_item "$item"
done

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config >/dev/null
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build --wait db app dma nginx
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T nginx nginx -t
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T nginx nginx -s reload
restore_platform_services

if healthcheck; then
  log "Rollback completed successfully."
  exit 0
fi

fail "Rollback completed but healthcheck did not recover."
