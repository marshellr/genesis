# Monitoring Stack

This stack is intentionally lean for a single VM.

## Components

- Prometheus for metrics collection from Node Exporter
- Grafana for dashboards
- Node Exporter for host metrics
- Uptime Kuma for synthetic availability checks

## Local ports

- Grafana: `127.0.0.1:3000`
- Prometheus: `127.0.0.1:9090`
- Uptime Kuma: `127.0.0.1:3001`

## Start

```bash
cp /projects/genesis/infra/monitoring/.env.example /projects/genesis/infra/monitoring/.env
nano /projects/genesis/infra/monitoring/.env

docker compose \
  --env-file /projects/genesis/infra/monitoring/.env \
  -f /projects/genesis/infra/monitoring/docker-compose.monitoring.yml \
  up -d
```

## Stop

```bash
docker compose \
  --env-file /projects/genesis/infra/monitoring/.env \
  -f /projects/genesis/infra/monitoring/docker-compose.monitoring.yml \
  down
```

## Access

- Grafana is published behind `grafana.shellr.net`
- Uptime Kuma is published behind `status.shellr.net`
- Keep Grafana behind the Nginx Basic Auth layer and Grafana's own login

## Recommended dashboards

- Grafana dashboard ID `1860` - Node Exporter Full
- Grafana dashboard ID `3662` - Prometheus 2.0 Stats
- Keep the local `Genesis VM Overview` dashboard as a lightweight default

## Recommended Uptime Kuma checks

- `https://shellr.net/health`
- `https://shellr.net/`
- `https://dma.shellr.net/`
- `https://docs.shellr.net/`
- `https://grafana.shellr.net/login`
- `https://status.shellr.net/`

## Log size protection

- Docker stdout/stderr uses `json-file` rotation with 10 MiB per file and 3 files per container.
- Uptime Kuma 1.x also appends unexpected application errors to `/app/data/error.log`; Docker's log driver does not manage that file.
- Deployment installs `infra/monitoring/logrotate/uptime-kuma.conf` as root-owned `/etc/logrotate.d/genesis-uptime-kuma`; it rotates the bind-mounted file at 10 MiB, keeps 3 compressed archives, and uses `copytruncate`.
- `infra/backup/cron/uptime-kuma-logrotate.cron` evaluates the size every 5 minutes.
- Backup runtime snapshots exclude `*.log` and `*.log.*`, so application logs never become part of the file archive.

## Prometheus notes

- Prometheus scrapes:
  - itself
  - Node Exporter
  - Uptime Kuma metrics
- Uptime Kuma metrics require authentication and are read from a VM-local secret file under `infra/monitoring/prometheus/secrets/`
