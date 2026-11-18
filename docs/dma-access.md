# DMA backend boundary

The public DMA hostname serves the static project showcase. PHP paths return HTTP 404. Unrecognised static paths also return HTTP 404.

The interactive PHP sources live under dma/backend. The image copies them to /opt/dma/backend, outside Apache's /var/www/html web root. The web root contains only a static health marker. Apache denies PHP files as a second boundary.

No operator account is required. The backend has no published host port. Database health is checked through PHP CLI. The existing prefetch job invokes a bounded CLI command inside the backend container, using the existing environment and temporary cache.

Local backend overrides are excluded from Git, images and release synchronization. Deploy verification and CI probe the public boundary. Keep all backend PHP outside the web root when extending the application.
