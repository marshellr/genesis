# Routing and DNS

## Hostnames

- `shellr.net`
- `www.shellr.net`
- `dma.shellr.net`
- `grafana.shellr.net`
- `status.shellr.net`
- `docs.shellr.net`

## Responsibilities

- Nginx on the VM handles all runtime hostnames except `docs.shellr.net`
- GitHub Pages handles `docs.shellr.net`

## Operational rule

Avoid wildcard DNS. Every public hostname should exist because a deliberate service needs it.
