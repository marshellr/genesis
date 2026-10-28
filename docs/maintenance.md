# Weekly System Maintenance

## Schedule

`genesis-weekly-maintenance.timer` starts every Sunday at 05:15 Europe/Berlin with up to 10 minutes of randomized delay. The timer is deliberately not persistent: if the VM is offline during the window, it will not trigger an unexpected daytime reboot after the next boot.

This window follows the daily 03:17 backup and the Sunday 04:37 Docker garbage collection.

Ubuntu's existing daily unattended security updates remain enabled. The weekly window installs any remaining available updates and provides the controlled reboot and full application recovery check.

## Patch Safety Gates

Before any package changes, the maintenance service:

1. acquires a single-run lock
2. rejects an inconsistent `dpkg` state
3. requires at least 4 GiB free on `/`
4. validates the Docker Compose configurations
5. reconciles and health-checks the application, monitoring, and logging stacks
6. creates a fresh complete backup and verifies that its health marker is no older than one hour

The patch uses `apt-get upgrade --with-new-pkgs`. It may install dependencies required by updates, but it does not automatically remove installed packages. A failed update, failed backup, or failed health check stops the run without rebooting.

After successful updates, APT state and Compose configuration are checked again and all stacks are reconciled. Only then is a non-blocking reboot requested.

## Post-Boot Recovery

`genesis-post-reboot-health.service` is enabled at boot after Docker and network-online. It:

- validates all Compose configurations
- runs `docker compose up --no-build --wait` for the core, monitoring and logging stacks plus optional host-local extensions
- verifies that every expected container is running and healthy
- verifies local HTTPS responses for Shellr (including application identity and assets), DMA, Uptime Kuma, Grafana and optional host-local endpoints
- retries up to three times through systemd if recovery fails

Grafana HTTP 401 is considered healthy because the endpoint is intentionally protected.

## Status and Diagnostics

```bash
systemctl list-timers genesis-weekly-maintenance.timer
systemctl status genesis-weekly-maintenance.service
systemctl status genesis-post-reboot-health.service
journalctl -u genesis-weekly-maintenance.service
journalctl -u genesis-post-reboot-health.service
```

State markers are written under `/var/lib/genesis-maintenance`:

- `last-patch-success`
- `last-patch-failure`
- `last-boot-health-success`
- `last-boot-health-failure`

A non-mutating preflight can be run with:

```bash
sudo /projects/genesis/scripts/weekly-maintenance.sh --check
sudo /projects/genesis/scripts/post-reboot-health.sh --check-only
```

Do not manually start `genesis-weekly-maintenance.service` unless an immediate patch and reboot is intended.
