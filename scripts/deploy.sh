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
RELEASE_BACKUP_RETENTION="${RELEASE_BACKUP_RETENTION:-10}"

SYNC_DIRS=(app dma docs scripts .github infra/nginx/conf.d infra/nginx/snippets infra/nginx/static)
SYNC_FILES=(infra/nginx/nginx.conf infra/compose/docker-compose.yml infra/compose/.env.example)
PLATFORM_CONFIG_FILES=(
  infra/monitoring/docker-compose.monitoring.yml
  infra/monitoring/.env.example
  infra/monitoring/prometheus/prometheus.yml
  infra/monitoring/prometheus/alerts.yml
  infra/monitoring/alertmanager/alertmanager.yml
  infra/monitoring/grafana/provisioning/datasources/prometheus.yml
  infra/monitoring/grafana/provisioning/datasources/loki.yml
  infra/monitoring/grafana/provisioning/dashboards/dashboards.yml
  infra/monitoring/grafana/dashboards/genesis-vm-overview.json
  infra/logging/docker-compose.logging.yml
  infra/logging/.env.example
  infra/logging/loki/config.yml
  infra/logging/alloy/config.alloy
)
BACKUP_ITEMS=(app dma docs scripts .github infra/nginx/nginx.conf infra/nginx/conf.d infra/nginx/snippets infra/nginx/static infra/compose/docker-compose.yml infra/compose/.env infra/compose/.env.example infra/monitoring infra/logging infra/backup/cron)
PLATFORM_CONFIG_CHANGED=0

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

  if [[ -d "$backup_path" ]]; then
    mkdir -p "$live_path"
    rsync -a --delete "$backup_path/" "$live_path/"
  elif [[ -e "$backup_path" ]]; then
    mkdir -p "$(dirname "$live_path")"
    cp -a "$backup_path" "$live_path"
  fi
}

sync_directory() {
  local relative="$1"
  local source_path="$RELEASE_DIR/$relative"
  local live_path="$ROOT_DIR/$relative"

  [[ -d "$source_path" ]] || return 0

  # Keep the target directory inode so Docker bind mounts remain valid.
  mkdir -p "$live_path"
  rsync -a --delete "$source_path/" "$live_path/"
}

sync_file() {
  local relative="$1"
  local source_path="$RELEASE_DIR/$relative"
  local live_path="$ROOT_DIR/$relative"

  [[ -f "$source_path" ]] || return 0

  mkdir -p "$(dirname "$live_path")"
  install -m 0644 "$source_path" "$live_path"
}

sync_platform_file() {
  local relative="$1"
  local source_path="$RELEASE_DIR/$relative"
  local live_path="$ROOT_DIR/$relative"

  [[ -f "$source_path" ]] || return 0
  if [[ -f "$live_path" ]] && cmp -s "$source_path" "$live_path"; then
    return 0
  fi

  mkdir -p "$(dirname "$live_path")"
  install -m 0644 "$source_path" "$live_path"
  PLATFORM_CONFIG_CHANGED=1
}

install_cron_jobs() {
  local cron_file target name existing keep
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
}

restart_platform_services() {
  (( PLATFORM_CONFIG_CHANGED == 1 )) || return 0

  log "Applying monitoring and logging configuration changes"
  docker compose --env-file "$ROOT_DIR/infra/monitoring/.env" -f "$ROOT_DIR/infra/monitoring/docker-compose.monitoring.yml" config >/dev/null
  docker compose --env-file "$ROOT_DIR/infra/monitoring/.env" -f "$ROOT_DIR/infra/monitoring/docker-compose.monitoring.yml" restart prometheus alertmanager grafana
  docker compose --env-file "$ROOT_DIR/infra/monitoring/.env" -f "$ROOT_DIR/infra/monitoring/docker-compose.monitoring.yml" up -d --no-build --wait prometheus alertmanager grafana

  docker compose --env-file "$ROOT_DIR/infra/logging/.env" -f "$ROOT_DIR/infra/logging/docker-compose.logging.yml" config >/dev/null
  docker compose --env-file "$ROOT_DIR/infra/logging/.env" -f "$ROOT_DIR/infra/logging/docker-compose.logging.yml" up -d --no-build --remove-orphans --wait loki alloy
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

smoke_check() {
  local name="$1"
  local url="$2"
  local expected="$3"
  local response

  response="$(curl -fsS --max-time 10 "$url")" || {
    log "Public smoke check failed for $name"
    return 1
  }
  printf '%s' "$response" | grep -Fq "$expected" || {
    log "Public smoke check returned an unexpected response for $name"
    return 1
  }
}

prune_release_backups() {
  local -a backups=()
  local index

  mapfile -t backups < <(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r)
  for ((index=RELEASE_BACKUP_RETENTION; index<${#backups[@]}; index++)); do
    rm -rf -- "$BACKUP_ROOT/${backups[$index]}"
  done
}

rollback() {
  log "Starting rollback to previous release state."

  local item
  for item in "${BACKUP_ITEMS[@]}"; do
    restore_item "$item"
  done

  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config >/dev/null
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build --wait db app dma nginx

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
require_command cmp
require_command rsync
require_command sudo

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
PREVIOUS_DMA_IMAGE_TAG="$(awk -F= '/^DMA_IMAGE_TAG=/{print $2}' "$ENV_FILE" | tail -n1)"
PREVIOUS_DMA_IMAGE_REPOSITORY="$(awk -F= '/^DMA_IMAGE_REPOSITORY=/{print $2}' "$ENV_FILE" | tail -n1)"
PREVIOUS_IMAGE_TAG="${PREVIOUS_IMAGE_TAG:-manual}"
PREVIOUS_IMAGE_REPOSITORY="${PREVIOUS_IMAGE_REPOSITORY:-genesis-app}"
PREVIOUS_DMA_IMAGE_TAG="${PREVIOUS_DMA_IMAGE_TAG:-manual}"
PREVIOUS_DMA_IMAGE_REPOSITORY="${PREVIOUS_DMA_IMAGE_REPOSITORY:-genesis-dma}"

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
for item in "${PLATFORM_CONFIG_FILES[@]}"; do
  sync_platform_file "$item"
done

install_cron_jobs

chmod 755 "$ROOT_DIR/scripts" || true
find "$ROOT_DIR/scripts" -maxdepth 1 -type f -name '*.sh' -exec chmod 750 {} \; || true

upsert_env_value APP_IMAGE_REPOSITORY "${PREVIOUS_IMAGE_REPOSITORY}"
upsert_env_value APP_IMAGE_TAG "$RELEASE_SHA"
upsert_env_value DMA_IMAGE_REPOSITORY "${PREVIOUS_DMA_IMAGE_REPOSITORY}"
upsert_env_value DMA_IMAGE_TAG "$RELEASE_SHA"

log "Validating Compose configuration"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config >/dev/null

log "Building app image tag ${RELEASE_SHA}"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" build app dma

log "Starting updated services"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build --wait db app dma nginx
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T nginx nginx -t
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T nginx nginx -s reload
restart_platform_services

if ! healthcheck \
  || ! smoke_check "shellr health" "https://shellr.net/health" '"status":"ok"' \
  || ! smoke_check "DMA showcase" "https://dma.shellr.net/" 'DMA Statistics Module' \
  || ! smoke_check "status snapshot" "https://status.shellr.net/" 'shellr platform status'; then
  log "Post-deployment verification failed."
  if rollback; then
    fail "Deployment failed and rollback succeeded."
  else
    fail "Deployment failed and rollback also failed. Manual intervention required."
  fi
fi

prune_release_backups

cat > "$STATE_FILE" <<EOF
LAST_DEPLOYED_SHA=$RELEASE_SHA
LAST_DEPLOYED_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
LAST_BACKUP_DIR=$BACKUP_DIR
PREVIOUS_APP_IMAGE_REPOSITORY=$PREVIOUS_IMAGE_REPOSITORY
PREVIOUS_APP_IMAGE_TAG=$PREVIOUS_IMAGE_TAG
CURRENT_APP_IMAGE_REPOSITORY=$PREVIOUS_IMAGE_REPOSITORY
CURRENT_APP_IMAGE_TAG=$RELEASE_SHA
PREVIOUS_DMA_IMAGE_REPOSITORY=$PREVIOUS_DMA_IMAGE_REPOSITORY
PREVIOUS_DMA_IMAGE_TAG=$PREVIOUS_DMA_IMAGE_TAG
CURRENT_DMA_IMAGE_REPOSITORY=$PREVIOUS_DMA_IMAGE_REPOSITORY
CURRENT_DMA_IMAGE_TAG=$RELEASE_SHA
EOF

log "Deployment completed successfully."
