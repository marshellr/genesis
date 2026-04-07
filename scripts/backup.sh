#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

PROJECT_ROOT="/projects/genesis"
COMPOSE_ENV="$PROJECT_ROOT/infra/compose/.env"
BACKUP_ROOT="$PROJECT_ROOT/infra/backup"
DB_BACKUP_DIR="$BACKUP_ROOT/db"
FILES_BACKUP_DIR="$BACKUP_ROOT/files"
LOG_DIR="$BACKUP_ROOT/logs"
TMP_DIR="$BACKUP_ROOT/tmp"
RESTORE_ROOT="$BACKUP_ROOT/restore"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%F-%H%M%S)"
HOSTNAME_SHORT="$(hostname -s)"
LOG_FILE="$LOG_DIR/backup.log"
RUNTIME_STATE_DIR="$TMP_DIR/runtime-state"

mkdir -p "$DB_BACKUP_DIR" "$FILES_BACKUP_DIR" "$LOG_DIR" "$TMP_DIR" "$RESTORE_ROOT"

log() {
  printf '[%s] %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG_FILE"
}

require_file() {
  local path="$1"
  if [[ ! -f "$path" ]]; then
    log "Fehlende Datei: $path"
    exit 1
  fi
}

read_env_value() {
  local key="$1"
  local value
  value="$(grep -E "^${key}=" "$COMPOSE_ENV" | tail -n1 | cut -d= -f2- || true)"
  if [[ -z "$value" ]]; then
    log "Fehlende Variable in $COMPOSE_ENV: $key"
    exit 1
  fi
  printf '%s' "$value"
}

require_file "$COMPOSE_ENV"

if ! command -v sqlite3 >/dev/null 2>&1; then
  log "Fehlendes Kommando: sqlite3"
  exit 1
fi

COMPOSE_PROJECT_NAME="$(read_env_value COMPOSE_PROJECT_NAME)"
DB_DATABASE="$(read_env_value DB_DATABASE)"
DB_USERNAME="$(read_env_value DB_USERNAME)"
DB_PASSWORD="$(read_env_value DB_PASSWORD)"
DB_CONTAINER="${COMPOSE_PROJECT_NAME}-db"

DB_DUMP_FILE="$DB_BACKUP_DIR/${TIMESTAMP}-${HOSTNAME_SHORT}-${DB_DATABASE}.sql.gz"
FILES_ARCHIVE_FILE="$FILES_BACKUP_DIR/${TIMESTAMP}-${HOSTNAME_SHORT}-genesis-files.tar.gz"
MANIFEST_FILE="$DB_BACKUP_DIR/${TIMESTAMP}-${HOSTNAME_SHORT}-manifest.txt"

log "Backup gestartet"
log "DB-Container: $DB_CONTAINER"

if ! docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then
  log "DB-Container nicht gefunden: $DB_CONTAINER"
  exit 1
fi

if [[ "$(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER")" != "true" ]]; then
  log "DB-Container laeuft nicht: $DB_CONTAINER"
  exit 1
fi

log "Erstelle MariaDB-Dump: $DB_DUMP_FILE"
docker exec \
  -e MYSQL_PWD="$DB_PASSWORD" \
  "$DB_CONTAINER" \
  mariadb-dump \
  --single-transaction \
  --quick \
  --lock-tables=false \
  --routines \
  --triggers \
  --events \
  --databases "$DB_DATABASE" \
  -u "$DB_USERNAME" \
  | gzip -9 > "$DB_DUMP_FILE"

sudo rm -rf "$RUNTIME_STATE_DIR"
sudo mkdir -p "$RUNTIME_STATE_DIR/grafana" "$RUNTIME_STATE_DIR/uptime-kuma"

if [[ -d "$PROJECT_ROOT/infra/docker/volumes/grafana" ]]; then
  sudo cp -a "$PROJECT_ROOT/infra/docker/volumes/grafana/." "$RUNTIME_STATE_DIR/grafana/" 2>/dev/null || true
  sudo rm -f "$RUNTIME_STATE_DIR/grafana/grafana.db" "$RUNTIME_STATE_DIR/grafana/grafana.db-shm" "$RUNTIME_STATE_DIR/grafana/grafana.db-wal"
  if [[ -f "$PROJECT_ROOT/infra/docker/volumes/grafana/grafana.db" ]]; then
    sudo sqlite3 "$PROJECT_ROOT/infra/docker/volumes/grafana/grafana.db" ".backup '$RUNTIME_STATE_DIR/grafana/grafana.db'"
  fi
fi

if [[ -d "$PROJECT_ROOT/infra/docker/volumes/uptime-kuma" ]]; then
  sudo cp -a "$PROJECT_ROOT/infra/docker/volumes/uptime-kuma/." "$RUNTIME_STATE_DIR/uptime-kuma/" 2>/dev/null || true
  sudo rm -f "$RUNTIME_STATE_DIR/uptime-kuma/kuma.db" "$RUNTIME_STATE_DIR/uptime-kuma/kuma.db-shm" "$RUNTIME_STATE_DIR/uptime-kuma/kuma.db-wal"
  if [[ -f "$PROJECT_ROOT/infra/docker/volumes/uptime-kuma/kuma.db" ]]; then
    sudo sqlite3 "$PROJECT_ROOT/infra/docker/volumes/uptime-kuma/kuma.db" ".backup '$RUNTIME_STATE_DIR/uptime-kuma/kuma.db'"
  fi
fi

log "Erstelle Dateibackup: $FILES_ARCHIVE_FILE"
tar_args=(
  --exclude='./infra/backup' \
  --exclude='./infra/docker/volumes' \
  --exclude='./docs/publish' \
  --exclude='./releases' \
  -czf "$FILES_ARCHIVE_FILE" \
  -C "$PROJECT_ROOT" \
  .
)

if [[ -d "$RUNTIME_STATE_DIR/grafana" ]]; then
  tar_args+=(-C "$RUNTIME_STATE_DIR/grafana" --transform 's,^,infra/docker/volumes/grafana/,' .)
fi

if [[ -d "$RUNTIME_STATE_DIR/uptime-kuma" ]]; then
  tar_args+=(-C "$RUNTIME_STATE_DIR/uptime-kuma" --transform 's,^,infra/docker/volumes/uptime-kuma/,' .)
fi

sudo tar "${tar_args[@]}"
sudo chown shellr:shellr "$FILES_ARCHIVE_FILE"

{
  echo "timestamp=$TIMESTAMP"
  echo "host=$HOSTNAME_SHORT"
  echo "db_dump=$(basename "$DB_DUMP_FILE")"
  echo "files_archive=$(basename "$FILES_ARCHIVE_FILE")"
  echo "retention_days=$RETENTION_DAYS"
  sha256sum "$DB_DUMP_FILE" "$FILES_ARCHIVE_FILE"
} > "$MANIFEST_FILE"

log "Raeume alte Backups auf: aelter als $RETENTION_DAYS Tage"
find "$DB_BACKUP_DIR" -type f -mtime +"$RETENTION_DAYS" -delete
find "$FILES_BACKUP_DIR" -type f -mtime +"$RETENTION_DAYS" -delete
find "$RESTORE_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +"$RETENTION_DAYS" -exec rm -rf {} +
sudo rm -rf "$RUNTIME_STATE_DIR"

log "Backup abgeschlossen"
log "DB-Dump Groesse: $(du -h "$DB_DUMP_FILE" | awk '{print $1}')"
log "Dateiarchiv Groesse: $(du -h "$FILES_ARCHIVE_FILE" | awk '{print $1}')"
