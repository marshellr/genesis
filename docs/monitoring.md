# Monitoring

The monitoring and logging interfaces are available on the internal network.

- Prometheus collects service and host metrics from Node Exporter, cAdvisor, and application endpoints. Alertmanager handles Prometheus alerts.
- Grafana provides authenticated access to metrics and logs.
- Grafana Alloy forwards logs to Loki, which retains them for 72 hours.
- Uptime Kuma checks service availability, including public routing through Cloudflare. The internal status snapshot shows the last successful local backup.

During an incident, compare the public request with an origin probe, inspect Prometheus targets and container health, and use Loki to correlate relevant logs.

## Uptime Kuma upgrades

The container is pinned to a version and image digest. Before a major upgrade, test migration against a separate data copy with networking disabled. Stop the production container and archive its entire data directory before recreating it. Compare monitor definitions and notification mappings after migration. A rollback restores the complete pre-upgrade directory together with the old image.

Follow the [v1-to-v2 migration guide](https://github.com/louislam/uptime-kuma/wiki/Migration-From-v1-To-v2). Use the regular image for this migration.
