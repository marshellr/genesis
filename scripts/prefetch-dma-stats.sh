#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

LOG_FILE="/projects/genesis/infra/backup/logs/dma-prefetch.log"
mkdir -p "$(dirname "$LOG_FILE")"

log() {
  printf '[%s] %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG_FILE"
}

for elo in emerald_plus diamond_plus master_plus; do
  log "Waerme DMA Champion-Pool Cache fuer ${elo} auf"
  sudo -n docker exec genesis-dma timeout --kill-after=5s 45s php /opt/dma/backend/prefetch.php "$elo"
done

log "DMA Champion-Pool Prefetch abgeschlossen"
