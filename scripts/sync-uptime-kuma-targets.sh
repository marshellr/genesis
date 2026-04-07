#!/usr/bin/env bash
set -euo pipefail

KUMA_URL="${KUMA_URL:-http://genesis-monitoring-uptime-kuma:3001}"
KUMA_USERNAME="${KUMA_USERNAME:-shellr-admin}"
KUMA_PASSWORD="${KUMA_PASSWORD:?KUMA_PASSWORD is required}"

cleanup() {
  docker rm -f genesis-monitor-targets >/dev/null 2>&1 || true
}

trap cleanup EXIT

cleanup

docker run -d \
  --name genesis-monitor-targets \
  --network genesis_monitoring \
  --label "kuma.platform.group.name=Shellr Platform" \
  --label "kuma.shellrhome.http.name=shellr.net" \
  --label "kuma.shellrhome.http.parent_name=platform" \
  --label "kuma.shellrhome.http.url=https://shellr.net/" \
  --label "kuma.shellrhealth.http.name=shellr.net health" \
  --label "kuma.shellrhealth.http.parent_name=platform" \
  --label "kuma.shellrhealth.http.url=https://shellr.net/health" \
  --label "kuma.dma.http.name=dma.shellr.net" \
  --label "kuma.dma.http.parent_name=platform" \
  --label "kuma.dma.http.url=https://dma.shellr.net/" \
  --label "kuma.docs.http.name=docs.shellr.net" \
  --label "kuma.docs.http.parent_name=platform" \
  --label "kuma.docs.http.url=https://docs.shellr.net/" \
  --label "kuma.grafana.http.name=grafana internal health" \
  --label "kuma.grafana.http.parent_name=platform" \
  --label "kuma.grafana.http.url=http://genesis-monitoring-grafana:3000/api/health" \
  --label "kuma.status.http.name=status.shellr.net" \
  --label "kuma.status.http.parent_name=platform" \
  --label "kuma.status.http.url=https://status.shellr.net/" \
  alpine:3.20 sleep infinity >/dev/null

set +e
timeout 30s docker run --rm \
  --network genesis_monitoring \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -e AUTOKUMA__KUMA__URL="$KUMA_URL" \
  -e AUTOKUMA__KUMA__USERNAME="$KUMA_USERNAME" \
  -e AUTOKUMA__KUMA__PASSWORD="$KUMA_PASSWORD" \
  -e AUTOKUMA__DOCKER__HOSTS=unix:///var/run/docker.sock \
  -e AUTOKUMA__DOCKER__LABEL_PREFIX=kuma \
  -e AUTOKUMA__ON_DELETE=delete \
  ghcr.io/bigboot/autokuma:uptime-kuma-v1-latest
status=$?
set -e

if [[ "$status" -ne 0 && "$status" -ne 124 ]]; then
  exit "$status"
fi
