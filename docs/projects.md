# Projects

## shellr platform

Single-VM platform on Hetzner with Docker Compose, Nginx, TLS, monitoring, logging, backup, and GitHub Actions deployment.

### Technical focus

- subdomain-based routing instead of wildcard shortcuts
- explicit runtime separation on one host
- bounded observability retention
- documented recovery paths

## DMA application

Legacy PHP application migrated into the platform as its own runtime surface.

### Integration highlights

- dedicated subdomain on `dma.shellr.net`
- own database scope inside the MariaDB service
- reverse-proxy integration without coupling it to the landing page
- controlled handling of legacy configuration and secret rotation
