#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

PROJECT_ROOT="/projects/genesis"
STATE_DIR="/var/lib/genesis-maintenance"
LOCK_FILE="/run/lock/genesis-post-reboot-health.lock"
CORE_COMPOSE="$PROJECT_ROOT/infra/compose/docker-compose.yml"
CORE_ENV="$PROJECT_ROOT/infra/compose/.env"
MONITORING_COMPOSE="$PROJECT_ROOT/infra/monitoring/docker-compose.monitoring.yml"
MONITORING_ENV="$PROJECT_ROOT/infra/monitoring/.env"
LOGGING_COMPOSE="$PROJECT_ROOT/infra/logging/docker-compose.logging.yml"
LOGGING_ENV="$PROJECT_ROOT/infra/logging/.env"
MODE="${1:---reconcile}"

EXPECTED_CONTAINERS=(
  genesis-db
  genesis-app
  genesis-dma
  genesis-nginx
  genesis-monitoring-prometheus
  genesis-monitoring-grafana
  genesis-monitoring-alertmanager
  genesis-monitoring-node-exporter
  genesis-monitoring-cadvisor
  genesis-monitoring-uptime-kuma
  genesis-logging-loki
  genesis-logging-alloy
)

validate_extra_projects() { :; }
reconcile_extra_projects() { :; }
check_extra_endpoints() { :; }
if [[ -f "$PROJECT_ROOT/infra/local/health.sh" ]]; then
  source "$PROJECT_ROOT/infra/local/health.sh"
fi

log() {
  printf '[post-reboot-health] %s %s\n' "$(date '+%F %T')" "$*"
}

fail() {
  log "ERROR: $*"
  exit 1
}

on_error() {
  local exit_code=$?
  mkdir -p "$STATE_DIR"
  printf '%s exit=%s\n' "$(date -u +%FT%TZ)" "$exit_code" > "$STATE_DIR/last-boot-health-failure"
  log "Gesundheitspruefung fehlgeschlagen (Exit-Code $exit_code)."
  exit "$exit_code"
}

require_file() {
  [[ -f "$1" ]] || fail "Fehlende Datei: $1"
}

wait_for_docker() {
  local attempt
  for attempt in $(seq 1 60); do
    if systemctl is-active --quiet docker && docker info >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  fail "Docker wurde nicht rechtzeitig betriebsbereit."
}

validate_compose() {
  docker compose --env-file "$CORE_ENV" -f "$CORE_COMPOSE" config >/dev/null
  docker compose --env-file "$MONITORING_ENV" -f "$MONITORING_COMPOSE" config >/dev/null
  docker compose --env-file "$LOGGING_ENV" -f "$LOGGING_COMPOSE" config >/dev/null
  validate_extra_projects
}

reconcile_stacks() {
  timeout 480 docker compose --env-file "$CORE_ENV" -f "$CORE_COMPOSE" up -d --no-build --remove-orphans --wait db app dma nginx
  timeout 480 docker compose --env-file "$MONITORING_ENV" -f "$MONITORING_COMPOSE" up -d --no-build --remove-orphans --wait
  timeout 300 docker compose --env-file "$LOGGING_ENV" -f "$LOGGING_COMPOSE" up -d --no-build --remove-orphans --wait
  reconcile_extra_projects
}

container_ready() {
  local container="$1"
  local running
  local health

  running="$(docker inspect -f '{{.State.Running}}' "$container" 2>/dev/null || true)"
  [[ "$running" == "true" ]] || return 1
  health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container")"
  [[ "$health" == "healthy" || "$health" == "none" ]]
}

wait_for_containers() {
  local attempt
  local container
  local all_ready

  for attempt in $(seq 1 60); do
    all_ready=1
    for container in "${EXPECTED_CONTAINERS[@]}"; do
      if ! container_ready "$container"; then
        all_ready=0
      fi
    done
    if (( all_ready == 1 )); then
      return 0
    fi
    sleep 5
  done

  for container in "${EXPECTED_CONTAINERS[@]}"; do
    container_ready "$container" || log "Nicht bereit: $container"
  done
  fail "Nicht alle erwarteten Container wurden rechtzeitig gesund."
}

http_code() {
  local host="$1"
  local path="$2"
  curl -LsS --connect-timeout 3 --max-time 10 --resolve "$host:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$host$path" || true
}

wait_for_endpoints() {
  local attempt
  local shellr_code
  local dma_code
  local status_code
  local grafana_code

  for attempt in $(seq 1 60); do
    shellr_code="$(http_code shellr.net /health)"
    dma_code="$(http_code dma.shellr.net /)"
    status_code="$(http_code status.shellr.net /)"
    grafana_code="$(http_code grafana.shellr.net /login)"


    if [[ "$shellr_code" == "200" && "$dma_code" == "200" && "$status_code" == "200" && "$grafana_code" == "401" ]] && check_extra_endpoints && python3 "$PROJECT_ROOT/scripts/check-public-routing.py" --local --rounds 3; then
      log "Endpunkte gesund: shellr=$shellr_code dma=$dma_code status=$status_code grafana=$grafana_code"
      return 0
    fi
    sleep 5
  done

  fail "Endpunkte nicht gesund: shellr=$shellr_code dma=$dma_code status=$status_code grafana=$grafana_code"
}

[[ "$EUID" -eq 0 ]] || fail "Dieses Skript muss als root laufen."
[[ "$MODE" == "--reconcile" || "$MODE" == "--check-only" ]] || fail "Unbekannter Modus: $MODE"

for command_name in curl docker flock seq systemctl timeout; do
  command -v "$command_name" >/dev/null 2>&1 || fail "Fehlendes Kommando: $command_name"
done
for required_path in "$CORE_ENV" "$MONITORING_ENV" "$LOGGING_ENV"; do
  require_file "$required_path"
done

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  log "Eine andere Post-Boot-Pruefung ist bereits aktiv."
  exit 0
fi

trap on_error ERR
wait_for_docker
validate_compose


if [[ "$MODE" == "--reconcile" ]]; then
  reconcile_stacks
fi

wait_for_containers
wait_for_endpoints

mkdir -p "$STATE_DIR"
printf '%s\n' "$(date -u +%FT%TZ)" > "$STATE_DIR/last-boot-health-success"
rm -f -- "$STATE_DIR/last-boot-health-failure"
log "Alle Dienste und Endpunkte sind gesund."
