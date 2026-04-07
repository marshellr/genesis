# Lessons Learned

## 1. Subdomains scale better than subpaths for mixed apps

Using `dma.shellr.net` is operationally cleaner than forcing DMA under `/dma` on the main site.

## 2. Small hosts need hard limits everywhere

Log retention, metric retention, image cleanup, and backup scope all need explicit boundaries on a 40 GB VM.

## 3. A reverse proxy is an architecture surface, not just a convenience

Clean hostnames, redirects, access control, and TLS handling shape how professional the platform feels and how safely it can be operated.

## 4. Docs should not compete with production resources

Serving `docs.shellr.net` from GitHub Pages keeps the VM focused on runtime workloads and makes documentation versioned by default.

## 5. Protected observability is still observability

Grafana does not need to be public to be useful. Putting it behind Nginx access control is the right default.
