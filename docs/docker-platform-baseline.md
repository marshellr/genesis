# Docker Platform Baseline

Diese VM nutzt Docker Engine mit Compose Plugin als schlanke Runtime-Basis fuer das Projekt unter `/projects/genesis`.

## Installierte Runtime

- Docker Engine
- Docker Compose Plugin
- containerd

## Docker-Netzwerke

- `genesis_frontend`
  - Nur fuer oeffentlich erreichbare Services wie spaeter Reverse Proxy oder App-Frontend
- `genesis_backend`
  - Fuer interne Services wie App-API, DMA, Datenbank
- `genesis_monitoring`
  - Fuer Monitoring- und Logging-Komponenten

## Infrastrukturstruktur

```text
/projects/genesis/infra/
  backup/
  compose/
  docker/
    networks/
    volumes/
      backups/
      grafana/
      loki/
      postgres/
      prometheus/
  env/
  logging/
  monitoring/
  nginx/
  runtime/
  shared/
```

## Logging-Defaults

Docker ist global auf `json-file` mit Rotation gesetzt:

- `max-size=10m`
- `max-file=3`
- `compress=true`

Das begrenzt den Log-Verbrauch pro Container auf grob etwa 30 MB plus Kompression.

## Volume-Strategie

- Persistente Daten spaeter nur fuer zustandsbehaftete Services
- Trennung nach Verantwortung statt gemischter Sammel-Volumes
- Monitoring-Volumes bewusst klein halten
- Backup-Zwischenstaende getrennt unter `volumes/backups/`

## Wichtiger Betriebsaspekt

Docker-Portfreigaben koennen UFW-Regeln umgehen. Container-Ports sollten spaeter nur gezielt veroeffentlicht werden. Interne Dienste bleiben auf benannten Docker-Netzwerken ohne Host-Port-Binding.
