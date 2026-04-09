#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

LOG_FILE="/projects/genesis/infra/backup/logs/dma-prefetch.log"
BASE_URL="https://dma.shellr.net/champion-pool.php"
mkdir -p "$(dirname "$LOG_FILE")"

log() {
  printf '[%s] %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG_FILE"
}

for elo in emerald_plus diamond_plus master_plus; do
  log "Waerme DMA Champion-Pool Cache fuer ${elo} auf"
  curl -sk --max-time 45 "${BASE_URL}?elo=${elo}" > /dev/null
done

log "DMA Champion-Pool Prefetch abgeschlossen"
