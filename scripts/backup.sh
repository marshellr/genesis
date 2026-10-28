#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

PROJECT_ROOT="/projects/genesis"
COMPOSE_ENV="$PROJECT_ROOT/infra/compose/.env"
BACKUP_ROOT="$PROJECT_ROOT/infra/backup"
DB_BACKUP_DIR="$BACKUP_ROOT/db"
FILES_BACKUP_DIR="$BACKUP_ROOT/files"
LOG_DIR="$BACKUP_ROOT/logs"
TMP_DIR="$BACKUP_ROOT/tmp"
RESTORE_ROOT="$BACKUP_ROOT/restore"
RETENTION_COUNT="${BACKUP_RETENTION_COUNT:-7}"
RESTORE_RETENTION_DAYS="${BACKUP_RESTORE_RETENTION_DAYS:-7}"
MIN_FREE_MIB="${BACKUP_MIN_FREE_MIB:-2048}"
ESTIMATED_ARCHIVE_MIB="${BACKUP_ESTIMATED_ARCHIVE_MIB:-1024}"
TIMESTAMP="$(date +%F-%H%M%S)"
HOSTNAME_SHORT="$(hostname -s)"
LOG_FILE="$LOG_DIR/backup.log"
LOCK_FILE="$BACKUP_ROOT/.backup.lock"
RUN_TMP_DIR="$TMP_DIR/run-${TIMESTAMP}-$$"
ARCHIVE_OVERLAY_DIR="$RUN_TMP_DIR/_runtime-overlay"
RUNTIME_STATE_DIR="$ARCHIVE_OVERLAY_DIR/infra/docker/volumes"
RESTIC_ENV_FILE="$PROJECT_ROOT/infra/backup/restic.env"
RESTIC_CACHE_DIR="$PROJECT_ROOT/restic-cache"
BACKUP_HEALTH_FILE="$BACKUP_ROOT/last-successful-backup"
LOCAL_BACKUP_COMPLETE=0
DB_DUMP_FILE=""
FILES_ARCHIVE_FILE=""
MANIFEST_FILE=""
DB_DUMP_FILE_PARTIAL=""
FILES_ARCHIVE_FILE_PARTIAL=""
MANIFEST_FILE_PARTIAL=""

mkdir -p "$DB_BACKUP_DIR" "$FILES_BACKUP_DIR" "$LOG_DIR" "$TMP_DIR" "$RESTORE_ROOT"

log() {
  printf '[%s] %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG_FILE"
}

require_positive_integer() {
  local name="$1"
  local value="$2"

  if [[ ! "$value" =~ ^[1-9][0-9]*$ ]]; then
    log "Ungueltiger Wert fuer $name: $value (erwartet: positive Ganzzahl)"
    exit 1
  fi
}

cleanup() {
  local exit_code=$?
  local path

  trap - EXIT INT TERM HUP
  set +e

  if [[ -d "$RUN_TMP_DIR" ]]; then
    sudo rm -rf -- "$RUN_TMP_DIR"
  fi

  rm -f -- "$BACKUP_HEALTH_FILE.tmp"
  if (( LOCAL_BACKUP_COMPLETE == 0 )); then
    for path in \
      "$DB_DUMP_FILE_PARTIAL" \
      "$FILES_ARCHIVE_FILE_PARTIAL" \
      "$MANIFEST_FILE_PARTIAL" \
      "$DB_DUMP_FILE" \
      "$FILES_ARCHIVE_FILE" \
      "$MANIFEST_FILE"; do
      [[ -n "$path" ]] && rm -f -- "$path"
    done
  fi

  if (( exit_code != 0 )); then
    log "Backup fehlgeschlagen (Exit-Code $exit_code); temporaere Daten wurden entfernt."
  fi

  exit "$exit_code"
}

cleanup_stale_tmp() {
  # The fixed runtime-state path comes from the previous implementation.
  sudo rm -rf -- "$TMP_DIR/runtime-state"
  sudo find "$TMP_DIR" \
    -mindepth 1 \
    -maxdepth 1 \
    -type d \
    -name 'run-*' \
    -exec rm -rf -- {} +
}

artifact_is_referenced() {
  local key="$1"
  local artifact_name="$2"
  local manifest

  shopt -s nullglob
  for manifest in "$DB_BACKUP_DIR"/*-manifest.txt; do
    [[ -s "$manifest" ]] || continue
    if grep -Fqx -- "$key=$artifact_name" "$manifest"; then
      return 0
    fi
  done
  return 1
}

cleanup_incomplete_backups() {
  local artifact
  local artifact_name

  shopt -s nullglob

  rm -f -- "$DB_BACKUP_DIR"/*.partial "$FILES_BACKUP_DIR"/*.partial
  find "$DB_BACKUP_DIR" "$FILES_BACKUP_DIR" -maxdepth 1 -type f -size 0 -delete

  for artifact in "$DB_BACKUP_DIR"/*.sql.gz; do
    artifact_name="$(basename "$artifact")"
    if ! artifact_is_referenced db_dump "$artifact_name"; then
      log "Entferne unvollstaendigen DB-Dump: $artifact_name"
      rm -f -- "$artifact"
    fi
  done

  for artifact in "$FILES_BACKUP_DIR"/*-genesis-files.tar.gz; do
    artifact_name="$(basename "$artifact")"
    if ! artifact_is_referenced files_archive "$artifact_name"; then
      log "Entferne unvollstaendiges Dateiarchiv: $artifact_name"
      rm -f -- "$artifact"
    fi
  done
}

remove_backup_set() {
  local manifest="$1"
  local db_dump
  local files_archive

  db_dump="$(awk -F= '$1 == "db_dump" { print $2; exit }' "$manifest")"
  files_archive="$(awk -F= '$1 == "files_archive" { print $2; exit }' "$manifest")"

  if [[ -n "$db_dump" && "$db_dump" == "$(basename "$db_dump")" ]]; then
    rm -f -- "$DB_BACKUP_DIR/$db_dump"
  fi
  if [[ -n "$files_archive" && "$files_archive" == "$(basename "$files_archive")" ]]; then
    rm -f -- "$FILES_BACKUP_DIR/$files_archive"
  fi
  rm -f -- "$manifest"
}

prune_local_backups() {
  local index
  local manifest_entry
  local manifest
  local -a manifests=()

  mapfile -t manifests < <(
    find "$DB_BACKUP_DIR" \
      -maxdepth 1 \
      -type f \
      -name '*-manifest.txt' \
      -size +0c \
      -printf '%T@ %p\n' \
      | sort -nr
  )

  for ((index=RETENTION_COUNT; index<${#manifests[@]}; index++)); do
    manifest_entry="${manifests[$index]}"
    manifest="${manifest_entry#* }"
    log "Entferne lokalen Backup-Satz ausserhalb der Retention: $(basename "$manifest")"
    remove_backup_set "$manifest"
  done
}

check_free_space() {
  local available_kib
  local estimated_archive_kib
  local runtime_state_kib=0
  local required_kib
  local latest_archive_entry
  local latest_archive
  local path
  local path_kib

  available_kib="$(df -Pk "$BACKUP_ROOT" | awk 'NR == 2 { print $4 }')"
  estimated_archive_kib=$((ESTIMATED_ARCHIVE_MIB * 1024))

  latest_archive_entry="$(
    find "$FILES_BACKUP_DIR" \
      -maxdepth 1 \
      -type f \
      -name '*-genesis-files.tar.gz' \
      -size +0c \
      -printf '%T@ %p\n' \
      | sort -nr \
      | sed -n '1p'
  )"
  latest_archive="${latest_archive_entry#* }"
  if [[ -n "$latest_archive_entry" && -f "$latest_archive" ]]; then
    estimated_archive_kib="$(du -k "$latest_archive" | awk '{ print $1 }')"
  fi

  for path in \
    "$PROJECT_ROOT/infra/docker/volumes/grafana" \
    "$PROJECT_ROOT/infra/docker/volumes/uptime-kuma" \
    "${EXTRA_BACKUP_PATHS[@]}"; do
    [[ -d "$path" ]] || continue
    path_kib="$(sudo du -sk --exclude='*.log' --exclude='*.log.*' "$path" | awk '{ print $1 }')"
    runtime_state_kib=$((runtime_state_kib + path_kib))
  done

  required_kib=$((MIN_FREE_MIB * 1024 + estimated_archive_kib + runtime_state_kib))
  log "Speicherpruefung: verfuegbar=$((available_kib / 1024)) MiB, benoetigt=$((required_kib / 1024)) MiB (inklusive ${MIN_FREE_MIB} MiB Reserve)"

  if (( available_kib < required_kib )); then
    log "Zu wenig freier Speicher fuer ein sicheres Backup; es wurden keine Backupdaten erzeugt."
    exit 1
  fi
}

snapshot_sqlite_runtime() {
  local source_dir="$1"
  local target_dir="$2"
  local database_name="$3"

  sudo test -f "$source_dir/$database_name" || { log "Fehlende SQLite-Datenbank: $source_dir/$database_name"; exit 1; }

  sudo mkdir -p "$target_dir"
  sudo tar \
    --exclude="$database_name" \
    --exclude="$database_name-shm" \
    --exclude="$database_name-wal" \
    --exclude='*.log' \
    --exclude='*.log.*' \
    -C "$source_dir" \
    -cf - \
    . \
    | sudo tar -C "$target_dir" -xf -

  if sudo test -f "$source_dir/$database_name"; then
    sudo sqlite3 "$source_dir/$database_name" ".timeout 30000" ".backup '$target_dir/$database_name'"
    [[ "$(sudo sqlite3 "$target_dir/$database_name" "PRAGMA integrity_check;")" == "ok" ]] || { log "SQLite-Pruefung fehlgeschlagen: $database_name"; exit 1; }
  fi
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

read_optional_env_file() {
  local path="$1"
  if [[ ! -f "$path" ]]; then
    return 1
  fi

  set -a
  # shellcheck disable=SC1090
  source "$path"
  set +a
  return 0
}

EXTRA_BACKUP_PATHS=()
backup_extra_projects() { :; }
backup_extra_manifest() { echo "sqlite_snapshots=grafana.db,kuma.db"; }
# Trusted host-local extensions are deliberately excluded from Git.
if [[ -f "$PROJECT_ROOT/infra/local/backup.sh" ]]; then
  source "$PROJECT_ROOT/infra/local/backup.sh"
fi

require_file "$COMPOSE_ENV"

for required_command in docker flock sqlite3 sudo tar; do
  if ! command -v "$required_command" >/dev/null 2>&1; then
    log "Fehlendes Kommando: $required_command"
    exit 1
  fi
done

require_positive_integer BACKUP_RETENTION_COUNT "$RETENTION_COUNT"
require_positive_integer BACKUP_RESTORE_RETENTION_DAYS "$RESTORE_RETENTION_DAYS"
require_positive_integer BACKUP_MIN_FREE_MIB "$MIN_FREE_MIB"
require_positive_integer BACKUP_ESTIMATED_ARCHIVE_MIB "$ESTIMATED_ARCHIVE_MIB"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  log "Ein anderer Backup-Lauf ist bereits aktiv; dieser Lauf wird beendet."
  if [[ "${BACKUP_REQUIRE_RUN:-false}" == "true" ]]; then
    exit 75
  fi
  exit 0
fi

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP

cleanup_stale_tmp
cleanup_incomplete_backups

COMPOSE_PROJECT_NAME="$(read_env_value COMPOSE_PROJECT_NAME)"
DB_DATABASE="$(read_env_value DB_DATABASE)"
DB_ROOT_PASSWORD="$(read_env_value DB_ROOT_PASSWORD)"
DMA_DB_NAME="$(read_env_value DMA_DB_NAME)"
DB_CONTAINER="${COMPOSE_PROJECT_NAME}-db"

DB_DUMP_FILE="$DB_BACKUP_DIR/${TIMESTAMP}-${HOSTNAME_SHORT}-${COMPOSE_PROJECT_NAME}-databases.sql.gz"
FILES_ARCHIVE_FILE="$FILES_BACKUP_DIR/${TIMESTAMP}-${HOSTNAME_SHORT}-genesis-files.tar.gz"
MANIFEST_FILE="$DB_BACKUP_DIR/${TIMESTAMP}-${HOSTNAME_SHORT}-manifest.txt"
DB_DUMP_FILE_PARTIAL="$DB_DUMP_FILE.partial"
FILES_ARCHIVE_FILE_PARTIAL="$FILES_ARCHIVE_FILE.partial"
MANIFEST_FILE_PARTIAL="$MANIFEST_FILE.partial"

log "Backup gestartet"
log "DB-Container: $DB_CONTAINER"

check_free_space

if ! docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then
  log "DB-Container nicht gefunden: $DB_CONTAINER"
  exit 1
fi

if [[ "$(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER")" != "true" ]]; then
  log "DB-Container laeuft nicht: $DB_CONTAINER"
  exit 1
fi

log "Erstelle MariaDB-Dump fuer $DB_DATABASE und $DMA_DB_NAME: $DB_DUMP_FILE"
docker exec \
  -e MYSQL_PWD="$DB_ROOT_PASSWORD" \
  "$DB_CONTAINER" \
  mariadb-dump \
  --single-transaction \
  --quick \
  --lock-tables=false \
  --routines \
  --triggers \
  --events \
  --databases "$DB_DATABASE" "$DMA_DB_NAME" \
  -u root \
  | gzip -9 > "$DB_DUMP_FILE_PARTIAL"

sudo mkdir -p "$RUNTIME_STATE_DIR/grafana" "$RUNTIME_STATE_DIR/uptime-kuma"

snapshot_sqlite_runtime \
  "$PROJECT_ROOT/infra/docker/volumes/grafana" \
  "$RUNTIME_STATE_DIR/grafana" \
  grafana.db

snapshot_sqlite_runtime \
  "$PROJECT_ROOT/infra/docker/volumes/uptime-kuma" \
  "$RUNTIME_STATE_DIR/uptime-kuma" \
  kuma.db

backup_extra_projects

log "Erstelle Dateibackup: $FILES_ARCHIVE_FILE"
tar_args=(
  --exclude='./infra/backup'
  --exclude='./infra/docker/volumes'
  --exclude='./docs/publish'
  --exclude='./releases'
  --exclude='./infra/runtime'
  --exclude='./restic-cache'
  -czf "$FILES_ARCHIVE_FILE_PARTIAL"
  -C "$PROJECT_ROOT"
  .
)

if [[ -d "$ARCHIVE_OVERLAY_DIR" ]]; then
  tar_args+=(
    -C "$RUN_TMP_DIR"
    --transform 's,^\./_runtime-overlay/,,'
    ./_runtime-overlay/infra
  )
fi

if [[ -d "$ARCHIVE_OVERLAY_DIR/external" ]]; then
  tar_args+=(./_runtime-overlay/external)
fi
sudo tar "${tar_args[@]}"
sudo chown shellr:shellr "$FILES_ARCHIVE_FILE_PARTIAL"
chmod 600 "$FILES_ARCHIVE_FILE_PARTIAL"

sudo chown shellr:shellr "$DB_DUMP_FILE_PARTIAL"
mv "$DB_DUMP_FILE_PARTIAL" "$DB_DUMP_FILE"
mv "$FILES_ARCHIVE_FILE_PARTIAL" "$FILES_ARCHIVE_FILE"

{
  echo "timestamp=$TIMESTAMP"
  echo "host=$HOSTNAME_SHORT"
  echo "databases=$DB_DATABASE,$DMA_DB_NAME"
  echo "db_dump=$(basename "$DB_DUMP_FILE")"
  echo "files_archive=$(basename "$FILES_ARCHIVE_FILE")"
  echo "retention_count=$RETENTION_COUNT"
  backup_extra_manifest
  sha256sum "$DB_DUMP_FILE" "$FILES_ARCHIVE_FILE"
} > "$MANIFEST_FILE_PARTIAL"
sudo chown shellr:shellr "$MANIFEST_FILE_PARTIAL"
mv "$MANIFEST_FILE_PARTIAL" "$MANIFEST_FILE"
LOCAL_BACKUP_COMPLETE=1

sudo rm -rf -- "$RUN_TMP_DIR"

log "Behalte die neuesten $RETENTION_COUNT vollstaendigen lokalen Backup-Saetze"
prune_local_backups

if read_optional_env_file "$RESTIC_ENV_FILE" && [[ "${RESTIC_ENABLED:-false}" == "true" ]]; then
  if ! command -v restic >/dev/null 2>&1; then
    log "Offsite-Backup uebersprungen: restic ist nicht installiert."
  elif [[ -z "${RESTIC_REPOSITORY:-}" || -z "${RESTIC_PASSWORD_FILE:-}" ]]; then
    log "Offsite-Backup uebersprungen: RESTIC_REPOSITORY oder RESTIC_PASSWORD_FILE fehlt."
  else
    export RESTIC_CACHE_DIR
    mkdir -p "$RESTIC_CACHE_DIR"
    log "Starte optionales Offsite-Backup mit restic"
    restic backup \
      "$DB_DUMP_FILE" \
      "$FILES_ARCHIVE_FILE" \
      "$MANIFEST_FILE" \
      --host "$HOSTNAME_SHORT" \
      --tag genesis \
      --tag shellr \
      >> "$LOG_FILE" 2>&1

    if [[ "${RESTIC_PRUNE:-true}" == "true" ]]; then
      log "Bereinige Offsite-Snapshots nach Retention-Regeln"
      restic forget --prune \
        --keep-daily "${RESTIC_KEEP_DAILY:-7}" \
        --keep-weekly "${RESTIC_KEEP_WEEKLY:-4}" \
        --keep-monthly "${RESTIC_KEEP_MONTHLY:-3}" \
        >> "$LOG_FILE" 2>&1
    fi
  fi
else
  log "Offsite-Backup ist nicht aktiviert."
fi

log "Raeume Restore-Arbeitsverzeichnisse auf: aelter als $RESTORE_RETENTION_DAYS Tage"
find "$RESTORE_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +"$RESTORE_RETENTION_DAYS" -exec rm -rf -- {} +

mkdir -p "$(dirname "$BACKUP_HEALTH_FILE")"
printf "%s\n" "$(date +%s)" > "$BACKUP_HEALTH_FILE.tmp"
sudo chown shellr:shellr "$BACKUP_HEALTH_FILE.tmp"
mv "$BACKUP_HEALTH_FILE.tmp" "$BACKUP_HEALTH_FILE"

log "Backup abgeschlossen"
log "DB-Dump Groesse: $(du -h "$DB_DUMP_FILE" | awk '{print $1}')"
log "Dateiarchiv Groesse: $(du -h "$FILES_ARCHIVE_FILE" | awk '{print $1}')"
