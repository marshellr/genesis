# Logging Stack

This stack keeps central logging intentionally small for a single VM.

## Components

- Loki for log storage
- Promtail for log shipping from Docker containers
- Existing Grafana instance reused as the UI

## Retention Strategy

- Loki retention: 72 hours
- Max lookback: 72 hours
- Filesystem store only
- No replicas, no object storage, no extra cache layers
- Promtail excludes Loki and Promtail self-logs to avoid noisy loops

## Start

```bash
cp /projects/genesis/infra/logging/.env.example /projects/genesis/infra/logging/.env

docker compose \
  --env-file /projects/genesis/infra/logging/.env \
  -f /projects/genesis/infra/logging/docker-compose.logging.yml \
  up -d
```

## Verify

```bash
docker compose \
  --env-file /projects/genesis/infra/logging/.env \
  -f /projects/genesis/infra/logging/docker-compose.logging.yml \
  ps

curl -s http://127.0.0.1:3000/api/health
```

## Disk notes

- Keep Loki at 72h on a 40 GB VM unless logs remain tiny over time
- Docker container logs are already rotated at 10 MB x 3 per container
- Loki is for short retention and troubleshooting, not long-term archive
