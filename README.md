# shellr

Single-VM DevOps portfolio platform for [shellr.net](https://shellr.net).

This project is built to show production-like engineering decisions on a small host without hiding the system behind unnecessary complexity. The platform runs on one Hetzner VM and combines reverse proxying, HTTPS, containerized applications, CI/CD, monitoring, logging, backup, and technical documentation in one maintainable setup.

## Live Surfaces

- `https://shellr.net` - personal landing page and platform frontdoor
- `https://dma.shellr.net` - DMA application as a separate runtime surface
- `https://status.shellr.net` - public uptime and service status
- `https://docs.shellr.net` - technical documentation on GitHub Pages
- `https://grafana.shellr.net` - protected observability surface

## What This Platform Covers

- Nginx reverse proxy with TLS termination
- Docker Engine and Docker Compose runtime
- PHP-based application workloads
- MariaDB-backed services
- GitHub Actions deployment over SSH
- Monitoring with Prometheus, Grafana, Node Exporter, and Uptime Kuma
- Central logging with Loki and Promtail
- Backup and restore for project files and database state

## Platform Principles

- One VM, no pseudo-cluster
- Explicit hostnames instead of wildcard shortcuts
- Small operational footprint with bounded retention
- Documented deployment, rollback, backup, and restore paths
- Clear separation between public surfaces and protected operational tools

## Stack

- Host: Ubuntu on Hetzner Cloud
- Runtime: Docker Engine, Docker Compose
- Reverse proxy: Nginx
- Main site: PHP
- DMA app: PHP + MariaDB
- CI/CD: GitHub Actions + SSH deploy
- Monitoring: Prometheus, Grafana, Node Exporter, Uptime Kuma
- Logging: Loki, Promtail, Grafana
- Backup: MariaDB dump + project archive + rotation
- Documentation: GitHub Pages

## Repository Layout

```text
/projects/genesis
  app/                  main website and portfolio frontdoor
  dma/                  DMA application
  infra/
    compose/            Compose stacks and env templates
    nginx/              reverse proxy and TLS configuration
    monitoring/         monitoring stack configuration
    logging/            Loki and Promtail configuration
    backup/             backup artifacts and cron definitions
  scripts/              operational and deployment scripts
  docs/                 technical documentation and GitHub Pages content
```

## Documentation

- [Docs Index](/docs/README.md)
- [Projects](/docs/projects.md)
- [Architecture](/docs/architecture.md)
- [Deployment Flow](/docs/deployment-flow.md)
- [Monitoring](/docs/monitoring.md)
- [Logging](/docs/logging.md)
- [Routing and DNS](/docs/routing-dns.md)
- [Backup and Restore](/docs/backup-restore.md)
- [Lessons Learned](/docs/lessons-learned.md)
- [GitHub Pages](/docs/github-pages.md)

## Why This Exists

This is not meant to imitate enterprise scale. The goal is to demonstrate how a small platform can still be designed and operated with discipline: explicit routing, controlled runtime boundaries, observable services, careful disk usage, and documentation that explains how the system actually works.

## Notes

- `docs.shellr.net` is intentionally hosted on GitHub Pages, not on the VM
- `grafana.shellr.net` is intentionally protected and not meant to be a public dashboard
- the platform is designed for maintainability and explainability over tool sprawl
