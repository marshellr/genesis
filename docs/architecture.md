# Architecture

Proxmox VE runs on operator-owned hardware in Germany. A Docker VM hosts the web, database, monitoring, and logging services.

## Routing and isolation

- Cloudflare terminates visitor TLS and sends public traffic through an outbound tunnel to Nginx over HTTPS. The origin uses a Let’s Encrypt wildcard renewed with DNS-01.
- Pi-hole resolves internal names. Grafana and the Uptime Kuma status interface are reachable on the internal network.
- Docker networks separate frontend, database, and monitoring traffic. MariaDB stays on the backend network.
- GitHub Pages serves the documentation independently at [docs.shellr.net](https://docs.shellr.net).

## Operating boundaries

The web and observability stacks share one VM. The VM pulls and verifies [versioned releases](deployment-flow.md), and [encrypted offsite backups](backup-restore.md) provide a recovery path if local storage is lost.
