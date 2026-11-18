# DMA

The public hostname serves the static project showcase from infra/nginx/static/dma.

Interactive PHP sources, templates and related assets are retained under backend. Docker copies them outside the served web root. The container's HTTP surface contains only public/health.txt and denies PHP requests. The database health check and cache prefetch use CLI entry points.

The backend uses its existing environment for database and upstream configuration. No operator login is configured. Local overrides under backend/inc/env.local.php remain private.

See docs/dma-access.md for the boundary and deployment verification.
