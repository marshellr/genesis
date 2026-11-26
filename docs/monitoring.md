# Monitoring

The monitoring and logging interfaces are available on the internal network.

- Prometheus collects service and host metrics from Node Exporter, cAdvisor, and application endpoints.
- Grafana provides authenticated access to metrics and logs.
- Grafana Alloy forwards logs to Loki, which retains them for 72 hours.
- Uptime Kuma checks service availability, including public routing through Cloudflare. The internal status snapshot shows the last successful local backup and the age of the last completed offsite backup.

During an incident, compare the public request with an origin probe, inspect Prometheus targets and container health, and use Loki to correlate relevant logs.

## Grafana headers and password state

Grafana emits its own CSP with a fresh response nonce (`GF_SECURITY_CONTENT_SECURITY_POLICY=true`), using the pinned version's unchanged default template. nginx must not add a second CSP, X-Frame-Options or X-Content-Type-Options to this virtual host; it keeps the remaining proxy headers and Basic Auth. Other virtual hosts retain their existing policies. `scripts/check-grafana-headers.py` runs during deployment and rejects a proxy policy, duplicated headers, or bootstrap scripts without the matching nonce.

The admin-password environment setting initializes an empty Grafana database; it does not reset existing credentials. Password changes belong in Grafana and persist in its data volume. The separate nginx htpasswd file is local state, ignored by Git and outside deployment synchronization. Deployments and rollbacks must neither regenerate this file nor reset Grafana users.

## Uptime Kuma upgrades

The container is pinned to a version and image digest. Before a major upgrade, test migration against a separate data copy with networking disabled. Stop the production container and archive its entire data directory before recreating it. Compare monitor definitions and notification mappings after migration. A rollback restores the complete pre-upgrade directory together with the old image.

Follow the [v1-to-v2 migration guide](https://github.com/louislam/uptime-kuma/wiki/Migration-From-v1-To-v2). Use the regular image for this migration.
