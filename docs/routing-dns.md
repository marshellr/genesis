# Routing and DNS

Cloudflare Tunnel routes the documented public application hostnames to Nginx over HTTPS. The origin uses a Let’s Encrypt wildcard renewed through DNS-01. The www hostname redirects to the portfolio. GitHub Pages serves the separate documentation domain.

Pi-hole provides internal DNS for LAN clients. Internal interfaces resolve through Pi-hole and are reached on the local network.

## Network boundaries

The documented public tunnel routes are the portfolio, its www redirect and the static DMA showcase. Monitoring and management interfaces use internal DNS and LAN access. Published monitoring ports bind to host loopback. The PHP DMA container has no published host port.

The reverse proxy listens on HTTP and HTTPS for LAN and tunnel ingress. Router port forwarding and host or hypervisor firewall rules therefore remain part of the boundary. Confirm the configured tunnel hostnames, router forwarding and external reachability from an independent network after routing changes. The repository describes this boundary without publishing host addresses or private tunnel credentials.
