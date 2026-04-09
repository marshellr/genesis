#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

LOG_FILE="/projects/genesis/infra/backup/logs/docker-gc.log"
mkdir -p "$(dirname "$LOG_FILE")"

log() {
  printf '[%s] %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG_FILE"
}

log "Docker cleanup gestartet"
docker builder prune -af --filter "until=168h" >> "$LOG_FILE" 2>&1 || log "Builder prune lieferte einen Fehler"
docker image prune -af --filter "until=168h" >> "$LOG_FILE" 2>&1 || log "Image prune lieferte einen Fehler"
docker container prune -f --filter "until=168h" >> "$LOG_FILE" 2>&1 || log "Container prune lieferte einen Fehler"
log "Docker cleanup abgeschlossen"
