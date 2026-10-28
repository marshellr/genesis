#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

PROJECT_ROOT="/projects/genesis"
BACKUP_SCRIPT="$PROJECT_ROOT/scripts/backup.sh"
POST_BOOT_SCRIPT="$PROJECT_ROOT/scripts/post-reboot-health.sh"
STATE_DIR="/var/lib/genesis-maintenance"
LOCK_FILE="/run/lock/genesis-weekly-maintenance.lock"
MIN_FREE_MIB="${GENESIS_PATCH_MIN_FREE_MIB:-4096}"
CORE_COMPOSE="$PROJECT_ROOT/infra/compose/docker-compose.yml"
CORE_ENV="$PROJECT_ROOT/infra/compose/.env"
MONITORING_COMPOSE="$PROJECT_ROOT/infra/monitoring/docker-compose.monitoring.yml"
MONITORING_ENV="$PROJECT_ROOT/infra/monitoring/.env"
LOGGING_COMPOSE="$PROJECT_ROOT/infra/logging/docker-compose.logging.yml"
LOGGING_ENV="$PROJECT_ROOT/infra/logging/.env"
MODE="${1:-run}"

log() {
  printf '[weekly-maintenance] %s %s\n' "$(date '+%F %T')" "$*"
}

fail() {
  log "ERROR: $*"
  exit 1
}

on_error() {
  local exit_code=$?
  mkdir -p "$STATE_DIR"
  printf '%s exit=%s\n' "$(date -u +%FT%TZ)" "$exit_code" > "$STATE_DIR/last-patch-failure"
  log "Patchvorgang fehlgeschlagen (Exit-Code $exit_code); kein Neustart wird ausgeloest."
  exit "$exit_code"
}

require_file() {
  [[ -f "$1" ]] || fail "Fehlende Datei: $1"
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Fehlendes Kommando: $1"
}

validate_compose() {
  docker compose --env-file "$CORE_ENV" -f "$CORE_COMPOSE" config >/dev/null
  docker compose --env-file "$MONITORING_ENV" -f "$MONITORING_COMPOSE" config >/dev/null
  docker compose --env-file "$LOGGING_ENV" -f "$LOGGING_COMPOSE" config >/dev/null
}

check_dpkg_state() {
  local audit_output
  audit_output="$(dpkg --audit)"
  [[ -z "$audit_output" ]] || fail "dpkg meldet einen inkonsistenten Zustand: $audit_output"
}

check_free_space() {
  local available_kib
  available_kib="$(df -Pk / | awk 'NR == 2 { print $4 }')"
  log "Freier Speicher vor Patch: $((available_kib / 1024)) MiB; Mindestwert: $MIN_FREE_MIB MiB"
  (( available_kib >= MIN_FREE_MIB * 1024 )) || fail "Zu wenig freier Speicher fuer den Patchvorgang"
}

verify_fresh_backup() {
  local marker="$PROJECT_ROOT/infra/backup/last-successful-backup"
  local backup_epoch
  local age

  require_file "$marker"
  backup_epoch="$(tr -d '[:space:]' < "$marker")"
  [[ "$backup_epoch" =~ ^[0-9]+$ ]] || fail "Ungueltiger Backup-Health-Marker"
  age=$(( $(date +%s) - backup_epoch ))
  (( age >= 0 && age <= 3600 )) || fail "Das Wartungsbackup ist nicht frisch genug: ${age}s"
}

[[ "$EUID" -eq 0 ]] || fail "Dieses Skript muss als root laufen."

for command_name in apt-get awk date df docker dpkg flock sync systemctl; do
  require_command "$command_name"
done
for required_path in "$BACKUP_SCRIPT" "$POST_BOOT_SCRIPT" "$CORE_ENV" "$MONITORING_ENV" "$LOGGING_ENV"; do
  require_file "$required_path"
done

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  log "Ein anderer Wartungslauf ist bereits aktiv."
  exit 0
fi

trap on_error ERR

check_dpkg_state
check_free_space
validate_compose

if [[ "$MODE" == "--check" ]]; then
  apt-get -s --with-new-pkgs upgrade >/dev/null
  log "Vorabpruefung erfolgreich; es wurden keine Pakete geaendert und kein Neustart geplant."
  exit 0
fi
[[ "$MODE" == "run" ]] || fail "Unbekannter Modus: $MODE"

log "Pruefe und reconciliere Dienste vor dem Wartungsbackup."
"$POST_BOOT_SCRIPT" --reconcile

log "Erzeuge verpflichtendes Wartungsbackup."
BACKUP_REQUIRE_RUN=true "$BACKUP_SCRIPT"
verify_fresh_backup
check_free_space

mkdir -p "$STATE_DIR"
printf '%s\n' "$(date -u +%FT%TZ)" > "$STATE_DIR/patch-started"

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export APT_LISTCHANGES_FRONTEND=none

log "Aktualisiere APT-Paketlisten."
apt-get -o DPkg::Lock::Timeout=600 update

log "Installiere verfuegbare Updates ohne automatische Paketentfernungen."
apt-get \
  -o DPkg::Lock::Timeout=600 \
  -o Dpkg::Options::=--force-confdef \
  -o Dpkg::Options::=--force-confold \
  -y --with-new-pkgs upgrade

check_dpkg_state
apt-get clean
validate_compose

log "Reconciliere Dienste nach Paketupdates."
"$POST_BOOT_SCRIPT" --reconcile

printf '%s\n' "$(date -u +%FT%TZ)" > "$STATE_DIR/last-patch-success"
rm -f -- "$STATE_DIR/last-patch-failure"
sync

log "Patchvorgang erfolgreich; geplanter Neustart wird jetzt ausgeloest."
systemctl --no-block reboot
