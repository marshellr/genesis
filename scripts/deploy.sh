#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="/projects/genesis"
RELEASE_SHA="${1:-}"
RELEASE_DIR="${2:-}"
COMPOSE_FILE="$ROOT_DIR/infra/compose/docker-compose.yml"
ENV_FILE="$ROOT_DIR/infra/compose/.env"
RUNTIME_DIR="$ROOT_DIR/infra/runtime"
STATE_FILE="$RUNTIME_DIR/deploy-state.env"
BACKUP_ROOT="$ROOT_DIR/releases/_backups"
HOST_HEADER="${DEPLOY_HOST_HEADER:-shellr.net}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="$BACKUP_ROOT/${TIMESTAMP}-${RELEASE_SHA:0:12}"
LOCK_DIR="$RUNTIME_DIR/deploy.lock"

SYNC_DIRS=(app docs scripts .github infra/nginx/conf.d infra/nginx/snippets)
SYNC_FILES=(infra/nginx/nginx.conf infra/compose/docker-compose.yml infra/compose/.env.example)
BACKUP_ITEMS=(app docs scripts .github infra/nginx/nginx.conf infra/nginx/conf.d infra/nginx/snippets infra/compose/docker-compose.yml infra/compose/.env infra/compose/.env.example)

log() {
  printf '[deploy] %s\n' "$*"
}

fail() {
  printf '[deploy] ERROR: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Required command missing: $1"
}

cleanup() {
  rm -rf "$LOCK_DIR"
}

backup_item() {
  local relative="$1"
  local source_path="$ROOT_DIR/$relative"
  local target_path="$BACKUP_DIR/$relative"

  if [[ -e "$source_path" ]]; then
    mkdir -p "$(dirname "$target_path")"
    cp -a "$source_path" "$target_path"
  fi
}

restore_item() {
  local relative="$1"
  local live_path="$ROOT_DIR/$relative"
  local backup_path="$BACKUP_DIR/$relative"

  rm -rf "$live_path"

  if [[ -e "$backup_path" ]]; then
    mkdir -p "$(dirname "$live_path")"
    cp -a "$backup_path" "$live_path"
  fi
}

sync_directory() {
  local relative="$1"
  local source_path="$RELEASE_DIR/$relative"
  local live_path="$ROOT_DIR/$relative"

  [[ -d "$source_path" ]] || return 0

  rm -rf "$live_path"
  mkdir -p "$(dirname "$live_path")"
  cp -a "$source_path" "$live_path"
}

sync_file() {
  local relative="$1"
  local source_path="$RELEASE_DIR/$relative"
  local live_path="$ROOT_DIR/$relative"

  [[ -f "$source_path" ]] || return 0

  mkdir -p "$(dirname "$live_path")"
  install -m 0644 "$source_path" "$live_path"
}

upsert_env_value() {
  local key="$1"
  local value="$2"

  touch "$ENV_FILE"

  if grep -q "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
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

rollback() {
  log "Starting rollback to previous release state."

  local item
  for item in "${BACKUP_ITEMS[@]}"; do
    restore_item "$item"
  done

  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config >/dev/null
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build db app nginx

  if healthcheck; then
    log "Rollback completed successfully."
    return 0
  fi

  return 1
}

trap cleanup EXIT

require_command docker
require_command cp
require_command curl
require_command grep
require_command install
require_command sed

[[ -n "$RELEASE_SHA" ]] || fail "Usage: deploy.sh <release-sha> <release-dir>"
[[ -n "$RELEASE_DIR" ]] || fail "Usage: deploy.sh <release-sha> <release-dir>"
[[ -d "$RELEASE_DIR" ]] || fail "Release directory not found: $RELEASE_DIR"
[[ -f "$RELEASE_DIR/app/Dockerfile" ]] || fail "Release is missing app/Dockerfile"
[[ -f "$RELEASE_DIR/infra/compose/docker-compose.yml" ]] || fail "Release is missing infra/compose/docker-compose.yml"
[[ -f "$ENV_FILE" ]] || fail "Compose env file missing: $ENV_FILE"

mkdir -p "$RUNTIME_DIR" "$BACKUP_ROOT"

if ! mkdir "$LOCK_DIR" 2>/dev/null; then
  fail "Another deployment appears to be running."
fi

PREVIOUS_IMAGE_TAG="$(awk -F= '/^APP_IMAGE_TAG=/{print $2}' "$ENV_FILE" | tail -n1)"
PREVIOUS_IMAGE_REPOSITORY="$(awk -F= '/^APP_IMAGE_REPOSITORY=/{print $2}' "$ENV_FILE" | tail -n1)"
PREVIOUS_IMAGE_TAG="${PREVIOUS_IMAGE_TAG:-manual}"
PREVIOUS_IMAGE_REPOSITORY="${PREVIOUS_IMAGE_REPOSITORY:-genesis-app}"

log "Creating backup in $BACKUP_DIR"
for item in "${BACKUP_ITEMS[@]}"; do
  backup_item "$item"
done

log "Syncing release files into live tree"
for item in "${SYNC_DIRS[@]}"; do
  sync_directory "$item"
done
for item in "${SYNC_FILES[@]}"; do
  sync_file "$item"
done

chmod 755 "$ROOT_DIR/scripts" || true
find "$ROOT_DIR/scripts" -maxdepth 1 -type f -name '*.sh' -exec chmod 750 {} \; || true

upsert_env_value APP_IMAGE_REPOSITORY "${PREVIOUS_IMAGE_REPOSITORY}"
upsert_env_value APP_IMAGE_TAG "$RELEASE_SHA"

log "Validating Compose configuration"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config >/dev/null

log "Building app image tag ${RELEASE_SHA}"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" build app

log "Starting updated services"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build db app nginx

if ! healthcheck; then
  log "Healthcheck failed after deployment."
  if rollback; then
    fail "Deployment failed and rollback succeeded."
  else
    fail "Deployment failed and rollback also failed. Manual intervention required."
  fi
fi

cat > "$STATE_FILE" <<EOF
LAST_DEPLOYED_SHA=$RELEASE_SHA
LAST_DEPLOYED_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
LAST_BACKUP_DIR=$BACKUP_DIR
PREVIOUS_APP_IMAGE_REPOSITORY=$PREVIOUS_IMAGE_REPOSITORY
PREVIOUS_APP_IMAGE_TAG=$PREVIOUS_IMAGE_TAG
CURRENT_APP_IMAGE_REPOSITORY=$PREVIOUS_IMAGE_REPOSITORY
CURRENT_APP_IMAGE_TAG=$RELEASE_SHA
EOF

log "Deployment completed successfully."
