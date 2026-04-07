#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="/projects/genesis"
COMPOSE_FILE="$ROOT_DIR/infra/compose/docker-compose.yml"
ENV_FILE="$ROOT_DIR/infra/compose/.env"
STATE_FILE="$ROOT_DIR/infra/runtime/deploy-state.env"
HOST_HEADER="${DEPLOY_HOST_HEADER:-shellr.net}"

BACKUP_ITEMS=(app docs scripts .github infra/nginx/nginx.conf infra/nginx/conf.d infra/nginx/snippets infra/compose/docker-compose.yml infra/compose/.env infra/compose/.env.example)

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

[[ -f "$STATE_FILE" ]] || fail "Deployment state file not found: $STATE_FILE"
# shellcheck disable=SC1090
source "$STATE_FILE"

BACKUP_DIR="${1:-${LAST_BACKUP_DIR:-}}"
[[ -n "$BACKUP_DIR" ]] || fail "No backup directory specified and no LAST_BACKUP_DIR in state file."
[[ -d "$BACKUP_DIR" ]] || fail "Backup directory not found: $BACKUP_DIR"

for item in "${BACKUP_ITEMS[@]}"; do
  rm -rf "$ROOT_DIR/$item"
  if [[ -e "$BACKUP_DIR/$item" ]]; then
    mkdir -p "$(dirname "$ROOT_DIR/$item")"
    cp -a "$BACKUP_DIR/$item" "$ROOT_DIR/$item"
  fi
done

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config >/dev/null
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build db app nginx

if healthcheck; then
  log "Rollback completed successfully."
  exit 0
fi

fail "Rollback completed but healthcheck did not recover."
