# DMA

DMA is a legacy PHP workload integrated into the `shellr` platform as a separate runtime surface on [dma.shellr.net](https://dma.shellr.net).

The project is intentionally kept separate from the main landing page so it can be operated, monitored, and migrated without coupling it to the rest of the public frontdoor.

## What It Does

- serves a dedicated DMA statistics interface
- runs as its own container behind the shared Nginx reverse proxy
- uses a dedicated database scope inside the shared MariaDB service
- participates in platform monitoring and uptime checks

## Runtime Architecture

```text
dma.shellr.net
  -> Nginx reverse proxy
  -> DMA PHP container
  -> MariaDB (dedicated DMA database scope)
```

## Technologies

- PHP
- MariaDB
- Docker
- Docker Compose
- Nginx

## Local / Platform Start

DMA is started through the main platform stack:

```bash
docker compose \
  --env-file infra/compose/.env \
  -f infra/compose/docker-compose.yml \
  up -d dma db nginx
```

## Configuration

Environment and integration defaults are defined through:

- `infra/compose/.env.example`
- `dma/inc/env.local.php.example`
- `dma/inc/env.php`

Runtime secrets should not be committed. They belong in environment files or host-level secret injection.

## Operational Notes

- DMA is exposed on its own subdomain instead of a subpath
- health checks are part of the platform monitoring model
- the workload is treated as a real runtime surface, not as a static portfolio artifact

## Related Links

- [Main platform](https://shellr.net)
- [Technical docs](https://docs.shellr.net)
- [System status](https://status.shellr.net)
