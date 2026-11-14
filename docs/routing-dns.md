# Routing and DNS

Cloudflare Tunnel routes the documented public application hostnames to Nginx over HTTPS. The origin uses a Let’s Encrypt wildcard renewed through DNS-01. The www hostname redirects to the portfolio. GitHub Pages serves the separate documentation domain.

Pi-hole provides internal DNS for LAN clients. Internal interfaces resolve through Pi-hole and are reached on the local network.
