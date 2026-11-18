# DMA write access

The public DMA hostname serves the static showcase. The PHP service has no published host port.

The champion-pool write operations require an explicitly configured operator, verified by PHP with HTTP Basic credentials. CSRF validation follows successful authentication. Anonymous requests cannot bootstrap champion records.

Provide the operator name through `DMA_WRITE_USERNAME` and the path to a PHP `password_hash` value through `DMA_WRITE_PASSWORD_HASH_FILE`. Mount the hash file read-only. The local `dma/inc/env.local.php` override accepts the same keys and is excluded from Git, image builds, and release synchronization.

Without both settings, writes return HTTP 401. Proxy-supplied user headers grant no permissions. The existing disabled public synchronization paths remain disabled.
